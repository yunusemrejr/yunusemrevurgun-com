<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

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

require_once dirname(__DIR__, 3) . '/global.php';
verifyAdminAction();
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Rmrp.php';
Auth::checkLogin();

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $response['message'] = 'Invalid security token';
        } else if (isset($_POST['id'])) {
            $rmrp = new Rmrp();
            $id = (int)$_POST['id'];
            if ($rmrp->deleteMemory($id)) {
                $response['success'] = true;
                $response['message'] = 'Memory deleted successfully';
            } else {
                $response['message'] = 'Failed to delete memory';
            }
        } else {
            $response['message'] = 'No memory ID provided';
        }
    }
} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Exception in rmrp delete: " . $e->getMessage());
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
