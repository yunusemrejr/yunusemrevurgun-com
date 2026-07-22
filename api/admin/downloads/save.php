<?php
/**
 * Downloads Save API
 * Creates or updates a downloadable app entry (admin only).
 * Accepts an optional thumbnail image upload (raster only). Files are never
 * hosted here — `download_url` references an external release (e.g. GitHub).
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
require_once dirname(__DIR__, 3) . '/models/Downloads.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// PHP silently empties $_POST/$_FILES when the body exceeds post_max_size.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Upload too large for server limits.']);
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

/**
 * Validate and store an uploaded thumbnail. Returns the stored filename.
 * Raster images only (SVG rejected to avoid any script/XSS surface).
 */
function storeDownloadThumbnail(array $file, string $uploadDir): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Thumbnail upload error (code ' . $file['error'] . ').');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($mimeToExt[$mime])) {
        throw new Exception('Invalid thumbnail type (detected: ' . $mime . '). Use JPG, PNG, WebP, or GIF.');
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Thumbnail too large. Maximum size is 5MB.');
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $safeBase = trim($safeBase, '-_.') ?: 'thumbnail';
    $filename = bin2hex(random_bytes(8)) . '_' . $safeBase . '.' . $mimeToExt[$mime];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        throw new Exception('Failed to save thumbnail.');
    }
    return $filename;
}

try {
    $downloads = new Downloads();
    $id = intval($_POST['id'] ?? 0);
    $existing = $id > 0 ? $downloads->getDownloadById($id) : null;
    if ($id > 0 && !$existing) {
        throw new Exception('Download not found');
    }

    $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
    if ($title === '') {
        throw new Exception('Title is required');
    }

    $downloadUrl = trim((string)($_POST['download_url'] ?? ''));
    if ($downloadUrl === '' || !filter_var($downloadUrl, FILTER_VALIDATE_URL) ||
        !in_array(parse_url($downloadUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
        throw new Exception('A valid http(s) download URL is required');
    }

    $description = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 4000);
    $platforms = Downloads::encodeList($_POST['platforms'] ?? '');
    $dependencies = Downloads::encodeList($_POST['dependencies'] ?? '');

    $uploadDir = dirname(__DIR__, 3) . '/' . Downloads::UPLOAD_DIR;
    $data = [
        'title' => $title,
        'description' => $description,
        'download_url' => $downloadUrl,
        'platforms' => $platforms,
        'dependencies' => $dependencies,
    ];

    // Thumbnail handling: new upload replaces the old one; explicit flag removes it.
    $oldThumbnail = $existing['thumbnail'] ?? null;
    $newThumbnail = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
        $newThumbnail = storeDownloadThumbnail($_FILES['thumbnail'], $uploadDir);
    }

    if ($existing) {
        if ($newThumbnail !== null) {
            $data['thumbnail'] = $newThumbnail;
        } elseif (!empty($_POST['remove_thumbnail'])) {
            $data['thumbnail'] = null;
        }
        if (!$downloads->updateDownload($id, $data)) {
            if ($newThumbnail && is_file($uploadDir . '/' . $newThumbnail)) unlink($uploadDir . '/' . $newThumbnail);
            throw new Exception('Failed to update download');
        }
        // Delete the replaced/removed thumbnail file after a successful save.
        $replaced = ($newThumbnail !== null || !empty($_POST['remove_thumbnail'])) ? $oldThumbnail : null;
        if ($replaced && is_file($uploadDir . '/' . $replaced)) {
            unlink($uploadDir . '/' . $replaced);
        }
        echo json_encode(['success' => true, 'message' => 'Download updated successfully']);
    } else {
        if ($newThumbnail !== null) {
            $data['thumbnail'] = $newThumbnail;
        }
        if (!$downloads->addDownload($data)) {
            if ($newThumbnail && is_file($uploadDir . '/' . $newThumbnail)) unlink($uploadDir . '/' . $newThumbnail);
            throw new Exception('Failed to add download');
        }
        echo json_encode(['success' => true, 'message' => 'Download added successfully']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
