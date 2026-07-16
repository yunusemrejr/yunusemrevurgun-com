<?php
/**
 * Gallery Archive/Restore API
 * Handles archiving and restoring gallery images
 */

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Gallery.php';
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
    $imageId = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? '';

    if (!$imageId || !is_numeric($imageId)) {
        throw new Exception('Invalid image ID');
    }
    if (!in_array($action, ['archive', 'restore'], true)) {
        throw new Exception('Invalid action');
    }

    $gallery = new Gallery();
    $image = $gallery->getImageById($imageId);

    if (!$image) {
        throw new Exception('Image not found');
    }

    if ($action === 'archive') {
        if (!$gallery->archiveImage($imageId)) {
            throw new Exception('Failed to archive image');
        }
        echo json_encode(['success' => true, 'message' => 'Image archived successfully']);
    } else {
        if (!$gallery->unarchiveImage($imageId)) {
            throw new Exception('Failed to restore image');
        }
        echo json_encode(['success' => true, 'message' => 'Image restored successfully']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed: ' . $e->getMessage()
    ]);
}
