<?php
$projectRoot = __DIR__;

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/includes/csrf.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function ensureSessionStarted(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function restrictDirectAccess() {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return;
    }
    
    $scriptPath = $_SERVER['SCRIPT_FILENAME'];
    $callingFile = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['file'] ?? '';
    
    if ((strpos($scriptPath, '/views/') !== false || strpos($scriptPath, '/models/') !== false) &&
        strpos($callingFile, 'index.php') === false && strpos($callingFile, '/controllers/') === false) {
        if (getenv('MODE') === 'development') {
            header('Location: /403.php');
        } else {
            header('Location: ' . FULL_BASE_PATH . '403.php');
        }
        exit;
    }
}

CSRFProtection::generateToken();

function isAdminRequestUri(string $uri): bool {
    $basePath = parse_url(FULL_BASE_PATH, PHP_URL_PATH) ?: '/';
    $basePath = rtrim($basePath, '/');
    $adminPath = $basePath . '/admin';
    $requestPath = parse_url($uri, PHP_URL_PATH);
    return $requestPath === $adminPath || strpos($requestPath, $adminPath . '/') === 0;
}

function getAdminLoginPath(): string {
    return (getenv('MODE') === 'development') ? '/admin/login' : FULL_BASE_PATH . 'admin/login';
}

function requireAdminSession(bool $allowCli = true): void {
    if (PHP_SESSION_NONE === session_status()) {
        session_start();
    }
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if ($allowCli && php_sapi_name() === 'cli') {
            return;
        }
        http_response_code(403);
        exit;
    }
}

function verifyAdminSession(): bool {
    if (PHP_SESSION_NONE === session_status()) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function verifyAdminAction() {
    // Always use path-based comparison (works in both dev and prod)
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($requestPath, '/admin/login') !== false || strpos($requestPath, '/admin/logout') !== false) {
        return true;
    }

    if (!verifyAdminSession()) {
        if (getenv('MODE') === 'development') {
            header('Location: /admin/login');
        } else {
            header('Location: ' . FULL_BASE_PATH . 'admin/login');
        }
        exit;
    }
    return true;
}