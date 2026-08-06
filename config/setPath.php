<?php
// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
$mainFolderPath = 'https://yunusemrevurgun.com/'; // Default fallback path
$mainFolderMode = 'https'; // Default mode
$useSSL = true; // Default to using SSL
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'yunusemrevurgun.com');
$protocol = 'https://'; // Default protocol
$localUrl = ''; // For development mode

// Auto-detect PHP built-in server or localhost and set dev mode automatically
$isBuiltInServer = (php_sapi_name() === 'cli-server');
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

$isLocalhost = (
    strpos($httpHost, 'localhost') !== false ||
    strpos($httpHost, '127.0.0.1') !== false ||
    strpos($httpHost, '192.168.') !== false ||
    strpos($httpHost, '10.0.') !== false ||
    $serverAddr === '127.0.0.1' ||
    $serverAddr === '::1' ||
    $remoteAddr === '127.0.0.1' ||
    $remoteAddr === '::1'
);

// Robust environment variable helper: checks getenv(), $_ENV, and $_SERVER
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value !== false) return $value;
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        return $default;
    }
}

// Load environment variables (once, shared for local and production paths)
$envLines = [];
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (empty($line) || strpos($line, '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $name = trim($parts[0]);
        $value = trim($parts[1], " \t\n\r\0\x0B'\"");
        
        // Load critical env vars that may be needed even in localhost mode
        if (in_array($name, ['MODE', 'TURNSTILE_SITEKEY', 'TURNSTILE_SECRET', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'DB_CONNECTION', 'MASTODON_BASE_URL', 'MASTODON_ACCESS_TOKEN', 'MASTODON_DEFAULT_VISIBILITY', 'MASTODON_DEFAULT_LANGUAGE'], true) && getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

// For localhost/dev server: ALWAYS force development mode
if ($isBuiltInServer || $isLocalhost) {
    putenv("MODE=development"); // Force development mode for local
    $localProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $localHost = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    define('FULL_BASE_PATH', $localProtocol . $localHost . '/');
    define('BASE_PATH', $localProtocol . $localHost . '/');
    return; // Exit early for local development
}

if ($envLines) {
    foreach ($envLines as $line) {
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse the line
        $parts = explode('=', $line, 2);
        // Check if we have both name and value parts
        if (count($parts) !== 2) {
            continue; // Skip lines that don't have a proper key=value format
        }
        
        $name = trim($parts[0]);
        $value = trim($parts[1], " \t\n\r\0\x0B'\"");
        
        // Store the values
        if ($name === 'MAIN_FOLDER_PATH') {
            $mainFolderPath = $value;
        } elseif ($name === 'MAIN_FOLDER_MODE') {
            $mainFolderMode = strtolower(trim($value));
        } elseif ($name === 'USE_SSL') {
            $useSSL = (strtolower($value) === 'true' || $value === '1' || $value === 'on');
        } elseif ($name === 'MODE') {
            if (env('MODE') === null) { putenv("MODE=$value"); $_ENV['MODE'] = $value; }
        } elseif ($name === 'LOCAL_URL') {
            $localUrl = $value;
        } elseif ($name === 'TURNSTILE_SITEKEY') {
            if (env('TURNSTILE_SITEKEY') === null) { putenv("TURNSTILE_SITEKEY=$value"); $_ENV['TURNSTILE_SITEKEY'] = $value; }
        } elseif ($name === 'TURNSTILE_SECRET') {
            if (env('TURNSTILE_SECRET') === null) { putenv("TURNSTILE_SECRET=$value"); $_ENV['TURNSTILE_SECRET'] = $value; }
        } elseif ($name === 'MASTODON_BASE_URL') {
            if (env('MASTODON_BASE_URL') === null) { putenv("MASTODON_BASE_URL=$value"); $_ENV['MASTODON_BASE_URL'] = $value; }
        } elseif ($name === 'MASTODON_ACCESS_TOKEN') {
            if (env('MASTODON_ACCESS_TOKEN') === null) { putenv("MASTODON_ACCESS_TOKEN=$value"); $_ENV['MASTODON_ACCESS_TOKEN'] = $value; }
        } elseif ($name === 'MASTODON_DEFAULT_VISIBILITY') {
            if (env('MASTODON_DEFAULT_VISIBILITY') === null) { putenv("MASTODON_DEFAULT_VISIBILITY=$value"); $_ENV['MASTODON_DEFAULT_VISIBILITY'] = $value; }
        } elseif ($name === 'MASTODON_DEFAULT_LANGUAGE') {
            if (env('MASTODON_DEFAULT_LANGUAGE') === null) { putenv("MASTODON_DEFAULT_LANGUAGE=$value"); $_ENV['MASTODON_DEFAULT_LANGUAGE'] = $value; }
        } elseif ($name === 'DB_HOST') {
            if (env('DB_HOST') === null) { putenv("DB_HOST=$value"); $_ENV['DB_HOST'] = $value; }
        } elseif ($name === 'DB_NAME') {
            if (env('DB_NAME') === null) { putenv("DB_NAME=$value"); $_ENV['DB_NAME'] = $value; }
        } elseif ($name === 'DB_USER') {
            if (env('DB_USER') === null) { putenv("DB_USER=$value"); $_ENV['DB_USER'] = $value; }
        } elseif ($name === 'DB_PASS') {
            if (env('DB_PASS') === null) { putenv("DB_PASS=$value"); $_ENV['DB_PASS'] = $value; }
        } elseif ($name === 'DB_PORT') {
            if (env('DB_PORT') === null) { putenv("DB_PORT=$value"); $_ENV['DB_PORT'] = $value; }
        } elseif ($name === 'DB_CONNECTION') {
            if (env('DB_CONNECTION') === null) { putenv("DB_CONNECTION=$value"); $_ENV['DB_CONNECTION'] = $value; }
        }
    }
    
    // Set protocol based on SSL setting
    $protocol = $useSSL ? 'https://' : 'http://';

    // Special handling for development mode
    if (getenv('MODE') === 'development' && !empty($localUrl)) {
        $mainFolderPath = rtrim($localUrl, '/') . '/';
        $mainFolderMode = 'https';
        define('FULL_BASE_PATH', $mainFolderPath);
        define('BASE_PATH', $mainFolderPath);
        return; // Exit early for development mode
    }

    // Process the path based on the mode after reading all variables
    if ($mainFolderMode === 'domain') {
        // For domain mode, ensure path ends with / and doesn't have protocol
        $mainFolderPath = preg_replace('#^https?://#', '', $mainFolderPath);
        $mainFolderPath = rtrim($mainFolderPath, '/') . '/';
    } elseif ($mainFolderMode === 'https') {
        // For https/http mode, ensure it starts with the correct protocol
        $mainFolderPath = preg_replace('#^https?://#', '', $mainFolderPath);
        $mainFolderPath = $protocol . ltrim($mainFolderPath, '/');
        // Ensure it ends with a slash
        $mainFolderPath = rtrim($mainFolderPath, '/') . '/';
    } elseif ($mainFolderMode === 'plain') {
        // For plain mode, keep only the path part without protocol or domain
        $mainFolderPath = preg_replace('#^https?://[^/]+#', '', $mainFolderPath);
        if (empty($mainFolderPath)) {
            $mainFolderPath = '/';
        } else {
            $mainFolderPath = '/' . trim($mainFolderPath, '/') . '/';
        }
        
        // For URLs that need a full path, we'll use the current host
        define('FULL_BASE_PATH', $protocol . $host . $mainFolderPath);
    } else {
        // Invalid mode, use default format with appropriate protocol
        $mainFolderPath = $protocol . 'yunusemrevurgun.com/';
    }
}

// Define the BASE_PATH constant
define('BASE_PATH', $mainFolderPath);

// If FULL_BASE_PATH is not defined yet and we need it for other modes
if (!defined('FULL_BASE_PATH')) {
    define('FULL_BASE_PATH', BASE_PATH);
}


if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}