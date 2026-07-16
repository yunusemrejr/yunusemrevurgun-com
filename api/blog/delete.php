<?php
require_once dirname(__DIR__, 2) . '/global.php';
require_once dirname(__DIR__, 2) . '/models/Auth.php';
require_once dirname(__DIR__, 2) . '/models/Blog.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
try {
    Auth::checkLogin();
    verifyAdminAction();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
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

// Get post ID
$postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$postId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

try {
    $blog = new Blog();
    
    // Check if post exists
    $post = $blog->getPostById($postId);
    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Delete the post first, then clean up files
    $result = $blog->deletePost($postId);
    
    if ($result && !empty($post['featured_image'])) {
        $imagePath = dirname(__DIR__, 2) . '/' . $post['featured_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Post deleted successfully',
            'post_id' => $postId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete post']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Blog delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred']);
}
?>
