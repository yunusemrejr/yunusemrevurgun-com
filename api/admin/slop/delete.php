<?php
/**
 * Slop Delete API
 * Removes the database row and its stored HTML file.
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
require_once dirname(__DIR__, 3) . '/models/Slop.php';
require_once dirname(__DIR__, 3) . '/models/Sitemap.php';
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
    $action = $_POST['action'] ?? '';
    if ($action !== 'delete') throw new Exception('Invalid action');

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid slop ID');

    $slop = new Slop();
    $row = $slop->getSlopById($id);
    if (!$row) throw new Exception('Slop not found');

    if ($slop->deleteSlop($id)) {
        try {
            (new Sitemap())->generateSitemapXML();
        } catch (Exception $sitemapError) {
            error_log('Sitemap snapshot failed after slop delete: ' . $sitemapError->getMessage());
        }
        echo json_encode(['success' => true, 'message' => 'Slop deleted']);
    } else {
        throw new Exception('Failed to delete slop');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
