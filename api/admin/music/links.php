<?php
/**
 * Music Links API
 * CRUD operations for external platform links (Spotify, Apple Music, etc.)
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
require_once dirname(__DIR__, 3) . '/models/Music.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $music = new Music();

    // GET: list all links
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $links = $music->getAllLinks();
        echo json_encode(['success' => true, 'links' => $links]);
        exit;
    }

    // POST: create, update, or delete
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $platform = trim($_POST['platform'] ?? 'other');
            $description = trim($_POST['description'] ?? '');

            if ($title === '' || $url === '') {
                throw new Exception('Title and URL are required');
            }

            // Basic URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL format');
            }

            $result = $music->addLink([
                'title' => $title,
                'url' => $url,
                'platform' => $platform,
                'description' => $description
            ]);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Link added successfully' : 'Failed to add link'
            ]);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                throw new Exception('Invalid link ID');
            }

            $data = [];
            if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
            if (isset($_POST['url'])) {
                $url = trim($_POST['url']);
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid URL format');
                }
                $data['url'] = $url;
            }
            if (isset($_POST['platform'])) $data['platform'] = $_POST['platform'];
            if (isset($_POST['description'])) $data['description'] = trim($_POST['description']);

            if (empty($data)) throw new Exception('No data to update');

            $result = $music->updateLink((int)$id, $data);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Link updated successfully' : 'Failed to update link'
            ]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                throw new Exception('Invalid link ID');
            }

            $result = $music->deleteLink((int)$id);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Link deleted successfully' : 'Failed to delete link'
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
