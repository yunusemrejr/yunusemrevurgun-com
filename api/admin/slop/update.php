<?php
/**
 * Slop Update API
 * Handles metadata edits and test-example seeding.
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
    $slop = new Slop();

    if ($action === 'seed_example') {
        $id = $slop->seedExample();
        try {
            (new Sitemap())->generateSitemapXML();
        } catch (Exception $sitemapError) {
            error_log('Sitemap snapshot failed after slop seed: ' . $sitemapError->getMessage());
        }
        $row = $slop->getSlopById($id);
        echo json_encode([
            'success' => true,
            'message' => 'Test example added',
            'slop' => ['id' => $id, 'slug' => $row['slug'] ?? ''],
        ]);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid slop ID');

    $row = $slop->getSlopById($id);
    if (!$row) throw new Exception('Slop not found');

    $data = [];
    if (array_key_exists('title', $_POST)) {
        $title = mb_substr(trim(strip_tags((string)$_POST['title'])), 0, 255);
        if ($title === '') throw new Exception('Title is required');
        $data['title'] = $title;
    }
    if (array_key_exists('description', $_POST)) {
        $data['description'] = mb_substr(trim(strip_tags((string)$_POST['description'])), 0, 2000);
    }
    if (empty($data)) throw new Exception('No data to update');

    if ($slop->updateSlop($id, $data)) {
        try {
            (new Sitemap())->generateSitemapXML();
        } catch (Exception $sitemapError) {
            error_log('Sitemap snapshot failed after slop update: ' . $sitemapError->getMessage());
        }
        echo json_encode(['success' => true, 'message' => 'Slop updated successfully']);
    } else {
        throw new Exception('Failed to update slop');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
