<?php
/**
 * Socials Save API
 * Creates or updates a social profile entry (admin only).
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
require_once dirname(__DIR__, 3) . '/models/Socials.php';
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
    $socials = new Socials();
    $id = intval($_POST['id'] ?? 0);
    $existing = $id > 0 ? $socials->getById($id) : null;
    if ($id > 0 && !$existing) {
        throw new Exception('Social not found');
    }

    $name = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 100);
    if ($name === '') {
        throw new Exception('Name is required');
    }

    $kind = in_array($_POST['kind'] ?? '', Socials::allowedKinds(), true)
        ? $_POST['kind'] : Socials::KIND_LINK;

    $url = trim((string)($_POST['url'] ?? ''));
    if ($kind === Socials::KIND_LINK) {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new Exception('A valid http(s) URL is required for link-type socials');
        }
        $url = mb_substr($url, 0, 500);
    } else {
        // Popup entries (e.g. the X/Twitter joke button) have no URL.
        $url = '';
    }

    $visibility = in_array($_POST['visibility'] ?? '', Socials::allowedVisibilities(), true)
        ? $_POST['visibility'] : Socials::VIS_BOTH;
    $handle = mb_substr(trim(strip_tags((string)($_POST['handle'] ?? ''))), 0, 150);
    $icon = in_array($_POST['icon'] ?? '', Socials::allowedIcons(), true)
        ? $_POST['icon'] : 'link';
    $active = !empty($_POST['active']) ? 1 : 0;
    $sortOrder = max(0, min(9999, intval($_POST['sort_order'] ?? 0)));

    $data = [
        'name' => $name,
        'url' => $url,
        'handle' => $handle,
        'icon' => $icon,
        'kind' => $kind,
        'visibility' => $visibility,
        'active' => $active,
        'sort_order' => $sortOrder,
    ];

    if ($existing) {
        if (!$socials->update($id, $data)) {
            throw new Exception('Failed to update social');
        }
        echo json_encode(['success' => true, 'message' => 'Social updated successfully']);
    } else {
        if (!$socials->add($data)) {
            throw new Exception('Failed to add social');
        }
        echo json_encode(['success' => true, 'message' => 'Social added successfully']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
