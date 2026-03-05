<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if (isset($routes[$uri])) {
    $controllerClass = 'app\\Controllers\\' . $routes[$uri]['controller'];
    $method = $routes[$uri]['method'];

    require_once __DIR__ . '/../App/Controllers/' . $routes[$uri]['controller'] . '.php';

    $controller = new $controllerClass();
    $controller->$method();
} else {
    http_response_code(404);
    //echo "Página não encontrada.";
}
