<?php
/**
 * WebSocket Gateway - Monitoramento em Tempo Real
 * Stack: PHP puro + Workerman + Redis
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../config.php';

use Workerman\Worker;
use Workerman\Timer;

use App\Controllers\BiaController;
use App\Services\LocationIngestService;

// ========================
// CONFIGURAÇÕES
// ========================
date_default_timezone_set('America/Cuiaba');

$SSL_CONTEXT = [
    'ssl' => [
        'local_cert'  => '/etc/letsencrypt/live/sgd.idealsolucoes.online/fullchain.pem',
        'local_pk'    => '/etc/letsencrypt/live/sgd.idealsolucoes.online/privkey.pem',
        'verify_peer' => false,
    ]
];

// ========================
// WORKER
// ========================
$ws_worker = new Worker("websocket://0.0.0.0:8443", $SSL_CONTEXT);
$ws_worker->transport = 'ssl';
$ws_worker->count = 1;

// ========================
// DEPENDÊNCIAS
// ========================
$biaController     = new BiaController();
$locationService  = new LocationIngestService();

// ========================
// CONTROLE DE CONEXÕES
// ========================
$clients = [];

// Canais de broadcast
$channels = [
    'admin:monitoramento' => [],
];

// ========================
// EVENTOS DO WORKER
// ========================

// Conexão
$ws_worker->onConnect = function($connection) use (&$clients) {
    $clients[$connection->id] = $connection;

    echo "[CONNECT] Cliente {$connection->id}\n";

    $connection->send(json_encode([
        'event' => 'connection',
        'msg'   => 'Conexão WebSocket segura estabelecida'
    ]));
};

// Desconexão
$ws_worker->onClose = function($connection) use (&$clients, &$channels) {
    unset($clients[$connection->id]);

    foreach ($channels as $key => $conns) {
        unset($channels[$key][$connection->id]);
    }

    echo "[DISCONNECT] Cliente {$connection->id}\n";
};

// ========================
// MENSAGENS RECEBIDAS
// ========================
$ws_worker->onMessage = function($connection, $data) use (
    &$channels,
    $biaController,
    $locationService
) {
    $payload = json_decode($data, true);
    if (!$payload || empty($payload['event'])) return;

    switch ($payload['event']) {

        // ------------------------
        // SUBSCRIBE EM CANAIS
        // ------------------------
        case 'subscribe':
            $channel = $payload['channel'] ?? null;
            if (!$channel) return;

            if (!isset($channels[$channel])) {
                $channels[$channel] = [];
            }

            $channels[$channel][$connection->id] = $connection;

            echo "[SUBSCRIBE] {$connection->id} -> {$channel}\n";

            $connection->send(json_encode([
                'event'   => 'subscribed',
                'channel' => $channel
            ]));
        break;

        case 'list_conversations':
            print_r($payload);
            $biaController->listConversations($connection);
            break;

        case 'get_history':
            $biaController->getHistory($connection, $payload);
            break;

        // ------------------------
        // BIA
        // ------------------------
        case 'user_message':
            $biaController->processUserMessage(
                $connection,
                $payload['payload'] ?? []
            );
        break;

        // ------------------------
        // LOCALIZAÇÃO
        // ------------------------
        case 'location_update':

            print_r($payload['payload']);

            $resultado = $locationService->ingest(
                $payload['payload'] ?? []
            );

            print_r($resultado);

            if (!empty($channels['admin:monitoramento'])) {
                $msg = json_encode([
                    'event' => 'monitoramento_status',
                    'data'  => $resultado
                ]);

                foreach ($channels['admin:monitoramento'] as $conn) {
                    $conn->send($msg);
                }
            }
        break;
    }
};

// ========================
// WORKER START
// ========================
$ws_worker->onWorkerStart = function() use (&$clients) {

    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    echo "[REDIS] Conectado\n";

    // ========================
    // STATUS ORDERS (COM DELAY)
    // ========================
    $lastOrderTime = microtime(true) - 1;
    $delay = 1.0;

    Timer::add(0.1, function() use ($redis, &$clients, &$lastOrderTime, $delay) {
        $now = microtime(true);

        if ($now - $lastOrderTime >= $delay) {
            $msg = $redis->lpop('status_orders');
            if ($msg) {
                echo "[REDIS] status_orders\n";
                foreach ($clients as $conn) {
                    $conn->send($msg);
                }
                $lastOrderTime = $now;
            }
        }
    });

    // ========================
    // FILA PRESENÇAS
    // ========================
    Timer::add(0.1, function() use ($redis, &$clients) {
        $msg = $redis->lpop('fila_presencas');
        if ($msg) {
            echo "[REDIS] fila_presencas\n";
            foreach ($clients as $conn) {
                $conn->send($msg);
            }
        }
    });

    // ========================
    // ALERTAS DE MONITORAMENTO
    // ========================
    Timer::add(1, function() use ($redis) {
        $alerta = $redis->lpop('fila_alertas_monitoramento');
        if ($alerta) {
            echo "[ALERTA] {$alerta}\n";
        }
    });

    // ========================\r\n
      // STATUS DE MONITORAMENTO (BROADCAST PARA SCALAS.JS)\r\n
      // ========================\r\n
      Timer::add(0.1, function() use ($redis, &$clients) {
          // Consome a fila que o LocationIngestService preencheu
          $msg = $redis->lpop('fila_status_monitoramento');
          if ($msg) {
              echo "[REDIS] fila_status_monitoramento\\n";
              // Envia para todos os clientes
              foreach ($clients as $conn) {
                  $conn->send($msg);
              }
          }
      });

    // ========================
    // INFRA / SERVIÇOS
    // ========================
    Timer::add(5, function() use ($redis, &$clients) {
        $status = trim(shell_exec('systemctl is-active worker_payments'));
        if (!$status) $status = 'unknown';

        $payload = json_encode([
            'event' => 'monitoramento_status',
            'payload' => [
                'servico' => 'worker_payments',
                'status'  => $status
            ]
        ]);

        foreach ($clients as $conn) {
            $conn->send($payload);
        }

        $redis->setex('infra:worker_payments', 10, $status);
    });

    // Monitoramento do serviço de Transmissão (websocket_server)
    Timer::add(5, function() use ($redis, &$clients) {
        // Verifica se o serviço está ativo no Linux
        $status = trim(shell_exec('systemctl is-active websocket_server'));
        if (!$status) $status = 'unknown';

        $payload = json_encode([
            'event' => 'monitoramento_status',
            'payload' => [
                'servico' => 'websocket_server',
                'status'  => $status
            ]
        ]);

        // Envia o status para todos os administradores/clientes conectados
        foreach ($clients as $conn) {
            $conn->send($payload);
        }

        // Salva no Redis para consulta rápida se necessário
        $redis->setex('infra:websocket_server', 10, $status);
    });
};

// ========================
// LOGS
// ========================
Worker::$stdoutFile = '/var/www/html/workerman.log';
Worker::$pidFile    = '/tmp/websocket_server.pid';

// ========================
// START
// ========================
Worker::runAll();
