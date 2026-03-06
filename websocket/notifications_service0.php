
<?php
// Autoload do Composer (libs instaladas em /websocket/vendor)
require __DIR__ . '/vendor/autoload.php';

// Autoload manual do seu projeto (raiz)
require __DIR__ . '/../config.php';

use App\Controllers\WhatsappController;

// Conexão Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

echo "[" . date('Y-m-d H:i:s') . "] Worker iniciado\n";

$whatsappController = new WhatsappController();

while (true) {
    try {
        // Espera por até 30s por uma ordem na fila "payment_queue"
        $item = $redis->blpop('notifications_daily', 30);

        if ($item) {
            $dailyData = json_decode($item[1], true); // $item[1] contém o valor
            echo "[" . date('Y-m-d H:i:s') . "] Enviando para: \n";

            // Aqui você chama sua função de processamento
            $result = $whatsappController->processarNotificacoes($dailyData);

            if ($result) {
                echo "[" . date('Y-m-d H:i:s') . "] Notificação processada com sucesso\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Falha ao processar notificação \n";
                // Opcional: colocar de volta na fila para retry
                //$redis->lpush('payment_queue', json_encode($orderData));
            }
        }
    } catch (\Exception $e) {
        // Log de erros
        echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
        sleep(30); // evita loop infinito em caso de erro grave
    }
}
