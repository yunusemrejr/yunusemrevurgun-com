<?php
/**
 * Travel Link Gallery Image API
 * Links a gallery image to a travel location without copying the file
 */

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
require_once dirname(__DIR__, 3) . '/models/Travel.php';
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
    $locationId = $_POST['location_id'] ?? null;
    $imageId = $_POST['image_id'] ?? null;

    if (!$locationId || !is_numeric($locationId)) {
        throw new Exception('Invalid location ID');
    }
    if (!$imageId || !is_numeric($imageId)) {
        throw new Exception('Invalid gallery image ID');
    }

    $travel = new Travel();
    $gallery = new Gallery();

    $location = $travel->getLocationById((int)$locationId);
    if (!$location) {
        throw new Exception('Travel location not found');
    }

    $galleryImage = $gallery->getImageById((int)$imageId);
    if (!$galleryImage) {
        throw new Exception('Gallery image not found');
    }

    $result = $travel->addGalleryImageRef((int)$locationId, (int)$imageId, $galleryImage['filename']);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Gallery image linked successfully' : 'Failed to link gallery image',
        'filename' => $galleryImage['filename']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed: ' . $e->getMessage()
    ]);
}
?>
