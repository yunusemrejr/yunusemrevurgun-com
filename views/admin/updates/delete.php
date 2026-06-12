<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 3) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>  <?php
// Turn off output buffering and disable error display for this script
ob_end_clean();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Skip direct access check for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Continue processing
} else {
    // If global.php is not included, include it and restrict direct access
    if (file_exists('../../../global.php')) {
        require_once '../../../global.php';
        restrictDirectAccess();
    }
}

require_once __DIR__ . '/../../../global.php';
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Updates.php';
Auth::checkLogin();

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $response['message'] = 'Invalid security token';
        } else if (isset($_POST['id'])) {
            $updates = new Updates();
            $id = (int)$_POST['id'];
            if ($updates->deleteUpdate($id)) {
                $response['success'] = true;
                $response['message'] = 'Update deleted successfully';
            } else {
                $response['message'] = 'Failed to delete update';
            }
        } else {
            $response['message'] = 'No update ID provided';
        }
    }
} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Exception in updates delete: " . $e->getMessage());
    }
    $response['message'] = 'Server error occurred';
}

// Ensure no output before JSON
if (ob_get_length()) ob_clean();

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode($response);
exit; 