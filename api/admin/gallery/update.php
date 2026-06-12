<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Gallery.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';

if (!$id || !$title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Image ID and title are required']);
    exit;
}

try {
    $gallery = new Gallery();
    $result = $gallery->updateImage($id, ['title' => $title]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Image updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update image']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Gallery update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred']);
}
