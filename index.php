<?php
// Handle static files for PHP built-in server (dev mode)
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if (preg_match('#^/(?:\.env|\.git|config/|models/|includes/|middleware/|router/)|\.(?:sqlite|log|jsonl)$#i', $path)) {
        http_response_code(403);
        return;
    }
    
    if (is_file($file) && !preg_match('#^/sitemap(?:-(?:pages|posts|images|videos))?\.xml$#', $path)) {
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
    // Conditional GET for cookie-less content pages: the rendered body is
    // identical for every visitor, so a strong ETag over it lets crawlers
    // and feed readers revalidate with If-None-Match and receive a 304
    // (headers only) instead of the full page on every revisit — cheaper
    // recrawls and an unambiguous "unchanged" freshness signal. Redirects,
    // errors, downloads and anything that set a cookie or its own ETag pass
    // through untouched; session-backed routes never reach this branch.
    // Capture-and-re-emit at shutdown rather than an ob_start callback:
    // under LSAPI the response headers are already committed when a buffer
    // callback runs, so header() there is silently dropped (measured on
    // production); holding the whole body in our own buffer keeps headers
    // unsent until we decide.
    ob_start();
    $yevBufferLevel = ob_get_level();
    register_shutdown_function(static function () use ($yevBufferLevel): void {
        $chunks = [];
        while (ob_get_level() >= $yevBufferLevel) {
            $chunk = ob_get_clean();
            if ($chunk === false) break;
            $chunks[] = $chunk;
        }
        $body = implode('', array_reverse($chunks));
        if ($body === '' || headers_sent() || http_response_code() !== 200) {
            echo $body;
            return;
        }
        foreach (headers_list() as $sentHeader) {
            if (preg_match('#^(Location|Content-Disposition|Set-Cookie|ETag):#i', $sentHeader)) {
                echo $body;
                return;
            }
        }
        $etag = '"' . hash('sha256', $body) . '"';
        header('ETag: ' . $etag);
        $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($ifNoneMatch !== '') {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
                    http_response_code(304);
                    return;
                }
            }
        }
        echo $body;
    });
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
