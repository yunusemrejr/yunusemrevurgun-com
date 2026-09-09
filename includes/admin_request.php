<?php
/** Shared guard for direct APIs and routed admin actions. No database access. */
require_once __DIR__ . '/../models/Auth.php';

function guardAdminRequest(bool $api = false): void {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    $fail = static function (int $status, string $message) use ($api): void {
        http_response_code($status);
        if ($api) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $message]);
        } else {
            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }
        exit;
    };
    if (!Auth::checkLogin(false)) {
        if (!$api) {
            header('Location: ' . getAdminLoginPath());
            exit;
        }
        $fail(401, 'Your session has expired. Please sign in again.');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
        header('Allow: GET, HEAD, POST');
        $fail(405, 'Method not allowed.');
    }
    if ($method !== 'POST') return;
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        $fail(403, 'Please submit this action from the admin panel.');
    }
    // PHP discards both form fields and files when post_max_size is exceeded.
    $limit = trim(ini_get('post_max_size'));
    $bytes = (float)$limit * match (strtolower(substr($limit, -1))) {
        'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1,
    };
    if ($bytes > 0 && (float)($_SERVER['CONTENT_LENGTH'] ?? 0) > $bytes) {
        $fail(413, 'The upload exceeds the server size limit. Choose a smaller file.');
    }
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $fail(403, 'Security token expired. Reload the page before trying again.');
    }
    // These common scalar fields must never reach trim/hash/database APIs as arrays.
    foreach ($_POST as $key => $value) {
        if (is_array($value) && in_array($key, ['id', 'action', 'title', 'slug', 'status', 'csrf_token', 'username', 'password'], true)) {
            $fail(422, 'Invalid form field: ' . $key . '.');
        }
    }
}
