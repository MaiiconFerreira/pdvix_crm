<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\InfiniteTapService;

/**
 * InfiniteTapController
 *
 * Rotas mapeadas em routes.php:
 *
 *   POST /api/pdv/tap/criar       → criarTapPdv()     — PDV Electron (token)
 *   POST /api/pdv/tap/cancelar    → cancelarTapPdv()  — PDV Electron (token)
 *   GET  /api/pdv/tap/status      → statusTapPdv()    — PDV Electron (token)
 *
 *   GET  /api/webhook/infinitetap → webhookInfinitetap() — callback do app InfinitePay
 *                                                          (result_url; sem autenticação)
 */
class InfiniteTapController extends Controller
{
    private InfiniteTapService $tapService;

    public function __construct()
    {
        $this->tapService = new InfiniteTapService();
    }

    // =========================================================================
    // POST /api/pdv/tap/criar?token=XXX
    // =========================================================================

    /**
     * Chamado pelo PDV Electron para iniciar uma transação Tap to Pay.
     *
     * Body JSON:
     *   venda_id        int     — ID da venda no servidor
     *   valor           float   — Ex: 49.90
     *   payment_method  string  — 'credit' | 'debit'
     *   installments    int     — Parcelas (crédito; padrão 1)
     *   numero_venda    string  — Para roteamento WebSocket
     *   loja_id         int
     *   numero_pdv      string
     *   handle          string  — (opcional) Handle do merchant no InfinitePay
     *   doc_number      string  — (opcional) CNPJ/CPF sem pontuação
     *
     * Resposta:
     *   { status:'success', data:{ order_id, deeplink_url, valor_centavos } }
     */
    public function criarTapPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->ensureTokenAuth();

        $data = $this->getRequestData(
            ['venda_id', 'valor', 'payment_method'],
            ['installments', 'numero_venda', 'loja_id', 'numero_pdv', 'handle', 'doc_number', 'app_referrer']
        );

        try {
            $result = $this->tapService->criarTransacao($data);
        } catch (\InvalidArgumentException $e) {
            $this->responseJson('error', [], $e->getMessage(), 422);
        }

        $this->responseJson('success', $result, 'Transação InfiniteTap criada. Apresente o QR Code ao operador.');
    }

    // =========================================================================
    // POST /api/pdv/tap/cancelar?token=XXX
    // =========================================================================

    /**
     * Cancela uma order pendente (operador desistiu antes de apresentar o Tap).
     *
     * Body JSON: { order_id }
     */
    public function cancelarTapPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('POST');
        $this->ensureTokenAuth();

        $data    = $this->getRequestData(['order_id']);
        $orderId = trim($data['order_id']);

        $ok = $this->tapService->cancelarOrder($orderId);
        $this->responseJson(
            $ok ? 'success' : 'error',
            [],
            $ok ? 'Order cancelada.' : 'Order não encontrada ou já processada.'
        );
    }

    // =========================================================================
    // GET /api/pdv/tap/status?token=XXX&order_id=XXX
    // =========================================================================

    /**
     * Consulta o status de uma order InfiniteTap.
     * O PDV pode fazer polling enquanto espera o webhook (fallback).
     */
    public function statusTapPdv(): void
    {
        $this->jsonHeader();
        $this->ensureMethod('GET');
        $this->ensureTokenAuth();

        $orderId = trim($_GET['order_id'] ?? '');
        if (!$orderId) {
            $this->responseJson('error', [], 'order_id é obrigatório.', 422);
        }

        $order = $this->tapService->statusOrder($orderId);
        if (!$order) {
            $this->responseJson('error', [], 'Order não encontrada.', 404);
        }

        $this->responseJson('success', (array) $order, 'Status consultado.');
    }

    // =========================================================================
    // GET /api/webhook/infinitetap?order_id=XXX&nsu=XXX&aut=XXX&...
    // =========================================================================

    /**
     * Callback do app InfinitePay após a transação Tap to Pay.
     *
     * O InfinitePay abre a result_url via GET com os parâmetros de resultado.
     * Não requer autenticação — autenticado pelo order_id gerado internamente.
     *
     * Parâmetros de sucesso esperados:
     *   order_id, nsu, aut, card_brand, user_id, access_id, handle, merchant_document
     *
     * Parâmetros de erro:
     *   + warning (ex: "order_id is empty")
     */
    public function webhookInfinitetap(): void
    {
        // Aceita GET (padrão InfinitePay) e POST
        $this->jsonHeader();

        $params = array_merge($_GET, $_POST);

        $result = $this->tapService->processarWebhook($params);

        // InfinitePay não usa o body da resposta, mas retornamos JSON para debug
        $httpStatus = $result['sucesso'] ? 200 : 422;
        $this->responseJson(
            $result['sucesso'] ? 'success' : 'error',
            ['status' => $result['status'], 'order_id' => $result['order_id'] ?? null],
            $result['mensagem'],
            $httpStatus
        );
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Valida autenticação via token (mesmo padrão dos outros endpoints PDV).
     * Lê o token da query-string: ?token=XXX
     */
    private function ensureTokenAuth(): void
    {
        $token    = $_GET['token'] ?? '';
        $expected = $this->getConfigToken();

        if (!$token || !hash_equals($expected, $token)) {
            $this->responseJson('error', [], 'Token inválido ou ausente.', 401);
        }
    }

    private function getConfigToken(): string
    {
        // Recupera o token da tabela de config (mesma fonte usada pelos outros controllers)
        try {
            $pdo  = \App\Core\Database::getConnection();
            $stmt = $pdo->prepare("SELECT valor FROM config WHERE chave = 'api_token' LIMIT 1");
            $stmt->execute();
            return (string) ($stmt->fetchColumn() ?: '');
        } catch (\Exception) {
            return '';
        }
    }
}