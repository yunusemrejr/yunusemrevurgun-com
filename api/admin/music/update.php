<?php
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

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid track ID');

    $music = new Music();
    $track = $music->getTrackById($id);
    if (!$track) throw new Exception('Track not found');

    $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
    $description = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 1000);
    $recordedAt = trim($_POST['recorded_at'] ?? '');
    if (!empty($recordedAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordedAt)) {
        $recordedAt = $recordedAt;
    } else {
        $recordedAt = null;
    }

    if (empty($title)) throw new Exception('Title is required');

    $data = ['title' => $title, 'description' => $description, 'recorded_at' => $recordedAt];
    if (isset($_POST['sort_order'])) {
        $data['sort_order'] = intval($_POST['sort_order']);
    }

    if ($music->updateTrack($id, $data)) {
        echo json_encode(['success' => true, 'message' => 'Track updated successfully']);
    } else {
        throw new Exception('Failed to update track');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
