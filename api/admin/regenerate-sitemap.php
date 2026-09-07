<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(dirname(__DIR__));

require_once dirname(dirname(__DIR__)) . '/config/setPath.php';
require_once dirname(dirname(__DIR__)) . '/global.php';

// Restrict direct access and require admin authentication
restrictDirectAccess();

// Start session and check admin authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF protection
require_once dirname(dirname(__DIR__)) . '/includes/csrf.php';

if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

header('Content-Type: application/json');

try {
    require_once dirname(dirname(__DIR__)) . '/models/Database.php';
    require_once dirname(dirname(__DIR__)) . '/models/Sitemap.php';
    
    $sitemap = new Sitemap();
    $result = $sitemap->generateSitemapXML();
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Sitemap validated. Published content is included automatically.' : 'Sitemap validation failed.',
        'new_csrf_token' => $_SESSION['csrf_token'],
    ]);

} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Admin sitemap regeneration error: " . $e->getMessage());
    }
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while regenerating the sitemap',
        'error' => 'Please check the server log.'
    ]);
}
?>
