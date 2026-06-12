<?php
// Handle static files for PHP built-in server (dev mode)
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    
    if (is_file($file)) {
        return false; // Let PHP built-in server handle the static file directly
    }
}

// Include the error handler first
require_once __DIR__ . '/config/error_handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'global.php';
require_once 'middleware/SecurityMiddleware.php';
require_once 'middleware/SessionAdminSecurityMiddleware.php';

// Create request array
$request = [
    'uri' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
    'post' => $_POST
];

// Apply security middleware
$security = new SecurityMiddleware($request);
$security->handle();

// Apply the middleware for admin routes
$middleware = new SessionAdminSecurityMiddleware($request);
if (!$middleware->handle()) {
    // If middleware fails, stop further processing
    exit;
}

// Continue with routing
if (getenv('MODE') === 'development') {
    error_log("Index.php: About to load router");
}
require_once 'router/Router.php';
$router = new Router();
$router->route();

echo <<<HTML
 
HTML;


?> 