<?php
/**
 * Gallery Albums API
 * Handles album CRUD operations and image-album assignments
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

// Validate CSRF token
if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
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
    $gallery = new Gallery();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Album name is required');
            }
            $name = mb_substr($name, 0, 255);
            $description = mb_substr($description, 0, 2000);

            $albumId = $gallery->addAlbum($name, $description);
            
            echo json_encode([
                'success' => true,
                'message' => 'Album created successfully',
                'album_id' => $albumId
            ]);
            break;
            
        case 'update':
            $id = $_POST['id'] ?? null;
            
            if (!$id || !is_numeric($id)) {
                throw new Exception('Invalid album ID');
            }
            
            $data = [];
            if (isset($_POST['name'])) {
                $name = trim($_POST['name']);
                if (empty($name)) {
                    throw new Exception('Album name cannot be empty');
                }
                $data['name'] = $name;
            }
            if (isset($_POST['description'])) {
                $data['description'] = trim($_POST['description']);
            }
            
            if (empty($data)) {
                throw new Exception('No data to update');
            }
            
            $result = $gallery->updateAlbum((int)$id, $data);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Album updated successfully' : 'Failed to update album'
            ]);
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? null;
            
            if (!$id || !is_numeric($id)) {
                throw new Exception('Invalid album ID');
            }
            
            $result = $gallery->deleteAlbum((int)$id);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Album deleted successfully' : 'Failed to delete album'
            ]);
            break;
            
        case 'assign':
            $imageId = $_POST['image_id'] ?? null;
            $albumId = $_POST['album_id'] ?? null;
            
            if (!$imageId || !is_numeric($imageId)) {
                throw new Exception('Invalid image ID');
            }
            
            // albumId can be null (to unassign), '0' or empty (to unassign), or a valid integer
            $albumIdValue = null;
            if ($albumId !== null && $albumId !== '' && $albumId !== '0') {
                $albumIdValue = (int)$albumId;
            }
            
            $result = $gallery->assignImageToAlbum((int)$imageId, $albumIdValue);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Image assignment updated successfully' : 'Failed to update image assignment'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Operation failed: ' . $e->getMessage()
    ]);
}
?>
