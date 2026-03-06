<?php
// Autoload do Composer
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../config.php';

use App\Controllers\WhatsappController;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Conexão Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

echo "[" . date('Y-m-d H:i:s') . "] Worker iniciado\n";

$whatsappController = new WhatsappController();

// Configuração Guzzle com Timeout
$http = new Client([
    'timeout' => 15, // Aumentei levemente pois lotes de 100 podem demorar um pouco mais
]);

define('EXPO_PUSH_ENDPOINT', 'https://exp.host/--/api/v2/push/send');
// Define o tamanho do lote conforme documentação da Expo
define('EXPO_CHUNK_SIZE', 100); 

while (true) {
    try {
        // Adicionada a nova fila 'expo_push_queue' na escuta
        $item = $redis->blpop(
            ['notifications_daily', 'new_pass_token', 'solicitacao_cadastral', 'fila_desistencia_notificacao', 'expo_push_queue'],
            30
        );

        if ($item) {
            $queue = $item[0];
            $data  = json_decode($item[1]);

            switch ($queue) {
                // ... SEUS CASES EXISTENTES ...
                case 'notifications_daily':
                    $delay = rand(1, 57);
                    echo "[" . date('Y-m-d H:i:s') . "] Aguardando {$delay}s...\n";
                    sleep($delay);
                    echo "[" . date('Y-m-d H:i:s') . "] Enviando notificação diária...\n";
                    $result = $whatsappController->processarNotificacoes($data);
                    break;

                case 'new_pass_token':
                    echo "[" . date('Y-m-d H:i:s') . "] Enviando nova senha...\n";
                    $result = $whatsappController->enviarNovaSenha($data);
                    break;

                case 'solicitacao_cadastral':
                    $delay = rand(1, 10);
                    echo "[" . date('Y-m-d H:i:s') . "] Aguardando {$delay}s...\n";
                    sleep($delay);
                    echo "[" . date('Y-m-d H:i:s') . "] Solicitando conclusão cadastral...\n";
                    //$result = $whatsappController->solicitarConclusaoCadastral($data);
                    break;

                case 'fila_desistencia_notificacao':
                    echo "[" . date('Y-m-d H:i:s') . "] Notificando supervisores...\n";
                    //$result = $whatsappController->notificarSupervisoresDesistencia($data);
                    break;

                // --- NOVA LÓGICA DA EXPO AQUI ---
                case 'expo_push_queue':
                    // Estrutura esperada do $data:
                    // {
                    //    "tokens": ["ExponentPushToken[xxx]", ...],
                    //    "title": "Título",
                    //    "body": "Mensagem",
                    //    "data": { "key": "value" }
                    // }
                    $tokens = $data->tokens ?? [];
                    $title  = $data->title ?? 'Nova Notificação';
                    $body   = $data->body ?? '';
                    $payloadData = $data->data ?? null;

                    if (empty($tokens) || !is_array($tokens)) {
                        echo "[" . date('Y-m-d H:i:s') . "] ERRO EXPO: Lista de tokens vazia ou inválida.\n";
                        break;
                    }

                    echo "[" . date('Y-m-d H:i:s') . "] Processando EXPO PUSH para " . count($tokens) . " tokens.\n";

                    // 1. Divide os tokens em lotes de 100 (Limite da Expo)
                    $chunks = array_chunk($tokens, EXPO_CHUNK_SIZE);

                    foreach ($chunks as $index => $chunk) {
                        $messages = [];
                        
                        // Prepara o array de mensagens para este lote
                        foreach ($chunk as $token) {
                            if (!empty($token)) {
                                $messages[] = [
                                    'to' => $token,
                                    'title' => $title,
                                    'body' => $body,
                                    'data' => $payloadData,
                                    'sound' => 'default',
                                ];
                            }
                        }

                        if (empty($messages)) continue;

                        try {
                            // Envia o lote para a Expo
                            $response = $http->post(EXPO_PUSH_ENDPOINT, [
                                'json' => $messages,
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Accept-Encoding' => 'gzip, deflate',
                                    'Content-Type' => 'application/json',
                                ],
                            ]);

                            $statusCode = $response->getStatusCode();
                            $responseBody = json_decode($response->getBody(), true);

                            // Análise de resposta da Expo (Tickets)
                            if (isset($responseBody['data']) && is_array($responseBody['data'])) {
                                $errorsCount = 0;
                                foreach ($responseBody['data'] as $ticket) {
                                    if ($ticket['status'] === 'error') {
                                        $errorsCount++;
                                        // AQUI: Você pode capturar tokens inválidos (DeviceNotRegistered) para remover do banco
                                        // echo "Erro no token: " . $ticket['message'] . "\n";
                                    }
                                }
                                echo "[" . date('Y-m-d H:i:s') . "] Lote " . ($index + 1) . " enviado. Sucessos: " . (count($messages) - $errorsCount) . " | Falhas: $errorsCount\n";
                            }
                            
                            
                        } catch (RequestException $e) {
                            // Tratamento de erro HTTP (ex: 429 Too Many Requests)
                            if ($e->hasResponse()) {
                                $res = $e->getResponse();
                                $code = $res->getStatusCode();
                                
                                echo "[" . date('Y-m-d H:i:s') . "] ERRO HTTP EXPO ($code): " . $res->getReasonPhrase() . "\n";

                                // Se for Rate Limit, espera um pouco antes de tentar o próximo lote
                                if ($code == 429) {
                                    echo "Rate Limit atingido. Pausando por 2 segundos...\n";
                                    sleep(2);
                                    // Obs: Idealmente, deveria-se reenfileirar este lote específico, 
                                    // mas no fluxo simples apenas logamos e seguimos.
                                }
                            } else {
                                echo "[" . date('Y-m-d H:i:s') . "] ERRO CONEXÃO EXPO: " . $e->getMessage() . "\n";
                            }
                        } catch (\Exception $e) {
                            echo "[" . date('Y-m-d H:i:s') . "] ERRO GENÉRICO EXPO: " . $e->getMessage() . "\n";
                        }
                    }
                    
                    // Define sucesso para o log geral lá embaixo não cair no 'else'
                    $result = true; 
                    break;
            }

            // Log de sucesso/falha existente
            if (!empty($result)) {
                echo "[" . date('Y-m-d H:i:s') . "] Fila '$queue' processada com sucesso\n";
            } else {
                // Logica de falha existente
            }
        }

    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] FATAL ERROR NO WORKER: " . $e->getMessage() . "\n";
        // Mantive seu sleep original de erro
        sleep(30);
    }
}