<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 2) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>  <?php
//if session not initialized, set it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If global.php is not included, include it and restrict direct access
if (file_exists('../../global.php')) {
    require_once '../../global.php';
    restrictDirectAccess();
}  
?><?php
require_once __DIR__ . '/../../global.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_COOKIE['js_enabled'])) {
    http_response_code(403);
    echo json_encode(['error' => 'JavaScript disabled']);
    // Also clear the session
    unset($_SESSION['js_check']);
    exit;
}

echo json_encode(['status' => 'ok']);
exit; 