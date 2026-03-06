<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\RedisService;

/**
 * PdvController
 *
 * Gerencia status e comandos remotos para PDVs via Redis → WebSocket.
 *
 * Rotas:
 *   GET  /api/pdv/status           → status()   lista todos PDVs com online/offline
 *   POST /api/pdv/comando          → comando()  enfileira comando remoto no Redis
 *   POST /api/pdv/carga            → carga()    sinaliza carga disponível para um PDV
 */
class PdvController extends Controller
{
    private \PDO         $pdo;
    private RedisService $redis;

    /** Comandos aceitos pelo PDV Electron */
    private const COMANDOS_VALIDOS = [
        'reiniciar', 'desligar', 'fechar_caixa',
        'cancelar_item', 'cancelar_venda',
        'desconto_item', 'desconto_venda',
        'finalizar_venda', 'enviar_comanda', 'enviar_carga',
    ];

    public function __construct()
    {
        $this->pdo   = Database::getConnection();
        $this->redis = new RedisService();
    }

    // =========================================================================
    // GET /api/pdv/status
    // =========================================================================

    public function status(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        $lojaId = $this->getLojaIdSessao();
        $where  = '';
        $params = [];

        if ($lojaId !== null) {
            $where              = 'WHERE p.loja_id = :loja_id';
            $params[':loja_id'] = $lojaId;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.id, p.loja_id, p.numero_pdv, p.descricao, p.url_local,
                p.status, p.online, p.ultimo_ping, p.versao_app,
                l.nome AS loja_nome
            FROM pdvs p
            LEFT JOIN lojas l ON l.id = p.loja_id
            {$where}
            ORDER BY l.nome, p.numero_pdv
        ");
        $stmt->execute($params);
        $pdvs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->responseJson('success', $pdvs);
    }

    // =========================================================================
    // POST /api/pdv/comando
    // =========================================================================

    public function comando(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(
            ['loja_id', 'numero_pdv', 'tipo'],
            ['payload']
        );

        $lojaId    = (int)    $data['loja_id'];
        $numeroPdv = trim($data['numero_pdv']);
        $tipo      = $data['tipo'];
        $payload   = $data['payload'];

        if (!in_array($tipo, self::COMANDOS_VALIDOS, true)) {
            $this->responseJson('error', [], 'Tipo de comando inválido.', 400);
        }

        // Enfileira no Redis — o WebSocket Gateway consome e envia ao PDV
        $chave   = "pdv:cmd:{$lojaId}:{$numeroPdv}";
        $comando = json_encode([
            'tipo'       => $tipo,
            'payload'    => $payload,
            'origem'     => 'painel',
            'usuario_id' => (int) ($_SESSION['logado']->id ?? 0),
            'enviado_em' => date('Y-m-d H:i:s'),
        ]);

        $this->redis->rPush($chave, $comando);

        $this->responseJson('success', ['chave' => $chave], 'Comando enfileirado com sucesso.');
    }

    // =========================================================================
    // POST /api/pdv/carga
    // =========================================================================

    public function carga(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente']);

        $data = $this->getRequestData(['loja_id', 'numero_pdv']);

        $lojaId    = (int)  $data['loja_id'];
        $numeroPdv = trim($data['numero_pdv']);

        // Sinaliza via Redis — WS enviará evento pdv:carga_disponivel ao PDV
        $chave = "pdv:cmd:{$lojaId}:{$numeroPdv}";
        $this->redis->rPush($chave, json_encode(['tipo' => 'enviar_carga', 'origem' => 'painel']));

        $this->responseJson('success', [], 'Sinal de carga disponível enviado.');
    }
}
