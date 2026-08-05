<?php
require_once 'setPath.php';
//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

$mode = env('MODE') ?: 'development';
$driver = env('DB_CONNECTION');

// If DB_CONNECTION is not explicitly set, infer from environment
if (empty($driver)) {
    // If MySQL credentials are present, use MySQL regardless of MODE
    if (env('DB_HOST') || env('DB_NAME') || env('DB_USER')) {
        $driver = 'mysql';
    } else {
        $driver = ($mode === 'production') ? 'mysql' : 'sqlite';
    }
}

if ($mode === 'production' || $driver === 'mysql') {
    return [
        'driver' => $driver,
        'host' => env('DB_HOST') ?: 'localhost',
        'dbname' => env('DB_NAME') ?: 'personal_website',
        'username' => env('DB_USER') ?: 'root',
        'password' => env('DB_PASS') ?: '',
        'port' => env('DB_PORT') ?: 3306,
        'charset' => 'utf8mb4'
    ];
}

// Development mode with SQLite
$dbPath = env('DB_NAME');
if (empty($dbPath)) {
    $dbPath = __DIR__ . '/../dev/test_db.sqlite';
}
return [
    'driver' => 'sqlite',
    'dbname' => $dbPath
];

