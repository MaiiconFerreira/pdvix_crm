<?php
/**
 * PDVix WebSocket Gateway — pdv_server.php
 *
 * Stack: Workerman 4.1+ + Redis + PDO
 *
 * ── Ambientes ──────────────────────────────────────────────────────────────
 *   dev  (padrão) → ws://0.0.0.0:8080   — sem SSL, localhost
 *   prod          → wss://0.0.0.0:8443  — SSL Let's Encrypt
 *
 * Para subir em produção, defina a variável de ambiente antes de iniciar:
 *   PDV_ENV=production php pdv_server.php start -d
 *
 * Variáveis opcionais (produção):
 *   PDV_HOST   → domínio para localizar os certs (padrão: $_SERVER['HTTP_HOST'])
 *   SSL_CERT   → caminho completo para fullchain.pem
 *   SSL_KEY    → caminho completo para privkey.pem
 *
 * PID:   /tmp/pdvix_ws.pid
 * Log:   /var/www/html/pdvix_ws.log
 *
 * Canais:
 *   pdv:{loja_id}:{numero_pdv}   — privado por PDV
 *   admin:loja:{loja_id}         — admin de uma loja específica
 *   admin:global                 — superadmin, recebe tudo
 *
 * Eventos PDV → Servidor:
 *   pdv:auth            — autenticação inicial
 *   pdv:heartbeat       — keepalive a cada 30s
 *   pdv:venda_finalizada
 *   pdv:caixa_aberto / pdv:caixa_fechado
 *   pdv:cmd_resultado   — resultado de comando remoto executado
 *
 * Eventos Servidor → PDV:
 *   ws:auth_ok / ws:auth_fail
 *   pdv:comando         — comando remoto (reiniciar, desconto, cancelar, etc.)
 *   pdv:pagamento_confirmado / pdv:pagamento_cancelado
 *   pdv:pagamento_pendente  — QR Code PIX gerado
 *   pdv:carga_disponivel
 *
 * Eventos Servidor → Admin:
 *   admin:pdv_online / admin:pdv_offline
 *   admin:pdv_status
 *   admin:venda_nova
 *   admin:pagamento_confirmado
 *   admin:cmd_resultado
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../config.php';

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;

date_default_timezone_set('America/Cuiaba');

// ─────────────────────────────────────────────────────────────────────────────
// PDO — Inicialização lazy com static (funciona em Windows + Linux)
//
// No Windows o Workerman NÃO usa fork(): cada worker é um processo PHP
// separado. Por isso referências (&$pdo) definidas no processo pai nunca
// chegam ao filho. A solução é criar a conexão na primeira vez que for
// necessária, dentro do próprio processo worker, usando static.
// ─────────────────────────────────────────────────────────────────────────────
function getPdo(): \PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, DB_USER, DB_PASS, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        echo "[PDO] Conexão MySQL estabelecida\n";
    }
    return $pdo;
}

// ─────────────────────────────────────────────────────────────────────────────
// Redis — mesma estratégia lazy
// ─────────────────────────────────────────────────────────────────────────────
function getRedis(): \Redis
{
    static $redis = null;
    if ($redis === null) {
        $redis = new \Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        echo "[REDIS] Conexão estabelecida\n";
    }
    return $redis;
}

// ── Ambiente ──────────────────────────────────────────────────────────────────
//$IS_PROD = (getenv('PDV_ENV') === 'production');
$IS_PROD = true;

if ($IS_PROD) {
    $host     = "pdvix.vps-kinghost.net";
    $certBase = '/etc/letsencrypt/live/' . $host;

    $SSL_CONTEXT = [
        'ssl' => [
            'local_cert'        => getenv('SSL_CERT') ?: "{$certBase}/fullchain.pem",
            'local_pk'          => getenv('SSL_KEY')  ?: "{$certBase}/privkey.pem",
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ],
    ];

    $ws = new Worker('websocket://0.0.0.0:8443', $SSL_CONTEXT);
    $ws->transport = 'ssl';
    echo "[ENV] Modo PRODUÇÃO — wss://0.0.0.0:8443 (SSL habilitado)\n";
} else {
    $ws = new Worker('websocket://0.0.0.0:' . WS_PORT);
    echo "[ENV] Modo DEV — ws://0.0.0.0:" . WS_PORT . " (sem SSL)\n";
}

$ws->count = 1;

// ── Estado em memória ─────────────────────────────────────────────────────────
//
// $clients[conn_id] = [
//   'conn'       => TcpConnection,
//   'tipo'       => 'pdv' | 'admin' | null,
//   'loja_id'    => int,
//   'numero_pdv' => string,
//   'usuario_id' => int,
//   'auth'       => bool,
//   'last_ping'  => timestamp,
// ]
//
// $pdvIndex["{loja_id}:{numero_pdv}"] = conn_id  (lookup O(1))
// $adminIndex[conn_id] = loja_id | 'global'
//
$clients    = [];
$pdvIndex   = [];
$adminIndex = [];

// ── Conexão ───────────────────────────────────────────────────────────────────
$ws->onConnect = function (TcpConnection $conn) use (&$clients) {
    $clients[$conn->id] = [
        'conn'       => $conn,
        'tipo'       => null,
        'loja_id'    => 0,
        'numero_pdv' => '',
        'usuario_id' => 0,
        'auth'       => false,
        'last_ping'  => time(),
    ];

    $conn->send(json_encode([
        'event' => 'ws:connected',
        'msg'   => 'PDVix Gateway v3.0',
    ]));

    echo "[CONNECT] #{$conn->id}\n";
};

// ── Desconexão ────────────────────────────────────────────────────────────────
// Nota: $pdo NÃO está no use() — é obtido via getPdo() quando necessário.
$ws->onClose = function (TcpConnection $conn) use (&$clients, &$pdvIndex, &$adminIndex) {
    $c = $clients[$conn->id] ?? null;

    if ($c && $c['tipo'] === 'pdv' && $c['auth']) {
        _pdvSetOnline($c['loja_id'], $c['numero_pdv'], false);

        _broadcastAdmin(
            $clients,
            $adminIndex,
            $c['loja_id'],
            json_encode([
                'event'      => 'admin:pdv_offline',
                'loja_id'    => $c['loja_id'],
                'numero_pdv' => $c['numero_pdv'],
            ])
        );

        unset($pdvIndex["{$c['loja_id']}:{$c['numero_pdv']}"]);
    }

    unset($adminIndex[$conn->id], $clients[$conn->id]);
    echo "[CLOSE] #{$conn->id}\n";
};

// ── Mensagens ─────────────────────────────────────────────────────────────────
$ws->onMessage = function (TcpConnection $conn, string $data) use (&$clients, &$pdvIndex, &$adminIndex) {
    $payload = json_decode($data, true);
    if (!$payload || empty($payload['event'])) return;

    $event = $payload['event'];
    $body  = $payload['payload'] ?? [];
    $c     = &$clients[$conn->id];

    // ── Eventos não autenticados ──────────────────────────────────────────────
    if ($event === 'pdv:auth') {
        _handlePdvAuth($conn, $c, $body, $clients, $pdvIndex, $adminIndex);
        return;
    }

    if ($event === 'admin:auth') {
        _handleAdminAuth($conn, $c, $body, $adminIndex);
        return;
    }

    // ── Rejeita não autenticados ──────────────────────────────────────────────
    if (!($c['auth'] ?? false)) {
        $conn->send(json_encode(['event' => 'ws:auth_fail', 'msg' => 'Não autenticado.']));
        return;
    }

    $c['last_ping'] = time();

    // ── Eventos PDV ───────────────────────────────────────────────────────────
    switch ($event) {

        case 'pdv:heartbeat':
            $conn->send(json_encode(['event' => 'ws:pong']));
            break;

        case 'pdv:venda_finalizada':
            _broadcastAdmin($clients, $adminIndex, $c['loja_id'], json_encode([
                'event'      => 'admin:venda_nova',
                'loja_id'    => $c['loja_id'],
                'numero_pdv' => $c['numero_pdv'],
                'payload'    => $body,
            ]));
            break;

        case 'pdv:caixa_aberto':
        case 'pdv:caixa_fechado':
            _broadcastAdmin($clients, $adminIndex, $c['loja_id'], json_encode([
                'event'      => 'admin:pdv_status',
                'loja_id'    => $c['loja_id'],
                'numero_pdv' => $c['numero_pdv'],
                'payload'    => $body,
            ]));
            break;

        case 'pdv:cmd_resultado':
            _broadcastAdmin($clients, $adminIndex, $c['loja_id'], json_encode([
                'event'      => 'admin:cmd_resultado',
                'loja_id'    => $c['loja_id'],
                'numero_pdv' => $c['numero_pdv'],
                'payload'    => $body,
            ]));
            break;
    }
};

// ── Worker Start — Timers ─────────────────────────────────────────────────────
// Aqui apenas inicializamos as conexões lazy para "aquecer" o cache
// e registramos os timers. O $pdo e $redis são acessados via getPdo()/getRedis().
$ws->onWorkerStart = function () use (&$clients, &$pdvIndex, &$adminIndex) {

    // Aquece as conexões lazy logo no startup do worker
    getRedis();
    getPdo();

    // ── Timer 1: Distribui comandos remotos aos PDVs (0.1s) ──────────────────
    Timer::add(0.1, function () use (&$clients, &$pdvIndex) {
        foreach ($pdvIndex as $key => $connId) {
            [$lojaId, $numeroPdv] = explode(':', $key, 2);
            $chave   = "pdv:cmd:{$lojaId}:{$numeroPdv}";
            $cmdJson = getRedis()->lPop($chave);
            if (!$cmdJson) continue;

            $conn = $clients[$connId]['conn'] ?? null;
            if ($conn) {
                $conn->send(json_encode([
                    'event'   => 'pdv:comando',
                    'payload' => json_decode($cmdJson, true),
                ]));
                echo "[CMD] → PDV {$lojaId}:{$numeroPdv} | {$cmdJson}\n";
            }
        }
    });

    // ── Timer 2: Roteamento de pagamentos confirmados (0.1s) ─────────────────
    Timer::add(0.1, function () use (&$clients, &$pdvIndex, &$adminIndex) {
        $item = getRedis()->lPop('pdv:fila_pagamentos');
        if (!$item) return;

        $data        = json_decode($item, true);
        $numeroVenda = $data['numero_venda'] ?? '';
        if (!$numeroVenda) return;

        $destino = getRedis()->get("pdv:venda_pdv:{$numeroVenda}");
        if ($destino) {
            $connId = $pdvIndex[$destino] ?? null;
            $conn   = $connId ? ($clients[$connId]['conn'] ?? null) : null;
            if ($conn) {
                $evento = 'pdv:pagamento_confirmado';
                if (isset($data['status']) && in_array($data['status'], ['failed', 'canceled'])) {
                    $evento = 'pdv:pagamento_cancelado';
                }
                $conn->send(json_encode(['event' => $evento, 'payload' => $data]));
                echo "[PAGAMENTO] → PDV {$destino} | venda {$numeroVenda}\n";
            }
            getRedis()->del("pdv:venda_pdv:{$numeroVenda}");
        }

        [$lojaId] = explode(':', $destino ?? '0:', 2);
        _broadcastAdmin($clients, $adminIndex, (int) $lojaId, json_encode([
            'event'   => 'admin:pagamento_confirmado',
            'payload' => $data,
        ]));
    });

    // ── Timer 3: Entrega QR Code PIX pendente ao PDV (0.1s) ──────────────────
    Timer::add(0.1, function () use (&$clients, &$pdvIndex) {
        foreach ($pdvIndex as $key => $connId) {
            [$lojaId, $numeroPdv] = explode(':', $key, 2);
            $keys = getRedis()->keys("pdv:pagamento_pendente:*");
            foreach ($keys as $k) {
                $qrData = getRedis()->lPop($k);
                if (!$qrData) continue;

                $numeroVenda = str_replace('pdv:pagamento_pendente:', '', $k);
                $destino     = getRedis()->get("pdv:venda_pdv:{$numeroVenda}");
                if ($destino !== "{$lojaId}:{$numeroPdv}") {
                    getRedis()->rPush($k, $qrData);
                    continue;
                }

                $conn = $clients[$connId]['conn'] ?? null;
                if ($conn) {
                    $conn->send(json_encode([
                        'event'   => 'pdv:pagamento_pendente',
                        'payload' => json_decode($qrData, true),
                    ]));
                }
            }
        }
    });

    // ── Timer 4: Watchdog — fecha conexões sem heartbeat >90s (30s) ──────────
    Timer::add(30, function () use (&$clients) {
        $limite = time() - 90;
        foreach ($clients as $id => $c) {
            if ($c['auth'] && ($c['last_ping'] ?? 0) < $limite) {
                echo "[WATCHDOG] Encerrando conexão inativa #{$id}\n";
                $c['conn']->close();
            }
        }
    });

    // ── Timer 5: Atualiza pdvs.ultimo_ping no BD (30s) ────────────────────────
    Timer::add(30, function () use (&$pdvIndex) {
        foreach ($pdvIndex as $key => $_connId) {
            [$lojaId, $numeroPdv] = explode(':', $key, 2);
            try {
                getPdo()->prepare(
                    "UPDATE pdvs SET ultimo_ping = NOW() WHERE loja_id = ? AND numero_pdv = ?"
                )->execute([$lojaId, $numeroPdv]);
            } catch (\Throwable) {}
        }
    });
};

// ── Funções auxiliares ────────────────────────────────────────────────────────

/**
 * Autentica um PDV Electron.
 * Valida o token via cache Redis (60s) para evitar consulta ao BD a cada conexão.
 * $pdo e $redis são obtidos via getPdo()/getRedis() — sem dependência de closure.
 */
function _handlePdvAuth(
    TcpConnection $conn,
    array &$c,
    array $body,
    array &$clients,
    array &$pdvIndex,
    array &$adminIndex
): void {
    $token     = trim($body['token']     ?? '');
    $lojaId    = (int) ($body['loja_id'] ?? 1);
    $numeroPdv = trim($body['numero_pdv'] ?? '01');
    $usuarioId = (int) ($body['usuario_id'] ?? 0);
    $versao    = trim($body['versao']    ?? '');

    $cacheKey   = "pdvix:token_cache";
    $tokenSalvo = getRedis()->get($cacheKey);

    if (!$tokenSalvo) {
        $stmt = getPdo()->prepare("SELECT valor FROM config WHERE chave = 'api_token' LIMIT 1");
        $stmt->execute();
        $row        = $stmt->fetch(\PDO::FETCH_OBJ);
        $tokenSalvo = $row ? $row->valor : '';
        getRedis()->setex($cacheKey, 60, $tokenSalvo);
    }

    if (empty($token) || !hash_equals($tokenSalvo, $token)) {
        $conn->send(json_encode(['event' => 'ws:auth_fail', 'msg' => 'Token inválido.']));
        $conn->close();
        return;
    }

    // Remove conexão anterior do mesmo PDV, se houver
    $chave = "{$lojaId}:{$numeroPdv}";
    if (isset($pdvIndex[$chave])) {
        $oldId   = $pdvIndex[$chave];
        $oldConn = $clients[$oldId]['conn'] ?? null;
        if ($oldConn && $oldConn->id !== $conn->id) {
            $oldConn->close();
        }
    }

    $c['tipo']        = 'pdv';
    $c['loja_id']     = $lojaId;
    $c['numero_pdv']  = $numeroPdv;
    $c['usuario_id']  = $usuarioId;
    $c['auth']        = true;
    $c['last_ping']   = time();
    $pdvIndex[$chave] = $conn->id;

    _pdvSetOnline($lojaId, $numeroPdv, true, $versao);

    $conn->send(json_encode([
        'event' => 'ws:auth_ok',
        'canal' => "pdv:{$lojaId}:{$numeroPdv}",
    ]));

    _broadcastAdmin($clients, $adminIndex, $lojaId, json_encode([
        'event'      => 'admin:pdv_online',
        'loja_id'    => $lojaId,
        'numero_pdv' => $numeroPdv,
    ]));

    echo "[AUTH PDV] loja={$lojaId} pdv={$numeroPdv} conn=#{$conn->id}\n";
}

/**
 * Autentica um admin do painel.
 */
function _handleAdminAuth(
    TcpConnection $conn,
    array &$c,
    array $body,
    array &$adminIndex
): void {
    $sessaoId = trim($body['session_id'] ?? '');
    $lojaId   = !empty($body['loja_id']) ? (int) $body['loja_id'] : 'global';

    if (empty($sessaoId)) {
        $conn->send(json_encode(['event' => 'ws:auth_fail', 'msg' => 'Session inválida.']));
        return;
    }

    $c['tipo']       = 'admin';
    $c['loja_id']    = $lojaId === 'global' ? 0 : (int) $lojaId;
    $c['auth']       = true;
    $c['last_ping']  = time();
    $adminIndex[$conn->id] = $lojaId;

    $conn->send(json_encode(['event' => 'ws:auth_ok', 'tipo' => 'admin']));
    echo "[AUTH ADMIN] loja={$lojaId} conn=#{$conn->id}\n";
}

/**
 * Envia mensagem para todos os admins de uma loja (ou global).
 */
function _broadcastAdmin(array &$clients, array &$adminIndex, int $lojaId, string $msg): void
{
    foreach ($adminIndex as $connId => $lojaAdmin) {
        if ($lojaAdmin === 'global' || (int) $lojaAdmin === $lojaId) {
            $conn = $clients[$connId]['conn'] ?? null;
            if ($conn) $conn->send($msg);
        }
    }
}

/**
 * Atualiza o status online/offline do PDV na tabela pdvs.
 * Usa getPdo() internamente — sem receber PDO como parâmetro.
 */
function _pdvSetOnline(int $lojaId, string $numeroPdv, bool $online, string $versao = ''): void
{
    try {
        $sets   = ['online = :online', 'ultimo_ping = NOW()'];
        $params = [':online' => $online ? 1 : 0, ':loja_id' => $lojaId, ':numero_pdv' => $numeroPdv];

        if ($versao !== '') {
            $sets[]            = 'versao_app = :versao';
            $params[':versao'] = $versao;
        }

        getPdo()->prepare(
            "UPDATE pdvs SET " . implode(', ', $sets) . " WHERE loja_id = :loja_id AND numero_pdv = :numero_pdv"
        )->execute($params);
    } catch (\Throwable) {}
}

// ── Logs e PID ────────────────────────────────────────────────────────────────
Worker::$stdoutFile = defined('PATH_LOGS')
    ? rtrim(PATH_LOGS, '/\\') . '/pdvix_ws.log'
    : '/var/www/html/pdvix_ws.log';

Worker::$pidFile = '/tmp/pdvix_ws.pid';

Worker::runAll();