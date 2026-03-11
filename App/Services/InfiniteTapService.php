<?php
namespace App\Services;

use App\Core\Database;
use App\Models\PagamentoModel;

/**
 * InfiniteTapService
 *
 * Gera deeplinks para o app InfinitePay (Tap to Pay no celular do operador),
 * processa o retorno do webhook e notifica o PDV via WebSocket (Redis/Workerman).
 *
 * Fluxo:
 *   1. PDV Electron chama POST /api/pdv/tap/criar
 *   2. Backend gera deeplink + salva order em `infinitetap_orders`
 *   3. Electron exibe QR do deeplink → operador escaneia com o celular
 *   4. InfinitePay processa o Tap to Pay e chama result_url (webhook)
 *   5. Webhook atualiza status + publica evento WebSocket
 *   6. PDV Electron recebe pdv:pagamento_confirmado / pdv:pagamento_cancelado
 */
class InfiniteTapService
{
    // ── Deeplink base do InfinitePay ──────────────────────────────────────────
    private const DEEPLINK_BASE = 'infinitepaydash://infinitetap-app';

    // ── Valor mínimo: 100 centavos = R$ 1,00 ─────────────────────────────────
    private const VALOR_MINIMO_CENTAVOS = 100;

    // ── Máximo de parcelas ────────────────────────────────────────────────────
    private const MAX_PARCELAS = 12;

    private \PDO           $pdo;
    private PagamentoModel $pagamentoModel;

    public function __construct()
    {
        $this->pdo            = Database::getConnection();
        $this->pagamentoModel = new PagamentoModel();
    }

    // =========================================================================
    // CRIAR TRANSAÇÃO
    // =========================================================================

    /**
     * Cria um registro local e retorna o deeplink para o app InfinitePay.
     *
     * @param array $data {
     *   venda_id        int     — ID da venda no servidor
     *   valor           float   — Valor em reais (ex: 49.90)
     *   payment_method  string  — 'credit' | 'debit'
     *   installments    int     — Parcelas (somente crédito; padrão 1)
     *   numero_venda    string  — Número da venda (para roteamento WS)
     *   loja_id         int
     *   numero_pdv      string
     *   doc_number      string  — CNPJ/CPF do merchant (sem pontuação, opcional)
     *   handle          string  — Handle do merchant no InfinitePay (opcional)
     *   app_referrer    string  — Identificador do app (padrão: PDVix)
     * }
     * @return array { order_id, deeplink_url, valor_centavos }
     * @throws \InvalidArgumentException
     */
    public function criarTransacao(array $data): array
    {
        $this->validarDadosTransacao($data);

        $orderId       = $this->gerarOrderId($data['venda_id'], $data['numero_venda'] ?? '');
        $valorCentavos = (int) round((float) $data['valor'] * 100);
        $installments  = (int) ($data['installments'] ?? 1);
        $method        = $data['payment_method'];
        $handle        = $data['handle']       ?? '';
        $docNumber     = $data['doc_number']   ?? '';
        $appReferrer   = $data['app_referrer'] ?? 'PDVix';

        // result_url: webhook no servidor (não é um deeplink do Electron)
        $resultUrl = $this->buildResultUrl($orderId);

        // Salva no banco antes de retornar o deeplink
        $this->salvarOrder([
            'order_id'       => $orderId,
            'venda_id'       => (int) $data['venda_id'],
            'loja_id'        => (int) ($data['loja_id'] ?? 1),
            'numero_pdv'     => $data['numero_pdv'] ?? '01',
            'numero_venda'   => $data['numero_venda'] ?? '',
            'valor'          => (float) $data['valor'],
            'valor_centavos' => $valorCentavos,
            'payment_method' => $method,
            'installments'   => $installments,
            'handle'         => $handle,
            'doc_number'     => $docNumber,
            'result_url'     => $resultUrl,
            'status'         => 'pendente',
        ]);

        $deeplink = $this->buildDeeplink([
            'amount'              => $valorCentavos,
            'payment_method'      => $method,
            'installments'        => $installments,
            'order_id'            => $orderId,
            'result_url'          => $resultUrl,
            'app_client_referrer' => $appReferrer,
            'handle'              => $handle,
            'doc_number'          => $docNumber,
            'af_force_deeplink'   => 'true',   // necessário no iOS
        ]);

        return [
            'order_id'       => $orderId,
            'deeplink_url'   => $deeplink,
            'valor_centavos' => $valorCentavos,
        ];
    }

    // =========================================================================
    // PROCESSAR WEBHOOK (result_url callback do InfinitePay)
    // =========================================================================

    /**
     * Processa o retorno do InfinitePay após a transação Tap to Pay.
     * Atualiza o status no banco e publica evento no WebSocket (Redis).
     *
     * @param array $params  Query-string recebida no result_url
     * @return array { sucesso, status, mensagem, order_id }
     */
    public function processarWebhook(array $params): array
    {
        $orderId = $params['order_id'] ?? '';
        if (!$orderId) {
            return ['sucesso' => false, 'status' => 'erro', 'mensagem' => 'order_id ausente.'];
        }

        $order = $this->findOrderById($orderId);
        if (!$order) {
            return ['sucesso' => false, 'status' => 'erro', 'mensagem' => 'Pedido não encontrado.'];
        }

        // Se já processado, retorna sem duplicar
        if (in_array($order->status, ['confirmado', 'cancelado'], true)) {
            return ['sucesso' => true, 'status' => $order->status, 'mensagem' => 'Já processado.', 'order_id' => $orderId];
        }

        $temWarning = !empty($params['warning']);
        $status     = $temWarning ? 'cancelado' : 'confirmado';

        // Atualiza order
        $this->atualizarOrder($orderId, [
            'status'            => $status,
            'nsu'               => $params['nsu']               ?? null,
            'aut'               => $params['aut']               ?? null,
            'card_brand'        => $params['card_brand']        ?? null,
            'infinitepay_user_id'   => $params['user_id']       ?? null,
            'infinitepay_access_id' => $params['access_id']     ?? null,
            'merchant_document' => $params['merchant_document'] ?? null,
            'warning'           => $params['warning']           ?? null,
        ]);

        // Registra pagamento confirmado na tabela de pagamentos da venda
        if ($status === 'confirmado') {
            $tipoPagamento = $order->payment_method === 'debit' ? 'pos_debito' : 'pos_credito';
            $this->pagamentoModel->createPagamento([
                'venda_id'           => $order->venda_id,
                'tipo_pagamento'     => $tipoPagamento,
                'valor'              => $order->valor,
                'referencia_externa' => $params['nsu'] ?? $orderId,
                'descricao'          => 'InfiniteTap ' . strtoupper($order->payment_method)
                                        . ' — NSU: ' . ($params['nsu'] ?? '-'),
                'status'             => 'confirmado',
            ]);
        }

        // Publica evento WebSocket para o PDV Electron
        $this->publicarEventoWs($order, $status, $params);

        return [
            'sucesso'  => true,
            'status'   => $status,
            'mensagem' => $status === 'confirmado' ? 'Pagamento confirmado.' : 'Transação cancelada ou com erro.',
            'order_id' => $orderId,
        ];
    }

    // =========================================================================
    // STATUS DE UMA ORDER
    // =========================================================================

    public function statusOrder(string $orderId): ?object
    {
        return $this->findOrderById($orderId);
    }

    // =========================================================================
    // CANCELAR (antes de o operador pagar)
    // =========================================================================

    /**
     * Cancela uma order que ainda esteja pendente.
     * Não existe API de cancelamento no InfinitePay (o operador simplesmente
     * fecha o app), mas precisamos atualizar o estado local.
     */
    public function cancelarOrder(string $orderId): bool
    {
        $order = $this->findOrderById($orderId);
        if (!$order || $order->status !== 'pendente') return false;

        $stmt = $this->pdo->prepare(
            "UPDATE infinitetap_orders SET status = 'cancelado', updated_at = NOW() WHERE order_id = ?"
        );
        return $stmt->execute([$orderId]);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function validarDadosTransacao(array $data): void
    {
        foreach (['venda_id', 'valor', 'payment_method'] as $campo) {
            if (empty($data[$campo])) {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$campo}.");
            }
        }

        $valor         = (float) $data['valor'];
        $valorCentavos = (int) round($valor * 100);

        if ($valorCentavos < self::VALOR_MINIMO_CENTAVOS) {
            throw new \InvalidArgumentException(
                'Valor mínimo para InfiniteTap é R$ 1,00 (100 centavos).'
            );
        }

        $methodsValidos = ['credit', 'debit'];
        if (!in_array($data['payment_method'], $methodsValidos, true)) {
            throw new \InvalidArgumentException(
                'payment_method inválido. Use: credit | debit.'
            );
        }

        $installments = (int) ($data['installments'] ?? 1);
        if ($installments < 1 || $installments > self::MAX_PARCELAS) {
            throw new \InvalidArgumentException(
                'installments deve ser entre 1 e ' . self::MAX_PARCELAS . '.'
            );
        }

        // Cada parcela deve ser >= R$ 1,00
        if ($data['payment_method'] === 'credit' && $installments > 1) {
            $porParcela = $valorCentavos / $installments;
            if ($porParcela < self::VALOR_MINIMO_CENTAVOS) {
                throw new \InvalidArgumentException(
                    "Cada parcela deve ser de pelo menos R$ 1,00. "
                    . "Com {$installments}x, o mínimo é R$ " . number_format($installments / 100, 2, ',', '.') . "."
                );
            }
        }
    }

    /**
     * Gera um order_id único legível (não UUID para facilitar debug).
     * Formato: PDV-{venda_id}-{numero_venda}-{timestamp}
     */
    private function gerarOrderId(int $vendaId, string $numeroVenda): string
    {
        $ts    = time();
        $sufixo = substr(md5($vendaId . $numeroVenda . $ts), 0, 6);
        return "PDV-{$vendaId}-{$ts}-{$sufixo}";
    }

    /**
     * Monta a result_url que o InfinitePay irá chamar após a transação.
     * É sempre uma URL do servidor back-end (não um deeplink do Electron).
     */
    private function buildResultUrl(string $orderId): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? $this->getAppUrl(), '/');
        return $base . '/api/webhook/infinitetap?order_id=' . urlencode($orderId);
    }

    private function getAppUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}";
    }

    private function buildDeeplink(array $params): string
    {
        // Remove chaves vazias para URL limpa
        $filtrado = array_filter($params, fn($v) => $v !== '' && $v !== null);
        return self::DEEPLINK_BASE . '?' . http_build_query($filtrado);
    }

    // ── Banco de dados ────────────────────────────────────────────────────────

    private function salvarOrder(array $d): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO infinitetap_orders
                (order_id, venda_id, loja_id, numero_pdv, numero_venda,
                 valor, valor_centavos, payment_method, installments,
                 handle, doc_number, result_url, status, created_at, updated_at)
            VALUES
                (:order_id, :venda_id, :loja_id, :numero_pdv, :numero_venda,
                 :valor, :valor_centavos, :payment_method, :installments,
                 :handle, :doc_number, :result_url, :status, NOW(), NOW())
        ");
        $stmt->execute([
            ':order_id'       => $d['order_id'],
            ':venda_id'       => $d['venda_id'],
            ':loja_id'        => $d['loja_id'],
            ':numero_pdv'     => $d['numero_pdv'],
            ':numero_venda'   => $d['numero_venda'],
            ':valor'          => $d['valor'],
            ':valor_centavos' => $d['valor_centavos'],
            ':payment_method' => $d['payment_method'],
            ':installments'   => $d['installments'],
            ':handle'         => $d['handle'],
            ':doc_number'     => $d['doc_number'],
            ':result_url'     => $d['result_url'],
            ':status'         => $d['status'],
        ]);
    }

    private function atualizarOrder(string $orderId, array $campos): void
    {
        $campos['updated_at'] = date('Y-m-d H:i:s');
        $setParts = [];
        foreach ($campos as $col => $val) {
            $setParts[] = "`{$col}` = :{$col}";
        }
        $campos[':order_id'] = $orderId;

        $stmt = $this->pdo->prepare(
            "UPDATE infinitetap_orders SET " . implode(', ', $setParts) . " WHERE order_id = :order_id"
        );
        // rebind com prefixo ":"
        $bind = [':order_id' => $orderId];
        foreach ($campos as $col => $val) {
            if ($col !== ':order_id') $bind[":{$col}"] = $val;
        }
        $stmt->execute($bind);
    }

    private function findOrderById(string $orderId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM infinitetap_orders WHERE order_id = ? LIMIT 1"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    // ── WebSocket (Redis pub/sub via Workerman) ───────────────────────────────

    /**
     * Publica evento no canal Redis do PDV para notificar o Electron.
     * Segue exatamente o padrão já usado pelo PagarmeService/webhook.
     */
    private function publicarEventoWs(object $order, string $status, array $params): void
    {
        try {
            $redis = new \Redis();
            $redis->connect(
                $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                (int) ($_ENV['REDIS_PORT'] ?? 6379)
            );

            // Evento no mesmo canal já escutado pelo main.js do Electron
            $eventName = $status === 'confirmado'
                ? 'pdv:pagamento_confirmado'
                : 'pdv:pagamento_cancelado';

            $payload = [
                'event'   => $eventName,
                'payload' => [
                    'gateway'      => 'infinitetap',
                    'order_id'     => $order->order_id,
                    'venda_id'     => $order->venda_id,
                    'numero_venda' => $order->numero_venda,
                    'loja_id'      => $order->loja_id,
                    'numero_pdv'   => $order->numero_pdv,
                    'valor'        => $order->valor,
                    'status'       => $status,
                    'nsu'          => $params['nsu']       ?? null,
                    'aut'          => $params['aut']       ?? null,
                    'card_brand'   => $params['card_brand'] ?? null,
                    'warning'      => $params['warning']   ?? null,
                ],
            ];

            // Canal específico do PDV: pdv:{loja_id}:{numero_pdv}
            $canal = "pdv:{$order->loja_id}:{$order->numero_pdv}";
            $redis->publish($canal, json_encode($payload));

        } catch (\Exception $e) {
            // Falha no Redis não deve quebrar o webhook — loga e segue
            error_log('[InfiniteTapService] Redis publish falhou: ' . $e->getMessage());
        }
    }
}