<?php
/**
 * Mastodon Retry API
 * Re-publishes an existing website update to Mastodon (admin only).
 * Uses the SAME idempotency key as the original attempt, so a retry after a
 * transient failure can never create a duplicate status. Never rolls back or
 * deletes the local update on failure.
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
require_once dirname(__DIR__, 3) . '/services/MastodonService.php';
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

    $mastodon = new MastodonService();
    if (!$mastodon->isConfigured()) {
        throw new Exception('Mastodon is not configured (MASTODON_BASE_URL / MASTODON_ACCESS_TOKEN missing).');
    }

    // Reuse the stored idempotency key (fall back to the deterministic one).
    $key = !empty($update['mastodon_idempotency_key'])
        ? $update['mastodon_idempotency_key']
        : MastodonService::idempotencyKeyForUpdate($id);
    if (empty($update['mastodon_idempotency_key'])) {
        $updates->setMastodonIdempotencyKey($id, $key);
    }

    $updates->incrementMastodonAttempt($id);

    $pub = $mastodon->publish(
        (string) $update['title'],
        (string) $update['description'],
        Updates::canonicalUpdateUrl($id),
        $key
    );

    if ($pub['success']) {
        $updates->updateMastodonSync($id, [
            'mastodon_status_id' => $pub['status_id'],
            'mastodon_status_url' => $pub['status_url'],
            'mastodon_sync_status' => 'published',
            'mastodon_last_error' => null,
            'mastodon_published_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Published to Mastodon.',
            'status_url' => $pub['status_url'],
        ]);
    } else {
        $updates->updateMastodonSync($id, [
            'mastodon_sync_status' => 'failed',
            'mastodon_last_error' => $pub['error'],
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
