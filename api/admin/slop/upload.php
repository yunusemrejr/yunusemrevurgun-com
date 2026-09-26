<?php
/**
 * Slop Upload API
 * Handles single-file HTML uploads for the Quality 3D AI Slop section.
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

// PHP silently empties $_POST/$_FILES when the body exceeds post_max_size
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

try {
    if (!isset($_FILES['slop']) || $_FILES['slop']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file selected');
    }

    if ($_FILES['slop']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . getSlopUploadErrorMessage($_FILES['slop']['error']));
    }

    $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
    if ($title === '') {
        throw new Exception('Title is required');
    }
    $description = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 2000);

    $originalName = (string)$_FILES['slop']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['html', 'htm'], true)) {
        throw new Exception('Only .html files are allowed.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $_FILES['slop']['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    if (!in_array($mimeType, ['text/html', 'text/plain', 'application/xhtml+xml'], true)) {
        throw new Exception("Invalid file type (detected: $mimeType). Only HTML files are allowed.");
    }

    if ($_FILES['slop']['size'] > Slop::MAX_FILE_SIZE) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }
    if ($_FILES['slop']['size'] === 0) {
        throw new Exception('File is empty.');
    }

    // Content sniff: it must look like an HTML document, and must not carry
    // server-side code that could confuse a host that parses uploads as PHP.
    $peek = file_get_contents($_FILES['slop']['tmp_name'], false, null, 0, 65536);
    if ($peek === false) {
        throw new Exception('Could not read the uploaded file.');
    }
    if (!preg_match('/<!doctype|<html[\s>]|<head[\s>]|<body[\s>]/i', $peek)) {
        throw new Exception('File does not look like an HTML document.');
    }
    if (preg_match('/<\?(php|=|\s)|<%/i', $peek)) {
        throw new Exception('Server-side code is not allowed in slop files.');
    }

    $uploadDir = dirname(__DIR__, 3) . '/' . Slop::UPLOAD_DIR . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Upload directory is not writable.');
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = trim($safeBase, '-_.') ?: 'slop';
    $filename = bin2hex(random_bytes(8)) . '_' . mb_substr($safeBase, 0, 80) . '.html';
    $outputPath = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['slop']['tmp_name'], $outputPath)) {
        throw new Exception('Failed to save file');
    }
    chmod($outputPath, 0644);

    $slop = new Slop();
    $id = $slop->addSlop([
        'title' => $title,
        'description' => $description,
        'filename' => $filename,
        'original_filename' => $originalName,
        'file_size' => filesize($outputPath),
    ]);

    if ($id === false) {
        if (file_exists($outputPath)) unlink($outputPath);
        throw new Exception('Failed to save slop to database');
    }

    try {
        (new Sitemap())->generateSitemapXML();
    } catch (Exception $sitemapError) {
        error_log('Sitemap snapshot failed after slop upload: ' . $sitemapError->getMessage());
    }

    $row = $slop->getSlopById($id);
    echo json_encode([
        'success' => true,
        'message' => 'Slop uploaded successfully',
        'slop' => [
            'id' => $id,
            'title' => $title,
            'slug' => $row['slug'] ?? '',
            'url' => FULL_BASE_PATH . 'slop/' . rawurlencode($row['slug'] ?? ''),
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload failed: ' . $e->getMessage(),
    ]);
}

function getSlopUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}
