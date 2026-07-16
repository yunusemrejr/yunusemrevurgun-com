<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(dirname(__DIR__));

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';

// Include required files
require_once $projectRoot . '/models/Favicon.php';
require_once $projectRoot . '/includes/csrf.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in as admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['favicon_file']) || $_FILES['favicon_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

try {
    // Initialize favicon model
    $faviconModel = new Favicon();
    
    // Upload favicon
    $result = $faviconModel->uploadFavicon($_FILES['favicon_file']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'filename' => $result['filename'],
            'path' => 'assets/images/' . $result['filename']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Favicon upload error: " . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
