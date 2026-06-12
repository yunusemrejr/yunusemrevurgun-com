<?php
/**
 * Gallery Delete API
 * Handles image deletion from the gallery
 */

// Allow API access but prevent direct browser access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Include required files
require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Gallery.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Check CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';

// Check if session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate CSRF token
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get image ID
    $imageId = $_POST['id'] ?? null;
    
    if (!$imageId || !is_numeric($imageId)) {
        throw new Exception('Invalid image ID');
    }
    
    $gallery = new Gallery();
    
    // Get image data before deletion
    $image = $gallery->getImageById($imageId);
    
    if (!$image) {
        throw new Exception('Image not found');
    }
    
    // Delete (model handles both database row and physical file)
    if (!$gallery->deleteImage($imageId)) {
        throw new Exception('Failed to delete image');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Image deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Delete failed: ' . $e->getMessage()
    ]);
}
?>
