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
?>
<?php
require_once __DIR__ . '/../../models/Auth.php';
$auth = new Auth();
$auth->logout(); 