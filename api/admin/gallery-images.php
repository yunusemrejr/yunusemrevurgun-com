<?php
/**
 * Gallery Images API
 * Returns gallery images for selection when linking to travel locations
 */

error_reporting(E_ALL);
ini_set('display_errors', getenv('MODE') === 'development' ? '1' : '0');

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/global.php';
require_once dirname(__DIR__, 2) . '/models/Auth.php';
require_once dirname(__DIR__, 2) . '/models/Gallery.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $gallery = new Gallery();
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 24;
    $offset = ($page - 1) * $limit;

    $images = $gallery->getActiveImages($offset, $limit);
    $total = $gallery->getTotalActiveImages();
    $totalPages = ceil($total / $limit);

    echo json_encode([
        'success' => true,
        'images' => $images,
        'page' => $page,
        'total_pages' => $totalPages,
        'total' => $total
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed: ' . $e->getMessage()
    ]);
}
?>
