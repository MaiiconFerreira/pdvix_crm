<?php
// Autoload do Composer (libs instaladas em /websocket/vendor)
require __DIR__ . '/vendor/autoload.php';

// Autoload manual do seu projeto (raiz)
require __DIR__ . '/../config.php';

use App\Controllers\PagamentosController;
use App\Controllers\WhatsappController;

// Conexão Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Inicializa o controller de pagamentos
$pagamentosController = new PagamentosController();

echo "[" . date('Y-m-d H:i:s') . "] Worker iniciado\n";

while (true) {
    try {
        // Blpop espera por até 30s por uma ordem.
        // Se a fila estiver vazia, ele fica parado aqui (não consome CPU nem API).
        $item = $redis->blpop('payment_queue', 30);

        if ($item) {
            // Início da cronometragem para controle fino (opcional, mas recomendado)
            $startTime = microtime(true);

            $orderData = json_decode($item[1], true);
            echo "[" . date('Y-m-d H:i:s') . "] Processando ordem ID: {$orderData['id_ordem']}\n";

            // Processa a ordem (Gera token cacheado se precisar e envia pix)
            $result = $pagamentosController->processOrder($orderData);

            if ($result) {
                echo "[" . date('Y-m-d H:i:s') . "] Sucesso: Ordem ID {$orderData['id_ordem']}\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Falha: Ordem ID {$orderData['id_ordem']}\n";
                // Lógica de retry se necessário
            }

            // --- RATE LIMITING ---
            // A API permite ~1 req/s.
            // Se o processamento foi muito rápido (ex: 0.2s), dormimos o resto do segundo.
            // Se o processamento demorou mais que 1s, não precisa dormir.

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Vamos garantir pelo menos 1.1 segundos de intervalo entre inícios de processamento
            // para ser conservador com o limite de 60/min.
            if ($executionTime < 1.1) {
                $sleepTime = (1.1 - $executionTime) * 1000000; // converte para microssegundos
                usleep((int)$sleepTime);
            }
        }
    } catch (\Exception $e) {
        // Log de erros
        echo "[" . date('Y-m-d H:i:s') . "] ERRO CRÍTICO NO WORKER: " . $e->getMessage() . "\n";
        sleep(5); // Pausa maior em caso de erro de conexão/redis
    }
}
