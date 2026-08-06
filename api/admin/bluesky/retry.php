<?php
/**
 * Bluesky Retry API
 * Re-publishes an existing website update to Bluesky (admin only).
 * Uses the SAME deterministic record key (rkey) as the original attempt, so a
 * retry can never create a duplicate post — an already-existing record is
 * recovered, not recreated. Never rolls back or deletes the local update.
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
require_once dirname(__DIR__, 3) . '/models/Updates.php';
require_once dirname(__DIR__, 3) . '/services/BlueskyService.php';
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
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Invalid update ID');

    $updates = new Updates();
    $update = $updates->getUpdateById($id);
    if (!$update) throw new Exception('Update not found');

    // Already published? Report the existing post instead of re-posting.
    if (($update['bluesky_sync_status'] ?? '') === 'published' && !empty($update['bluesky_status_url'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Already published to Bluesky.',
            'status_url' => $update['bluesky_status_url'],
        ]);
        exit;
    }

    $bluesky = new BlueskyService();
    if (!$bluesky->isConfigured()) {
        throw new Exception('Bluesky is not configured (BLUESKY_HANDLE / BLUESKY_APP_PASSWORD missing).');
    }

    // Reuse the stored record key (fall back to the deterministic one).
    $rkey = !empty($update['bluesky_idempotency_key'])
        ? $update['bluesky_idempotency_key']
        : BlueskyService::rkeyForUpdate($id);
    if (empty($update['bluesky_idempotency_key'])) {
        $updates->setBlueskyIdempotencyKey($id, $rkey);
    }

    $updates->incrementBlueskyAttempt($id);

    $pub = $bluesky->publish(
        (string) $update['title'],
        (string) $update['description'],
        Updates::canonicalUpdateUrl($id),
        $rkey
    );

    if ($pub['success']) {
        $updates->updateBlueskySync($id, [
            'bluesky_status_uri' => $pub['status_uri'],
            'bluesky_status_url' => $pub['status_url'],
            'bluesky_sync_status' => 'published',
            'bluesky_last_error' => null,
            'bluesky_published_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Published to Bluesky.',
            'status_url' => $pub['status_url'],
        ]);
    } else {
        $updates->updateBlueskySync($id, [
            'bluesky_sync_status' => 'failed',
            'bluesky_last_error' => $pub['error'],
        ]);
        echo json_encode([
            'success' => false,
            'message' => $pub['error'],
            'retryable' => $pub['retryable'],
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
