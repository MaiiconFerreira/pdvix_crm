<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\PagarmeService;
use App\Services\RedisService;

/**
 * PagarmeController
 *
 * Integração com Pagar.me v5 para PIX e Stone POS.
 *
 * Rotas (sessão PHP — painel admin):
 *   POST /api/pagamentos/pix/criar    → criarPix()
 *   POST /api/pagamentos/pix/cancelar → cancelarPix()
 *   GET  /api/pagamentos/pix/status   → statusPix()
 *   POST /api/webhook/pagarme         → webhook()
 *
 * Rotas (token PDV — sem sessão PHP):
 *   POST /api/pdv/pix/criar           → criarPixPdv()
 *   POST /api/pdv/pix/cancelar        → cancelarPixPdv()
 *   GET  /api/pdv/pix/status          → statusPixPdv()
 */
class PagarmeController extends Controller
{
    private \PDO           $pdo;
    private PagarmeService $pagarmeService;
    private RedisService   $redis;

    public function __construct()
    {
        $this->pdo            = Database::getConnection();
        $this->pagarmeService = new PagarmeService();
        $this->redis          = new RedisService();
    }

    // =========================================================================
    // POST /api/pagamentos/pix/criar  (sessão — painel)
    // =========================================================================

    public function criarPix(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $this->_processarCriarPix();
    }

    // =========================================================================
    // POST /api/pdv/pix/criar  (token — PDV Electron)
    // =========================================================================

    public function criarPixPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();

        $this->_processarCriarPix();
    }

    // =========================================================================
    // POST /api/pagamentos/pix/cancelar  (sessão — painel)
    // =========================================================================

    public function cancelarPix(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);

        $this->_processarCancelarPix();
    }

    // =========================================================================
    // POST /api/pdv/pix/cancelar  (token — PDV Electron)
    // =========================================================================

    public function cancelarPixPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();

        $this->_processarCancelarPix();
    }

    // =========================================================================
    // GET /api/pagamentos/pix/status?order_id=X  (sessão)
    // =========================================================================

    public function statusPix(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');

        $this->_processarStatusPix();
    }

    // =========================================================================
    // GET /api/pdv/pix/status?order_id=X  (token — PDV Electron)
    // =========================================================================

    public function statusPixPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');
        $this->validarTokenPdv();

        $this->_processarStatusPix();
    }

    // =========================================================================
    // POST /api/webhook/pagarme
    // =========================================================================

    public function webhook(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');

        $rawBody = file_get_contents('php://input');

        // ── Valida assinatura HMAC ────────────────────────────────────────────
        if (!$this->_validarHmac($rawBody)) {
            http_response_code(401);
            echo json_encode(['error' => 'Assinatura inválida.']);
            exit;
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload inválido.']);
            exit;
        }

        $tipo    = $payload['type']   ?? '';
        $data    = $payload['data']   ?? [];
        $orderId = $data['id']        ?? '';

        // Apenas eventos relevantes
        if (!in_array($tipo, ['order.paid', 'order.payment_failed', 'order.canceled'], true)) {
            http_response_code(200);
            echo json_encode(['ok' => true]);
            exit;
        }

        $novoStatus = match ($tipo) {
            'order.paid'           => 'paid',
            'order.payment_failed' => 'failed',
            'order.canceled'       => 'canceled',
            default                => null,
        };

        if ($novoStatus && $orderId) {
            $stmt = $this->pdo->prepare("
                UPDATE pagarme_transacoes
                SET status = :status, payload_webhook = :webhook, updated_at = NOW()
                WHERE order_id = :order_id
            ");
            $stmt->execute([
                ':status'   => $novoStatus,
                ':webhook'  => $rawBody,
                ':order_id' => $orderId,
            ]);

            if ($novoStatus === 'paid') {
                $this->_confirmarPagamento($orderId);
            } elseif (in_array($novoStatus, ['failed', 'canceled'])) {
                $this->_notificarFalha($orderId, $novoStatus);
            }
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
    }

    // =========================================================================
    // IMPLEMENTAÇÃO COMPARTILHADA
    // =========================================================================

    private function _processarCriarPix(): void
    {
        $data = $this->getRequestData(
            ['venda_id', 'valor'],
            ['loja_id', 'numero_pdv', 'numero_venda', 'cliente_nome', 'cliente_cpf', 'cliente_email']
        );

        $vendaId     = (int)   $data['venda_id'];
        $valor       = (float) $data['valor'];
        $lojaId      = !empty($data['loja_id'])     ? (int)  $data['loja_id']   : 1;
        $numeroPdv   = !empty($data['numero_pdv'])  ? trim($data['numero_pdv']) : '01';
        $numeroVenda = trim($data['numero_venda'] ?? '');

        $clienteInfo = [
            'nome'  => $data['cliente_nome']  ?? '',
            'cpf'   => $data['cliente_cpf']   ?? '',
            'email' => $data['cliente_email'] ?? '',
        ];

        if ($valor <= 0) {
            $this->responseJson('error', [], 'Valor inválido.', 400);
        }

        // Verifica se já existe PIX pendente para esta venda
        $stmt = $this->pdo->prepare("
            SELECT id FROM pagarme_transacoes
            WHERE venda_id = ? AND tipo = 'pix' AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$vendaId]);
        if ($stmt->fetch()) {
            $this->responseJson('error', [], 'Já existe um PIX pendente para esta venda. Cancele antes de criar outro.', 409);
        }

        try {
            $result = $this->pagarmeService->criarPix($vendaId, $valor, $lojaId, $clienteInfo);
        } catch (\Exception $e) {
            $this->responseJson('error', [], 'Erro ao criar PIX: ' . $e->getMessage(), 502);
        }

        // Registra no Redis para que o WS entregue o QR Code ao PDV
        if (!empty($numeroVenda)) {
            $this->redis->set(
                "pdv:venda_pdv:{$numeroVenda}",
                "{$lojaId}:{$numeroPdv}",
                600  // TTL 10 minutos
            );
            $this->redis->rPush(
                "pdv:pagamento_pendente:{$numeroVenda}",
                json_encode([
                    'order_id'    => $result['order_id'],
                    'qr_code'     => $result['qr_code'],
                    'qr_code_url' => $result['qr_code_url'],
                    'expires_at'  => $result['expires_at'],
                    'valor'       => $valor,
                ])
            );
        }

        $this->responseJson('success', $result, 'PIX criado com sucesso.');
    }

    private function _processarCancelarPix(): void
    {
        $data    = $this->getRequestData(['order_id']);
        $orderId = $data['order_id'];

        try {
            $this->pagarmeService->cancelarCharge($orderId);
        } catch (\Exception $e) {
            $this->responseJson('error', [], 'Erro ao cancelar PIX: ' . $e->getMessage(), 502);
        }

        $this->responseJson('success', [], 'PIX cancelado com sucesso.');
    }

    private function _processarStatusPix(): void
    {
        $orderId = trim($_GET['order_id'] ?? '');
        if (empty($orderId)) {
            $this->responseJson('error', [], 'order_id é obrigatório.', 400);
        }

        $stmt = $this->pdo->prepare("
            SELECT pt.*, v.numero_venda
            FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE pt.order_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $transacao = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$transacao) {
            $this->responseJson('error', [], 'Transação não encontrada.', 404);
        }

        $this->responseJson('success', $transacao);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function _validarHmac(string $rawBody): bool
    {
        $secretKey = defined('PAGARME_WEBHOOK_SECRET') ? PAGARME_WEBHOOK_SECRET : '';
        $header    = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

        if (empty($secretKey) || empty($header)) return false;

        [$algo, $recebida] = explode('=', $header, 2) + ['sha1', ''];
        $esperada = hash_hmac($algo, $rawBody, $secretKey);

        return hash_equals($esperada, $recebida);
    }

    private function _confirmarPagamento(string $orderId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pt.venda_id, pt.charge_id, v.numero_venda
            FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE pt.order_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $transacao = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$transacao) return;

        // Confirma pagamento na tabela pagamentos_venda (se existir registro pendente)
        $this->pdo->prepare("
            UPDATE pagamentos_venda
            SET status = 'confirmado', updated_at = NOW()
            WHERE referencia_externa = ? AND status = 'pendente'
        ")->execute([$orderId]);

        // Enfileira no Redis para o WebSocket rotear ao PDV correto
        $this->redis->rPush('pdv:fila_pagamentos', json_encode([
            'numero_venda'  => $transacao->numero_venda,
            'order_id'      => $orderId,
            'charge_id'     => $transacao->charge_id,
            'status'        => 'confirmado',
            'confirmado_em' => date('Y-m-d H:i:s'),
        ]));
    }

    private function _notificarFalha(string $orderId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pt.venda_id, v.numero_venda
            FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE pt.order_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $transacao = $stmt->fetch(\PDO::FETCH_OBJ);
        if (!$transacao) return;

        // Notifica PDV da falha via Redis/WS
        $this->redis->rPush('pdv:fila_pagamentos', json_encode([
            'numero_venda' => $transacao->numero_venda,
            'order_id'     => $orderId,
            'status'       => $status,  // 'failed' | 'canceled'
            'mensagem'     => $status === 'failed' ? 'Pagamento PIX não aprovado.' : 'PIX cancelado.',
        ]));
    }
}
