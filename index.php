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

// Set session cookie params before starting session
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once 'global.php';
require_once 'middleware/SessionAdminSecurityMiddleware.php';

// Create request array
$request = [
    'uri' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
    'post' => $_POST
];

// Apply admin security middleware (includes auth + CSRF checks)
$middleware = new SessionAdminSecurityMiddleware($request);
if (!$middleware->handle()) {
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