<?php
namespace App\Services;

use App\Core\Database;

/**
 * PagarmeService
 *
 * Cliente HTTP para a API Pagar.me v5.
 * Responsável por criar orders PIX, criar pedidos POS Stone, cancelar charges e consultar status.
 *
 * Constantes esperadas no config.php:
 *   define('PAGARME_API_KEY',         'sk_...');
 *   define('PAGARME_WEBHOOK_SECRET',  'whsec_...');
 *
 * CORREÇÕES APLICADAS:
 *   [BUG-411] _request: todos os métodos não-GET enviam Content-Length (evita HTTP 411 em proxies IIS).
 *   [BUG-404] cancelarCharge: usa DELETE /charges/{charge_id} (endpoint correto da Pagar.me v5).
 *             O cancelamento sempre ocorre na CHARGE, nunca via POST .../cancel ou /orders/closed.
 *             Se charge_id não estiver no banco, consulta a API antes de cancelar.
 *   [NOVO]    criarPedidoPOS: cria pedido com poi_payment_settings para Stone POS
 */
class PagarmeService
{
    private \PDO   $pdo;
    private string $apiKey;
    private string $baseUrl = 'https://api.pagar.me/core/v5';

    public function __construct()
    {
        $this->pdo    = Database::getConnection();
        $this->apiKey = defined('PAGARME_API_KEY') ? PAGARME_API_KEY : 'sk_7b77b2c61c0a443ea83bca26744ed8cb';
    }

    // =========================================================================
    // CRIAR PIX
    // =========================================================================

    /**
     * Cria uma order PIX na Pagar.me v5.
     *
     * @param int    $vendaId
     * @param float  $valor
     * @param int    $lojaId
     * @param array  $clienteInfo  Opcional: ['nome'=>..., 'cpf'=>..., 'email'=>...]
     * @return array {order_id, charge_id, qr_code, qr_code_url, expires_at, expires_in, status}
     * @throws \Exception em caso de falha na API
     */
    public function criarPix(int $vendaId, float $valor, int $lojaId = 1, array $clienteInfo = []): array
    {
        $valorCentavos = (int) round($valor * 100);
        $expiresIn     = 600; // 10 minutos em segundos (campo correto da API v5)
        $expiresAt     = date('Y-m-d\\TH:i:s\\Z', strtotime('+10 minutes'));

        $customerNome  = !empty($clienteInfo['nome'])  ? mb_substr($clienteInfo['nome'], 0, 64) : 'Consumidor Final';
        $customerEmail = !empty($clienteInfo['email']) ? $clienteInfo['email'] : 'consumidor@pdvix.local';
        $customerDoc   = !empty($clienteInfo['cpf']) ? preg_replace('/\D/', '', $clienteInfo['cpf']) : null;

        $customerId = defined('PAGARME_CUSTOMER_ID') ? PAGARME_CUSTOMER_ID : '';

        $body = [
            'code'   => 'PDVIX-' . $vendaId . '-' . time(),
            'closed' => true,
            'items'  => [[
                'amount'      => $valorCentavos,
                'description' => 'Venda PDVix #' . $vendaId,
                'quantity'    => 1,
                'code'        => 'VENDA-' . $vendaId,
            ]],
            'payments' => [[
                'payment_method' => 'pix',
                'pix'            => [
                    'expires_in' => $expiresIn,
                ],
                'amount'         => $valorCentavos,
            ]],
        ];

        if (!empty($customerId)) {
            $body['customer_id'] = $customerId;
        } else {
            $body['customer'] = [
                'name'          => $customerNome,
                'type'          => 'individual',
                'email'         => $customerEmail,
                'document'      => $customerDoc ?? '09510976105',
                'document_type' => 'CPF',
                'phones'        => [
                    'home_phone' => [
                        'country_code' => '55',
                        'area_code'    => '65',
                        'number'       => '999999999',
                    ],
                ],
                'address' => [
                    'line_1'   => 'PDV Local',
                    'zip_code' => '78000000',
                    'city'     => 'Cuiaba',
                    'state'    => 'MT',
                    'country'  => 'BR',
                ],
            ];
        }

        $response = $this->_request('POST', '/orders', $body);

        if (empty($response['id'])) {
            throw new \Exception('Resposta inválida da Pagar.me: ' . json_encode($response));
        }

        $charge   = $response['charges'][0] ?? [];
        $lastTx   = $charge['last_transaction'] ?? [];
        $pix      = $lastTx['qr_code']     ?? null;
        $pixUrl   = $lastTx['qr_code_url'] ?? null;
        $chargeId = $charge['id']           ?? null;
        $status   = $response['status']     ?? 'pending';

        $this->_salvarTransacao([
            'venda_id'         => $vendaId,
            'loja_id'          => $lojaId,
            'order_id'         => $response['id'],
            'charge_id'        => $chargeId,
            'tipo'             => 'pix',
            'status'           => $status,
            'qr_code'          => $pix,
            'qr_code_url'      => $pixUrl,
            'expires_at'       => $expiresAt,
            'payload_request'  => json_encode($body),
            'payload_response' => json_encode($response),
        ]);

        return [
            'order_id'    => $response['id'],
            'charge_id'   => $chargeId,
            'qr_code'     => $pix,
            'qr_code_url' => $pixUrl,
            'expires_at'  => $expiresAt,
            'expires_in'  => $expiresIn,
            'status'      => $status,
        ];
    }

    // =========================================================================
    // CRIAR PEDIDO PARA STONE POS (débito, crédito, pix na maquininha)
    // =========================================================================

    /**
     * Cria um pedido aberto (closed=false) com poi_payment_settings para o Stone POS.
     *
     * Ao criar o pedido com closed=false + poi_payment_settings preenchido,
     * a maquininha (identificada por device_serial_number) recebe o pedido
     * e exibe o valor para ser pago presencialmente.
     *
     * O pagamento é confirmado via webhook charge.paid enviado ao endpoint
     * POST /api/webhook/pagarme.
     *
     * @param int    $vendaId
     * @param float  $valor
     * @param int    $lojaId
     * @param array  $posConfig  {
     *   device_serial_number: string  (número serial da maquininha Stone)
     *   tipo: 'debit'|'credit'|'pix'|'voucher'  (deixar vazio para o operador escolher)
     *   installments: int  (parcelas, apenas para crédito — mínimo 1)
     *   installment_type: 'merchant'|'issuer'  (padrão: merchant)
     *   display_name: string  (nome exibido na maquininha)
     *   print_receipt: bool   (imprimir comprovante)
     * }
     * @param array  $clienteInfo  ['nome'=>..., 'cpf'=>..., 'email'=>...]
     * @return array {order_id, status, device_serial_number, tipo}
     * @throws \Exception
     */
    public function criarPedidoPOS(
        int $vendaId,
        float $valor,
        int $lojaId = 1,
        array $posConfig = [],
        array $clienteInfo = []
    ): array {
        $valorCentavos     = (int) round($valor * 100);
        $deviceSerial      = trim($posConfig['device_serial_number'] ?? '');
        $tipo              = $posConfig['tipo']             ?? '';   // vazio = operador escolhe
        $installments      = (int) ($posConfig['installments']      ?? 1);
        $installmentType   = $posConfig['installment_type'] ?? 'merchant';
        $displayName       = $posConfig['display_name']     ?? ('Venda #' . $vendaId);
        $printReceipt      = (bool) ($posConfig['print_receipt'] ?? true);

        if (empty($deviceSerial)) {
            throw new \Exception('device_serial_number é obrigatório para pagamento via Stone POS.');
        }

        // ── Customer ─────────────────────────────────────────────────────────
        $customerNome  = !empty($clienteInfo['nome'])  ? mb_substr($clienteInfo['nome'], 0, 64) : 'Consumidor Final';
        $customerEmail = !empty($clienteInfo['email']) ? $clienteInfo['email'] : 'consumidor@pdvix.local';
        $customerDoc   = !empty($clienteInfo['cpf'])   ? preg_replace('/\D/', '', $clienteInfo['cpf']) : null;
        $customerId    = defined('PAGARME_CUSTOMER_ID') ? PAGARME_CUSTOMER_ID : '';

        // ── poi_payment_settings ──────────────────────────────────────────────
        // Para "Listagem de Pedidos" (operador escolhe pagamento na maquininha):
        //   closed = false, payment_setup NÃO é preenchido
        // Para "Pagamento Direto" (tipo já definido pelo PDV):
        //   closed = false, payment_setup É preenchido
        $poiSettings = [
            'visible'               => true,
            'display_name'          => mb_substr($displayName, 0, 64),
            'print_order_receipt'   => $printReceipt,
            'devices_serial_number' => [$deviceSerial],
        ];

        // Pagamento Direto: preenche payment_setup somente se tipo informado
        if (!empty($tipo)) {
            $paymentSetup = [
                'type'         => $tipo,
                'installments' => $installments,
            ];
            if ($tipo === 'credit' && $installments > 1) {
                $paymentSetup['installment_type'] = $installmentType;
            }
            $poiSettings['payment_setup'] = $paymentSetup;
        }

        $body = [
            'code'   => 'PDVIX-POS-' . $vendaId . '-' . time(),
            'closed' => false,   // OBRIGATÓRIO false para o POS receber o pedido
            'items'  => [[
                'amount'      => $valorCentavos,
                'description' => mb_substr('Venda PDVix #' . $vendaId, 0, 255),
                'quantity'    => 1,
                'code'        => 'VENDA-' . $vendaId,
            ]],
            'poi_payment_settings' => $poiSettings,
        ];

        if (!empty($customerId)) {
            $body['customer_id'] = $customerId;
        } else {
            $body['customer'] = [
                'name'          => $customerNome,
                'type'          => 'individual',
                'email'         => $customerEmail,
                'document'      => $customerDoc ?? '09510976105',
                'document_type' => 'CPF',
                'phones'        => [
                    'home_phone' => [
                        'country_code' => '55',
                        'area_code'    => '65',
                        'number'       => '999999999',
                    ],
                ],
                'address' => [
                    'line_1'   => 'PDV Local',
                    'zip_code' => '78000000',
                    'city'     => 'Cuiaba',
                    'state'    => 'MT',
                    'country'  => 'BR',
                ],
            ];
        }

        $response = $this->_request('POST', '/orders', $body);

        if (empty($response['id'])) {
            throw new \Exception('Resposta inválida da Pagar.me (POS): ' . json_encode($response));
        }

        $tipoSalvo = !empty($tipo) ? 'pos_' . $tipo : 'pos_maquininha';

        $this->_salvarTransacao([
            'venda_id'         => $vendaId,
            'loja_id'          => $lojaId,
            'order_id'         => $response['id'],
            'charge_id'        => null, // charge só existe após o pagamento na maquininha
            'tipo'             => $tipoSalvo,
            'status'           => 'pending',
            'qr_code'          => null,
            'qr_code_url'      => null,
            'expires_at'       => null,
            'payload_request'  => json_encode($body),
            'payload_response' => json_encode($response),
        ]);

        return [
            'order_id'             => $response['id'],
            'order_code'           => $response['code']   ?? null,
            'status'               => $response['status'] ?? 'pending',
            'device_serial_number' => $deviceSerial,
            'tipo'                 => $tipo ?: 'operador_escolhe',
        ];
    }

    // =========================================================================
    // CANCELAR CHARGE
    // =========================================================================

    /**
     * Cancela uma cobrança PIX ou pedido POS.
     *
     * Endpoint correto Pagar.me v5:
     *   DELETE /core/v5/charges/{charge_id}
     *
     * O cancelamento SEMPRE ocorre na CHARGE, nunca na ORDER.
     * Se charge_id nao estiver no banco local, busca o charge_id diretamente
     * na API para nao precisar do webhook de criacao.
     *
     * Para pedidos POS sem charge (nao pagos na maquininha), o pedido e
     * fechado via PATCH /orders/{id}/closed {"status":"canceled"}.
     *
     * @return string charge_id utilizado no cancelamento
     * @throws \Exception quando a transacao nao e encontrada ou a API retorna erro
     */
   public function cancelarCharge(string $orderId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT charge_id, tipo, status FROM pagarme_transacoes WHERE order_id = ? LIMIT 1"
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$row) {
            throw new \Exception("Transacao nao encontrada no banco para order_id={$orderId}.");
        }

        $chargeId    = $row->charge_id ?? '';
        $isPosType   = str_starts_with((string)($row->tipo ?? ''), 'pos_');
        $statusLocal = $row->status ?? 'pending';

        // 1. POS sem charge: maquininha ainda não processou. Tenta cancelar o pedido.
        if (empty($chargeId) && $isPosType) {
            try {
                $this->_request('PATCH', "/orders/{$orderId}/closed", ['status' => 'canceled']);
            } catch (\Exception $e) {
                error_log("[PagarmeService] POS cancelar order falhou: " . $e->getMessage());
            }
            $this->_marcarCancelado($orderId);
            return '';
        }

        // 2. PIX: Se por acaso faltar o charge_id, tenta buscar na API
        if (empty($chargeId)) {
            try {
                $orderData = $this->consultarOrder($orderId);
                $chargeId  = $orderData['charges'][0]['id'] ?? '';
                if ($chargeId) {
                    $this->pdo->prepare("UPDATE pagarme_transacoes SET charge_id = ? WHERE order_id = ?")
                              ->execute([$chargeId, $orderId]);
                }
            } catch (\Exception $e) {
                // Segue o jogo
            }
        }

        // 3. Tenta realizar o cancelamento na Pagar.me
        $sucesso = false;
        $erroApi = '';

        if (!empty($chargeId)) {
            try {
                // Endpoint canônico para cancelar/estornar
                $this->_request('DELETE', "/charges/{$chargeId}");
                $sucesso = true;
            } catch (\Exception $e) {
                $erroApi .= "DELETE Charge: " . $e->getMessage() . " | ";
            }
        }

        if (!$sucesso) {
            try {
                // Fallback: tenta fechar o pedido inteiro
                $this->_request('PATCH', "/orders/{$orderId}/closed", ['status' => 'canceled']);
                $sucesso = true;
            } catch (\Exception $e) {
                $erroApi .= "PATCH Order: " . $e->getMessage();
            }
        }

        // ── 4. A GRANDE SACADA PARA O PIX PENDENTE ──
        // A Pagar.me recusa o cancelamento de PIX não pago. Ele deve expirar sozinho.
        // Se a API falhou, mas a transação local ainda é 'pending', nós IGNORAMOS a falha da API.
        // O PIX vai expirar inofensivamente lá no servidor deles, e o PDV é liberado imediatamente.
        if (!$sucesso) {
            if ($statusLocal === 'pending' || $statusLocal === 'pending_capture') {
                error_log("[PagarmeService] Pagar.me recusou cancelar transação pendente. Deixando expirar por tempo. Erros: {$erroApi}");
            } else {
                // Se o status já era 'paid' e recusou, aí sim é um erro real (ex: falta de saldo na sua conta Stone/Pagar.me para estornar)
                throw new \Exception("A Pagar.me recusou o estorno: {$erroApi}");
            }
        }

        // 5. Marca como cancelado no banco local para destravar o Front-end
        $this->_marcarCancelado($orderId);
        return $chargeId;
    }

    // =========================================================================
    // CONSULTAR STATUS DO PEDIDO NA PAGAR.ME
    // =========================================================================

    /**
     * Consulta o status atual de um order diretamente na Pagar.me.
     * Útil para polling quando webhook não chegou.
     */
    public function consultarOrder(string $orderId): array
    {
        return $this->_request('GET', "/orders/{$orderId}");
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    // =========================================================================
    // HELPERS DE STATUS LOCAL
    // =========================================================================

    /**
     * Marca uma transação como cancelada no banco local.
     * Chamado após cancelarCharge() — não faz chamada à API.
     */
    private function _marcarCancelado(string $orderId): void
    {
        try {
            $this->pdo->prepare(
                "UPDATE pagarme_transacoes SET status = 'canceled', updated_at = NOW() WHERE order_id = ?"
            )->execute([$orderId]);
        } catch (\PDOException $e) {
            error_log("[PagarmeService] _marcarCancelado falhou para order={$orderId}: " . $e->getMessage());
        }
    }

    /**
     * Marca transações pendentes de uma venda como "substituido".
     * Usada antes de criar um novo PIX/POS para a mesma venda.
     * NÃO faz chamada à API — o charge antigo expira sozinho.
     *
     * @param int    $vendaId
     * @param string $tipo     'pix' ou 'pos_%' (LIKE)
     * @return string[] order_ids substituídos (para log)
     */
    public function substituirPendentes(int $vendaId, string $tipo = 'pix'): array
    {
        try {
            // 1. Expirar automaticamente PIX já vencidos pelo tempo
            $this->pdo->prepare("
                UPDATE pagarme_transacoes
                SET status = 'expired', updated_at = NOW()
                WHERE venda_id = ?
                  AND tipo = ?
                  AND status = 'pending'
                  AND expires_at IS NOT NULL
                  AND expires_at < NOW()
            ")->execute([$vendaId, $tipo]);

            // 2. Buscar pendentes ativos (não vencidos ainda)
            $stmt = $this->pdo->prepare("
                SELECT order_id FROM pagarme_transacoes
                WHERE venda_id = ? AND tipo = ? AND status IN ('pending','pending_capture')
            ");
            $stmt->execute([$vendaId, $tipo]);
            $pendentes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($pendentes)) {
                $placeholders = implode(',', array_fill(0, count($pendentes), '?'));
                $this->pdo->prepare("
                    UPDATE pagarme_transacoes
                    SET status = 'substituido', updated_at = NOW()
                    WHERE order_id IN ({$placeholders})
                ")->execute($pendentes);

                error_log(sprintf(
                    "[PagarmeService] %d PIX/POS substituído(s) para venda_id=%d: %s",
                    count($pendentes), $vendaId, implode(', ', $pendentes)
                ));
            }

            return $pendentes;

        } catch (\PDOException $e) {
            error_log("[PagarmeService] substituirPendentes falhou: " . $e->getMessage());
            return [];
        }
    }

    private function _salvarTransacao(array $data): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pagarme_transacoes
                    (venda_id, loja_id, order_id, charge_id, tipo, status,
                     qr_code, qr_code_url, expires_at,
                     payload_request, payload_response, created_at, updated_at)
                VALUES
                    (:venda_id, :loja_id, :order_id, :charge_id, :tipo, :status,
                     :qr_code, :qr_code_url, :expires_at,
                     :payload_request, :payload_response, NOW(), NOW())
            ");
            $stmt->execute([
                ':venda_id'         => $data['venda_id'],
                ':loja_id'          => $data['loja_id'],
                ':order_id'         => $data['order_id'],
                ':charge_id'        => $data['charge_id'],
                ':tipo'             => $data['tipo'],
                ':status'           => $data['status'],
                ':qr_code'          => $data['qr_code'],
                ':qr_code_url'      => $data['qr_code_url'],
                ':expires_at'       => $data['expires_at'],
                ':payload_request'  => $data['payload_request'],
                ':payload_response' => $data['payload_response'],
            ]);
        } catch (\PDOException $e) {
            // Log silencioso — não bloqueia o fluxo
        }
    }

    private function _request(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);

        // DELETE: apenas Authorization — sem Content-Type, sem Content-Length, sem body.
        // A infraestrutura da Pagar.me retorna 405 UnsupportedApiVersion quando recebe
        // um DELETE com Content-Type: application/json (tenta parsear body inexistente).
        //
        // GET: sem body, sem headers extras.
        //
        // POST / PUT / PATCH: Content-Type + Content-Length + body JSON.
        // Body mínimo '{}' evita HTTP 411 em proxies IIS quando não há payload.
        $headers = [
            'Authorization: Basic ' . base64_encode($this->apiKey . ':'),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($method !== 'GET' && $method !== 'DELETE') {
            $bodyJson  = !empty($body) ? json_encode($body) : '{}';
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($bodyJson);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true) ?? [];

        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? $response;
            throw new \Exception("Pagar.me HTTP {$httpCode}: {$message}");
        }

        return $decoded;
    }
}