<?php
error_reporting(E_ALL);
ini_set('display_errors', getenv('MODE') === 'development' ? '1' : '0');

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Music.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid track ID');

    $music = new Music();
    $track = $music->getTrackById($id);
    if (!$track) throw new Exception('Track not found');

    switch ($action) {
        case 'archive':
            $result = $music->archiveTrack($id);
            $msg = 'Track archived';
            break;
        case 'restore':
            $result = $music->unarchiveTrack($id);
            $msg = 'Track restored';
            break;
        case 'delete':
            $result = $music->deleteTrack($id);
            $msg = 'Track deleted';
            break;
        default:
            throw new Exception('Invalid action');
    }

    if ($result) {
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        throw new Exception('Failed to ' . $action . ' track');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
