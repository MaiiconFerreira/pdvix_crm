<?php

$prod = false;

define('APPLICATION_VERSION', '1.0.0');

define('DB_HOST', 'localhost');
define('DB_NAME', ($prod) ? 'pdvix_crm' : 'pdvix_crm');
define('DB_USER', ($prod) ? 'root' : 'root');
define('DB_PASS', ($prod) ? '' : '');

define('PATH_SCREENS', '/');
define('CORS_ALLOW_URLS', '/');
$GLOBALS['CORS_ALLOW_DOMAIN'] = ['http://localhost'];

define('PATH_LOGS', ($prod) ? '/var/www/html/logs/' : 'C:/xampp/pdvix_crm/logs/');

date_default_timezone_set('America/Cuiaba');

// ── Pagar.me ─────────────────────────────────────────────────────────────────
// Substitua pelos valores reais do dashboard Pagar.me v5
define('PAGARME_API_KEY',       'sk_7b77b2c61c0a443ea83bca26744ed8cb');
define('PAGARME_WEBHOOK_SECRET', getenv('PAGARME_WEBHOOK_SECRET') ?: 'whsec_COLOQUE_SEU_SECRET_AQUI');

// ── PDV Electron ──────────────────────────────────────────────────────────────
// Token estático compartilhado com o PDV Electron para autenticação das rotas /api/pdv/sync-*
// Gere um UUID v4 e salve também na tabela config (chave = 'api_token')
define('PDV_API_TOKEN', getenv('PDV_API_TOKEN') ?: 'COLOQUE_UM_TOKEN_SEGURO_AQUI');

// ── Redis ─────────────────────────────────────────────────────────────────────
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', (int)(getenv('REDIS_PORT') ?: 6379));

// ── WebSocket (Workerman) ────────────────────────────────────────────────────
define('WS_PORT', (int)(getenv('WS_PORT') ?: 8080));



// LOCK GLOBAL (1 PROCESSO POR WORKER)
function acquireLock($name) {
    $lockFile = sys_get_temp_dir() . "/{$name}.lock";
    $fp = fopen($lockFile, 'c');
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        exit;
    }
    return $fp;
}

function isWindows() {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function runCommand($cmd) {
    exec($cmd . ' 2>&1', $output, $code);
    return [$code === 0, implode("\n", $output)];
}

spl_autoload_register(function ($classe) {
    if (class_exists($classe, false)) return;

    $caminho = str_replace('\\', DIRECTORY_SEPARATOR, $classe) . '.php';
    $caminho_final = __DIR__ . DIRECTORY_SEPARATOR . $caminho;

    if (file_exists($caminho_final)) {
        require_once $caminho_final;
        return;
    }
});