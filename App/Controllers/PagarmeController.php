<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\PagarmeService;
use App\Services\RedisService;

/**
 * PagarmeController
 *
 * CORREÇÕES APLICADAS:
 *   [BUG-2a] webhook: event names corrigidos — charge.paid / charge.payment_failed / charge.canceled
 *   [BUG-2b] webhook: extração do order_id corrigida — $data['order']['id'] (não $data['id'])
 *   [BUG-2c] webhook: header HMAC corrigido — HTTP_X_PAGARME_SIGNATURE (não HTTP_X_HUB_SIGNATURE)
 *   [BUG-2d] webhook: após cancelamento direto, notifica PDV via Redis
 *   [NOVO]   Stone POS: criarPosPdv(), cancelarPosPdv(), statusPosPdv()
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
    // PIX — PAINEL (sessão)
    // =========================================================================

    public function criarPix(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);
        $this->_processarCriarPix();
    }

    public function cancelarPix(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('POST');
        $this->requirePerfil(['administrador', 'gerente', 'operador']);
        $this->_processarCancelarPix();
    }

    public function statusPix(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->_processarStatusPix();
    }

    // =========================================================================
    // PIX — PDV ELECTRON (token)
    // =========================================================================

    public function criarPixPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();
        $this->_processarCriarPix();
    }

    public function cancelarPixPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();
        $this->_processarCancelarPix();
    }

    public function statusPixPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');
        $this->validarTokenPdv();
        $this->_processarStatusPix();
    }

    // =========================================================================
    // STONE POS — PDV ELECTRON (token)
    // =========================================================================

    /**
     * POST /api/pdv/pos/criar?token=XXX
     *
     * Body esperado:
     * {
     *   "venda_id": 123,
     *   "valor": 49.90,
     *   "numero_venda": "LOJA1-PDV01-20250101-000001",
     *   "device_serial_number": "6N027946",
     *   "tipo": "debit",               (opcional — vazio = operador escolhe)
     *   "installments": 1,             (opcional)
     *   "installment_type": "merchant",(opcional)
     *   "display_name": "Mesa 3",      (opcional)
     *   "print_receipt": true,         (opcional)
     *   "loja_id": 1,                  (opcional)
     *   "numero_pdv": "01",            (opcional)
     *   "cliente_nome": "",            (opcional)
     *   "cliente_cpf": "",             (opcional)
     *   "cliente_email": ""            (opcional)
     * }
     */
    public function criarPosPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();
        $this->_processarCriarPos();
    }

    /**
     * POST /api/pdv/pos/cancelar?token=XXX
     * Body: { "order_id": "or_xxx" }
     */
    public function cancelarPosPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->validarTokenPdv();
        $this->_processarCancelarPix(); // reutiliza — lógica idêntica
    }

    /**
     * GET /api/pdv/pos/status?token=XXX&order_id=or_xxx
     */
    public function statusPosPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');
        $this->validarTokenPdv();
        $this->_processarStatusPix(); // reutiliza — lógica idêntica
    }

    // =========================================================================
    // WEBHOOK — POST /api/webhook/pagarme
    // =========================================================================

    /**
     * [BUG-2a] Tipos corrigidos: charge.paid / charge.payment_failed / charge.canceled
     * [BUG-2b] orderId agora vem de $data['order']['id'] (não $data['id'] que é o charge_id)
     * [BUG-2c] Header HMAC corrigido: HTTP_X_PAGARME_SIGNATURE
     */
    public function webhook(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');

        $rawBody = file_get_contents('php://input');

        // ── Valida assinatura HMAC ────────────────────────────────────────────
        // [BUG-2c] Header correto da Pagar.me: x-pagarme-signature
        // PHP converte para HTTP_X_PAGARME_SIGNATURE
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

        $tipo = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];

        // ── [BUG-2b] Extração correta do order_id ────────────────────────────
        // O $data['id'] é o charge_id (ex: "ch_NRPl6mouLuZ123FR4")
        // O order_id está em $data['order']['id'] (ex: "or_bqopZVqtEtr123F45")
        $chargeId = $data['id']              ?? '';
        $orderId  = $data['order']['id']     ?? $data['order_id'] ?? '';

        // ── [BUG-2a] Tipos de evento corrigidos ──────────────────────────────
        // charge.paid           → pagamento confirmado
        // charge.payment_failed → falha na transação
        // charge.canceled       → charge cancelada
        // charge.pending        → autorizada, aguardando captura (cartão)
        $eventosRelevantes = [
            'charge.paid',
            'charge.payment_failed',
            'charge.canceled',
            'charge.pending',   // cartão: authorized_pending_capture
        ];

        if (!in_array($tipo, $eventosRelevantes, true)) {
            http_response_code(200);
            echo json_encode(['ok' => true, 'ignored' => $tipo]);
            exit;
        }

        $novoStatus = match ($tipo) {
            'charge.paid'            => 'paid',
            'charge.pending'         => 'pending_capture',
            'charge.payment_failed'  => 'failed',
            'charge.canceled'        => 'canceled',
            default                  => null,
        };

        // ── Atualiza banco usando order_id como chave primária ────────────────
        if ($novoStatus && $orderId) {
            // Lê status anterior ANTES de atualizar (necessário para o guard de substituido)
            $stmtAnterior = $this->pdo->prepare(
                "SELECT status FROM pagarme_transacoes WHERE order_id = ? LIMIT 1"
            );
            $stmtAnterior->execute([$orderId]);
            $txAnterior     = $stmtAnterior->fetch(\PDO::FETCH_OBJ);
            $foiSubstituido = $txAnterior && $txAnterior->status === 'substituido';

            // Atualiza a transação Pagar.me
            $stmt = $this->pdo->prepare("
                UPDATE pagarme_transacoes
                SET status = :status,
                    charge_id = COALESCE(NULLIF(:charge_id,''), charge_id),
                    payload_webhook = :webhook,
                    updated_at = NOW()
                WHERE order_id = :order_id
            ");
            $stmt->execute([
                ':status'    => $novoStatus,
                ':charge_id' => $chargeId,
                ':webhook'   => $rawBody,
                ':order_id'  => $orderId,
            ]);

            // ── Ações por status ──────────────────────────────────────────────
            if ($novoStatus === 'paid') {
                $this->_confirmarPagamento($orderId, $chargeId, $data, $foiSubstituido);
            } elseif (in_array($novoStatus, ['failed', 'canceled'], true)) {
                $this->_notificarFalha($orderId, $novoStatus);
            } elseif ($novoStatus === 'pending_capture') {
                // Cartão: autorizado mas aguardando captura
                // Notifica PDV como "pendente de captura" — o pagamento está aprovado pelo portador
                $this->_notificarPendenteCaptura($orderId, $chargeId, $data);
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
        $lojaId      = !empty($data['loja_id'])    ? (int)  $data['loja_id']   : 1;
        $numeroPdv   = !empty($data['numero_pdv']) ? trim($data['numero_pdv']) : '01';
        $numeroVenda = trim($data['numero_venda'] ?? '');

        $clienteInfo = [
            'nome'  => $data['cliente_nome']  ?? '',
            'cpf'   => $data['cliente_cpf']   ?? '',
            'email' => $data['cliente_email'] ?? '',
        ];

        if ($valor <= 0) {
            $this->responseJson('error', [], 'Valor inválido.', 400);
        }

        // ── Substituição automática de PIX pendentes ──────────────────────────
        // A Pagar.me não permite cancelar um PIX não-pago (cancelar = estornar).
        // Estratégia: marcamos os PIX pendentes como 'substituido' localmente.
        // O QR code antigo expira sozinho em até 10 min na Pagar.me (inofensivo).
        // O webhook guard em _confirmarPagamento() trata o caso raro de alguém
        // pagar o QR antigo: se a venda já estiver quitada → auto-estorno.
        $substituidos = $this->pagarmeService->substituirPendentes($vendaId, 'pix');
        if (!empty($substituidos)) {
            error_log(sprintf(
                "[PagarmeController] PIX substituídos antes de nova criação — venda_id=%d, orders=[%s]",
                $vendaId, implode(',', $substituidos)
            ));
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
                600
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

    private function _processarCriarPos(): void
    {
        $data = $this->getRequestData(
            ['venda_id', 'valor', 'device_serial_number'],
            [
                'loja_id', 'numero_pdv', 'numero_venda',
                'tipo', 'installments', 'installment_type',
                'display_name', 'print_receipt',
                'cliente_nome', 'cliente_cpf', 'cliente_email',
            ]
        );

        $vendaId     = (int)   $data['venda_id'];
        $valor       = (float) $data['valor'];
        $lojaId      = !empty($data['loja_id'])    ? (int)  $data['loja_id']   : 1;
        $numeroPdv   = !empty($data['numero_pdv']) ? trim($data['numero_pdv']) : '01';
        $numeroVenda = trim($data['numero_venda'] ?? '');

        if ($valor <= 0) {
            $this->responseJson('error', [], 'Valor inválido.', 400);
        }

        $posConfig = [
            'device_serial_number' => trim($data['device_serial_number']),
            'tipo'                 => trim($data['tipo']             ?? ''),
            'installments'         => (int) ($data['installments']   ?? 1),
            'installment_type'     => $data['installment_type']      ?? 'merchant',
            'display_name'         => $data['display_name']          ?? ('Venda #' . $vendaId),
            'print_receipt'        => !empty($data['print_receipt']),
        ];

        $clienteInfo = [
            'nome'  => $data['cliente_nome']  ?? '',
            'cpf'   => $data['cliente_cpf']   ?? '',
            'email' => $data['cliente_email'] ?? '',
        ];

        // ── Substituição automática de POS pendentes ──────────────────────────
        // Mesma lógica do PIX: pedido POS não pago é substituído localmente.
        // A maquininha descarta pedidos substituídos quando outro chega.
        $stmt = $this->pdo->prepare("
            SELECT order_id FROM pagarme_transacoes
            WHERE venda_id = ? AND tipo LIKE 'pos_%' AND status IN ('pending','pending_capture')
        ");
        $stmt->execute([$vendaId]);
        $posPendentes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($posPendentes)) {
            $placeholders = implode(',', array_fill(0, count($posPendentes), '?'));
            $this->pdo->prepare("
                UPDATE pagarme_transacoes
                SET status = 'substituido', updated_at = NOW()
                WHERE order_id IN ({$placeholders})
            ")->execute($posPendentes);
            error_log(sprintf(
                "[PagarmeController] POS substituídos antes de nova criação — venda_id=%d, orders=[%s]",
                $vendaId, implode(',', $posPendentes)
            ));
        }

        try {
            $result = $this->pagarmeService->criarPedidoPOS($vendaId, $valor, $lojaId, $posConfig, $clienteInfo);
        } catch (\Exception $e) {
            $this->responseJson('error', [], 'Erro ao criar pedido POS: ' . $e->getMessage(), 502);
        }

        // Registra no Redis: quando a maquininha processar, o webhook notifica o PDV
        if (!empty($numeroVenda)) {
            $this->redis->set(
                "pdv:venda_pdv:{$numeroVenda}",
                "{$lojaId}:{$numeroPdv}",
                1800  // TTL 30 min (transações POS podem demorar mais)
            );
        }

        $this->responseJson('success', $result, 'Pedido enviado para a maquininha.');
    }

    private function _processarCancelarPix(): void
    {
        $data    = $this->getRequestData(['order_id']);
        $orderId = trim($data['order_id']);

        if (empty($orderId)) {
            $this->responseJson('error', [], 'order_id é obrigatório.', 400);
        }

        // Busca transação: charge_id (para validação/log) + numero_venda (para Redis)
        $stmt = $this->pdo->prepare("
            SELECT pt.venda_id, pt.charge_id, pt.status AS tx_status, v.numero_venda
            FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE pt.order_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $transacao = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$transacao) {
            $this->responseJson('error', [], 'Transação não encontrada.', 404);
        }

        if ($transacao->tx_status === 'canceled') {
            $this->responseJson('error', [], 'Esta cobrança já foi cancelada.', 409);
        }

        error_log("[PagarmeController] cancelarPix: order={$orderId} charge=" . ($transacao->charge_id ?: 'pendente'));

        try {
            $chargeId = $this->pagarmeService->cancelarCharge($orderId);
        } catch (\Exception $e) {
            error_log("[PagarmeController] cancelarPix ERRO: " . $e->getMessage());
            $this->responseJson('error', [
                'order_id'  => $orderId,
                'charge_id' => $transacao->charge_id ?? null,
                'details'   => $e->getMessage(),
            ], 'Erro ao cancelar Pix', 502);
        }

        // Limpa Redis e notifica PDV do cancelamento
        if (!empty($transacao->numero_venda)) {
            $this->redis->del("pdv:venda_pdv:{$transacao->numero_venda}");
            $this->redis->rPush('pdv:fila_pagamentos', json_encode([
                'numero_venda' => $transacao->numero_venda,
                'order_id'     => $orderId,
                'charge_id'    => $chargeId ?? $transacao->charge_id,
                'status'       => 'canceled',
                'mensagem'     => 'Cobrança cancelada pelo operador.',
            ]));
        }

        error_log("[PagarmeController] cancelarPix OK: order={$orderId} charge={$chargeId}");
        $this->responseJson('success', [
            'order_id'  => $orderId,
            'charge_id' => $chargeId ?? $transacao->charge_id,
        ], 'Pix cancelado com sucesso');
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

    /**
     * [BUG-2c] Corrigido: Pagar.me envia x-pagarme-signature
     * PHP converte hifens em underscores e adiciona prefixo HTTP_
     * Header final: HTTP_X_PAGARME_SIGNATURE
     *
     * Formato da assinatura Pagar.me:
     * "t=<timestamp>,v1=<hmac_hex>"
     * onde hmac = HMAC-SHA256(timestamp + "." + body, secret)
     */
    private function _validarHmac(string $rawBody): bool
    {
        $secretKey = defined('PAGARME_WEBHOOK_SECRET') ? PAGARME_WEBHOOK_SECRET : '';

        // Se não há secret configurado, aceita em dev mas loga aviso
        if (empty($secretKey) || $secretKey === 'whsec_COLOQUE_SEU_SECRET_AQUI') {
            error_log('[WEBHOOK] PAGARME_WEBHOOK_SECRET não configurado — validação HMAC ignorada em dev.');
            return true; // REMOVA ISSO EM PRODUÇÃO
        }

        // [BUG-2c] Header correto da Pagar.me
        $header = $_SERVER['HTTP_X_PAGARME_SIGNATURE'] ?? '';
        if (empty($header)) return false;

        // Parse: "t=1234567890,v1=abc123..."
        $parts     = [];
        $timestamp = '';
        $v1        = '';

        foreach (explode(',', $header) as $part) {
            [$k, $v] = explode('=', $part, 2) + ['', ''];
            if ($k === 't')  $timestamp = $v;
            if ($k === 'v1') $v1        = $v;
        }

        if (empty($timestamp) || empty($v1)) {
            // Fallback para formato simples sha1/sha256 (legado)
            [$algo, $recebida] = explode('=', $header, 2) + ['sha1', ''];
            $esperada = hash_hmac($algo, $rawBody, $secretKey);
            return hash_equals($esperada, $recebida);
        }

        // Formato padrão Pagar.me v5: HMAC-SHA256 do payload "timestamp.body"
        $payload  = $timestamp . '.' . $rawBody;
        $esperada = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($esperada, $v1);
    }

    /**
     * Confirma o pagamento após charge.paid do webhook.
     * Atualiza pagamentos_venda e notifica o PDV via Redis/WebSocket.
     *
     * @param bool $foiSubstituido  true quando o order_id estava marcado como
     *                              'substituido' antes do webhook chegar.
     *                              Ativa o guard de duplo-pagamento.
     */
    private function _confirmarPagamento(string $orderId, string $chargeId, array $data, bool $foiSubstituido = false): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pt.venda_id, pt.charge_id, pt.tipo, v.numero_venda, v.status AS venda_status
            FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE pt.order_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $transacao = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$transacao) return;

        // ── Guard: PIX/POS substituído que foi pago ───────────────────────────
        // Cenário: operador pediu novo QR, o PIX antigo foi marcado 'substituido',
        // mas o cliente escaneou e pagou antes de expirar.
        if ($foiSubstituido) {
            if ($transacao->venda_status === 'finalizada') {
                // Venda já quitada por outro pagamento → estorno automático
                error_log(sprintf(
                    "[WEBHOOK] ⚠ PIX substituído pago após venda já finalizada. Auto-estorno. " .
                    "order_id=%s venda_id=%d", $orderId, $transacao->venda_id
                ));
                try {
                    // Como o charge está 'paid', a API aceita o estorno agora
                    $this->pagarmeService->cancelarCharge($orderId);
                } catch (\Exception $e) {
                    // Se o estorno falhar (ex: saldo insuficiente na conta Stone),
                    // loga como ALERTA CRÍTICO para o gestor resolver manualmente.
                    error_log(sprintf(
                        "[WEBHOOK] 🚨 ALERTA CRÍTICO — auto-estorno falhou! " .
                        "order_id=%s venda_id=%d erro=%s",
                        $orderId, $transacao->venda_id, $e->getMessage()
                    ));
                    // Notifica PDV/painel para ação manual
                    $this->redis->rPush('pdv:alertas_criticos', json_encode([
                        'tipo'       => 'estorno_necessario',
                        'order_id'   => $orderId,
                        'venda_id'   => $transacao->venda_id,
                        'numero_venda' => $transacao->numero_venda,
                        'motivo'     => 'PIX substituído foi pago após venda finalizada. Estorno automático falhou.',
                        'criado_em'  => date('Y-m-d H:i:s'),
                    ]));
                }
                return; // Não processar como pagamento válido
            }

            // Venda ainda não finalizada → o cliente pagou o QR antigo,
            // mas o dinheiro chegou. Aceitar e processar normalmente.
            error_log(sprintf(
                "[WEBHOOK] PIX substituído pago, venda ainda aberta — aceitando pagamento. " .
                "order_id=%s venda_id=%d", $orderId, $transacao->venda_id
            ));
        }

        // Determina tipo de pagamento para gravar no pagamentos_venda
        $tipoPgto  = $this->_resolverTipoPagamento($transacao->tipo ?? '', $data);

        // Confirma pagamento na tabela pagamentos_venda
        $this->pdo->prepare("
            UPDATE pagamentos_venda
            SET status = 'confirmado', updated_at = NOW()
            WHERE referencia_externa = ? AND status = 'pendente'
        ")->execute([$orderId]);

        // Notifica PDV via Redis → WebSocket
        $this->redis->rPush('pdv:fila_pagamentos', json_encode([
            'numero_venda'         => $transacao->numero_venda,
            'order_id'             => $orderId,
            'charge_id'            => $chargeId ?: $transacao->charge_id,
            'status'               => 'confirmado',
            'tipo_pagamento'       => $tipoPgto,
            'valor'                => ($data['paid_amount'] ?? $data['amount'] ?? 0) / 100,
            'bandeira'             => $metadata['scheme_name']          ?? null,
            'portador'             => $metadata['account_holder_name']  ?? null,
            'authorization_code'   => $metadata['authorization_code']   ?? null,
            'installments'         => $metadata['installment_quantity']  ?? null,
            'terminal_serial'      => $metadata['terminal_serial_number'] ?? null,
            'confirmado_em'        => date('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Notifica PDV de falha ou cancelamento de pagamento.
     */
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

        $mensagem = $status === 'failed'
            ? 'Pagamento não aprovado. Tente novamente.'
            : 'Pagamento cancelado.';

        $this->redis->rPush('pdv:fila_pagamentos', json_encode([
            'numero_venda' => $transacao->numero_venda,
            'order_id'     => $orderId,
            'status'       => $status,
            'mensagem'     => $mensagem,
        ]));
    }

    /**
     * Notifica PDV que o cartão foi autorizado mas aguarda captura (POS crédito).
     * Para o operador do PDV, isso é equivalente a "aprovado" — a captura é automática.
     */
    private function _notificarPendenteCaptura(string $orderId, string $chargeId, array $data): void
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

        $metadata = $data['last_transaction']['metadata'] ?? $data['metadata'] ?? [];

        $this->redis->rPush('pdv:fila_pagamentos', json_encode([
            'numero_venda'       => $transacao->numero_venda,
            'order_id'           => $orderId,
            'charge_id'          => $chargeId,
            'status'             => 'confirmado',  // para o PDV, cartão autorizado = confirmado
            'tipo_pagamento'     => $this->_resolverTipoPagamento('', $data),
            'valor'              => ($data['amount'] ?? 0) / 100,
            'bandeira'           => $metadata['scheme_name']         ?? null,
            'portador'           => $metadata['account_holder_name'] ?? null,
            'authorization_code' => $metadata['authorization_code']  ?? null,
            'installments'       => $metadata['installment_quantity'] ?? null,
            'terminal_serial'    => $metadata['terminal_serial_number'] ?? null,
            'confirmado_em'      => date('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Resolve o tipo de pagamento (pos_debit, pos_credit, pix, etc.)
     * a partir do tipo salvo na transação e do payload do webhook.
     */
    private function _resolverTipoPagamento(string $tipoSalvo, array $data): string
    {
        $method  = $data['payment_method']              ?? '';
        $funding = $data['metadata']['account_funding_source']
                ?? $data['last_transaction']['funding_source']
                ?? '';

        if ($method === 'pix') return 'pos_pix';
        if ($method === 'credit_card' || $funding === 'credit')  return 'pos_credito';
        if ($method === 'debit_card'  || $funding === 'debit')   return 'pos_debito';
        if ($method === 'voucher')                               return 'convenio';

        // Fallback: usa tipo salvo na transação
        if (str_contains($tipoSalvo, 'credit')) return 'pos_credito';
        if (str_contains($tipoSalvo, 'debit'))  return 'pos_debito';
        if (str_contains($tipoSalvo, 'pix'))    return 'pos_pix';

        return 'outros';
    }

    // =========================================================================
    // PAINEL — LISTAGEM DE TRANSAÇÕES
    // =========================================================================

    public function listarTransacoes(): void
    {
        $this->jsonHeader();
        $this->ensureSession();
        $this->ensureMethod('GET');
        $this->requirePerfil(['administrador', 'gerente']);

        // Parâmetros do DataTables (paginação básica)
        $start  = isset($_GET['start']) ? (int) $_GET['start'] : 0;
        $length = isset($_GET['length']) ? (int) $_GET['length'] : 25;
        $search = $_GET['search']['value'] ?? '';

        $where = "1=1";
        $params = [];

        if (!empty($search)) {
            $where .= " AND (pt.order_id LIKE :search OR v.numero_venda LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        // Total de registros
        $stmtTotal = $this->pdo->query("SELECT COUNT(*) FROM pagarme_transacoes");
        $recordsTotal = (int) $stmtTotal->fetchColumn();

        // Total filtrado
        $stmtFiltered = $this->pdo->prepare("
            SELECT COUNT(*) FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE {$where}
        ");
        $stmtFiltered->execute($params);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        // Busca os dados
        $sql = "
            SELECT 
                pt.id, pt.order_id, pt.charge_id, pt.tipo, pt.status, pt.created_at,
                v.numero_venda
            FROM pagarme_transacoes pt
            LEFT JOIN vendas v ON v.id = pt.venda_id
            WHERE {$where}
            ORDER BY pt.created_at DESC
            LIMIT :start, :length
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':start', $start, \PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, \PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'draw'            => isset($_GET['draw']) ? (int) $_GET['draw'] : 1,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ]);
    }
}