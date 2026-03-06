<?php
// Carrega o ambiente (ajuste o caminho conforme sua estrutura)
require __DIR__ . '/../config.php';

use App\Services\Scheduler\Worker;

// Garante que só rode via CLI (Terminal)
if (php_sapi_name() !== 'cli') {
    die("Este serviço só pode ser executado via linha de comando.");
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando Worker do Scheduler...\n";

try {
    $worker = new Worker();
    $worker->run();
} catch (\Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}