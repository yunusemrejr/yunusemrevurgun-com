<?php
// Handle static files for PHP built-in server (dev mode)
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if (preg_match('#^/(?:\.env|\.git|config/|models/|includes/|middleware/|router/)|\.(?:sqlite|log|jsonl)$#i', $path)) {
        http_response_code(403);
        return;
    }
    
    if (is_file($file) && $path !== '/sitemap.xml') {
        return false; // Let PHP built-in server handle the static file directly
    }
}

// Include the error handler first
require_once __DIR__ . '/config/error_handler.php';

// Set session cookie params before starting session
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// A first-time visitor reading a content page needs no session: nothing on those
// pages is per-visitor. Starting one anyway wrote a session file (kept for 24 h by
// session.gc_maxlifetime) for every cookie-less request, which means one per
// crawler fetch, and PHP's session cache limiter stamped every page
// `Cache-Control: no-store`, which also switches off the browser back/forward
// cache. So: no cookie, GET/HEAD, and a content route means no session. Anything
// else (the contact form's captcha and CSRF token, every API, the admin area, and
// any visitor who already holds a session cookie, such as a logged-in admin) takes
// the unchanged path below.
$yevPath = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$yevStateless = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    && !isset($_COOKIE[session_name()])
    && ($yevPath === '' || preg_match('#^(?:home|about|portfolio|gallery|travel|blog|updates|rmrp|post-code|science-corner|yunobot|gemmaclaim|chessko|jelloshop|videos|downloads|slop|music|comedy|more|sitemap|privacy|terms|cookies|search|llms\.txt|sitemap\.xml|blog\.xml|updates\.xml|rmrp\.xml)(?:/|$)#', $yevPath) === 1);
$GLOBALS['yev_stateless'] = $yevStateless;

if ($yevStateless) {
    header('Cache-Control: no-cache');
} elseif (session_status() === PHP_SESSION_NONE) {
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
