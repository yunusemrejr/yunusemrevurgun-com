<?php
/**
 * Videos Upload API
 * Handles video file uploads. For URL-based videos, see add.php.
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
require_once dirname(__DIR__, 3) . '/models/Videos.php';
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
    if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file selected');
    }

    if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . getUploadErrorMessage($_FILES['video']['error']));
    }

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['video']['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = [
        'video/mp4', 'video/x-m4v', 'video/webm', 'video/ogg',
        'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv',
    ];

    if (!in_array($mimeType, $allowedTypes, true)) {
        throw new Exception("Invalid file type (detected: $mimeType). Only MP4, WebM, OGG, MOV, AVI, WMV are allowed.");
    }

    // Max file size: 200MB
    $maxSize = 200 * 1024 * 1024;
    if ($_FILES['video']['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 200MB.');
    }

    $videos = new Videos();
    $uploadDir = dirname(__DIR__, 3) . '/uploads/videos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $originalName = $_FILES['video']['name'];
    $tempPath = $_FILES['video']['tmp_name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = trim($safeBase, '-_.') ?: 'video';
    $uniqueId = bin2hex(random_bytes(8));
    $outputFilename = $uniqueId . '_' . $safeBase . '.' . ($ext === 'mp4' ? 'mp4' : 'mp4');
    $outputPath = $uploadDir . $outputFilename;

    // Re-encode to MP4 with H.264/AAC for broad compatibility
    $ffmpegCmd = sprintf(
        'ffmpeg -i %s -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k -movflags +faststart %s 2>/dev/null',
        escapeshellarg($tempPath),
        escapeshellarg($outputPath)
    );

    $returnCode = 0;
    $output = [];
    exec($ffmpegCmd, $output, $returnCode);

    // If ffmpeg conversion fails but file was already MP4 with H.264, copy directly
    if ($returnCode !== 0 || !file_exists($outputPath)) {
        // Try direct move if it's already a compatible MP4
        if ($ext === 'mp4' && $mimeType === 'video/mp4') {
            $outputFilename = $uniqueId . '_' . $safeBase . '.mp4';
            $outputPath = $uploadDir . $outputFilename;
            if (!move_uploaded_file($tempPath, $outputPath)) {
                throw new Exception('Failed to save file');
            }
        } else {
            throw new Exception('Failed to convert video. Ensure ffmpeg is installed with libx264 and aac support.');
        }
    }

    // Get duration using ffprobe
    $duration = 0;
    $ffprobeCmd = sprintf(
        'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
        escapeshellarg($outputPath)
    );
    $durationOutput = [];
    exec($ffprobeCmd, $durationOutput, $durCode);
    if ($durCode === 0 && !empty($durationOutput[0])) {
        $duration = intval(round(floatval($durationOutput[0])));
    }

    $fileSize = filesize($outputPath);
    $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
    if (empty($title)) {
        $title = pathinfo($originalName, PATHINFO_FILENAME);
    }
    $description = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 2000);

    $videoData = [
        'title' => $title,
        'description' => $description,
        'type' => 'upload',
        'filename' => $outputFilename,
        'original_filename' => $originalName,
        'duration' => $duration,
        'file_size' => $fileSize,
    ];

    if ($videos->addVideo($videoData)) {
        echo json_encode([
            'success' => true,
            'message' => 'Video uploaded successfully',
            'video' => [
                'filename' => $outputFilename,
                'title' => $title,
                'duration' => $duration,
            ],
        ]);
    } else {
        if (file_exists($outputPath)) unlink($outputPath);
        throw new Exception('Failed to save video to database');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload failed: ' . $e->getMessage(),
    ]);
}

function getUploadErrorMessage($errorCode) {
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
