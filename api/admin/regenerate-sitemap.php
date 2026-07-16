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
    
    if ($result) {
        // Check if files were created
        $publicPath = dirname(dirname(__DIR__)) . '/public/sitemap.xml';
        $rootPath = dirname(dirname(__DIR__)) . '/sitemap.xml';
        
        $publicExists = file_exists($publicPath);
        $rootExists = file_exists($rootPath);
        
        if ($publicExists || $rootExists) {
            echo json_encode([
                'success' => true,
                'message' => 'Sitemap regenerated successfully',
                'new_csrf_token' => $_SESSION['csrf_token'],
                'details' => [
                    'public_sitemap' => $publicExists,
                    'root_sitemap' => $rootExists,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Sitemap generation completed but files were not created'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Sitemap generation failed'
        ]);
    }
    
} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Admin sitemap regeneration error: " . $e->getMessage());
    }
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while regenerating the sitemap',
        'error' => $e->getMessage()
    ]);
}
?>
