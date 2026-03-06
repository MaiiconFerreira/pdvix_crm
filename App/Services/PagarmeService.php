<?php
namespace App\Services;

use App\Core\Database;

/**
 * PagarmeService
 *
 * Cliente HTTP para a API Pagar.me v5.
 * Responsável por criar orders PIX, cancelar charges e consultar status.
 *
 * Constantes esperadas no config.php:
 *   define('PAGARME_API_KEY',         'sk_...');
 *   define('PAGARME_WEBHOOK_SECRET',  'whsec_...');
 */
class PagarmeService
{
    private \PDO   $pdo;
    private string $apiKey;
    private string $baseUrl = 'https://api.pagar.me/core/v5';

    public function __construct()
    {
        $this->pdo    = Database::getConnection();
        //$this->apiKey = defined('PAGARME_API_KEY') ? PAGARME_API_KEY : '';
        $this->apiKey = 'sk_7b77b2c61c0a443ea83bca26744ed8cb';
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

        // ── Customer ─────────────────────────────────────────────────────────
        // Pagar.me PSP exige customer ou customer_id em todo pedido.
        // Usa dados do cliente se disponíveis; caso contrário usa genérico.
        // Em produção, salve o customer_id na config (PAGARME_CUSTOMER_ID).
        $customerNome  = !empty($clienteInfo['nome'])  ? mb_substr($clienteInfo['nome'], 0, 64) : 'Consumidor Final';
        $customerEmail = !empty($clienteInfo['email']) ? $clienteInfo['email'] : 'consumidor@pdvix.local';
        $customerDoc   = !empty($clienteInfo['cpf'])   ? preg_replace('/\D/', '', $clienteInfo['cpf']) : '00000000000';

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
                    'expires_in' => $expiresIn,  // segundos
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
                'document'      => $customerDoc,
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
        $pix      = $lastTx['qr_code']     ?? null;  // string copia-e-cola
        $pixUrl   = $lastTx['qr_code_url'] ?? null;  // URL do PNG do QR Code
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
    // CANCELAR CHARGE
    // =========================================================================

    public function cancelarCharge(string $orderId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT charge_id FROM pagarme_transacoes WHERE order_id = ? LIMIT 1"
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$row || empty($row->charge_id)) {
            throw new \Exception('Charge não encontrada para cancelamento.');
        }

        $this->_request('DELETE', "/charges/{$row->charge_id}");

        $this->pdo->prepare(
            "UPDATE pagarme_transacoes SET status = 'canceled', updated_at = NOW() WHERE order_id = ?"
        )->execute([$orderId]);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

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

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->apiKey . ':'),
            ],
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

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
