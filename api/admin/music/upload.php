<?php
/**
 * Music Upload API
 * Handles audio file uploads with auto-conversion to MP3
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
require_once dirname(__DIR__, 3) . '/models/Music.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

header('Content-Type: application/json');

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// PHP silently empties $_POST/$_FILES when the body exceeds post_max_size —
// detect it so users get a clear message instead of a bogus CSRF error.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Upload too large for server limits. Try fewer or smaller files.']);
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
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file selected');
    }

    if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . getUploadErrorMessage($_FILES['audio']['error']));
    }

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['audio']['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = [
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav',
        'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg',
        'video/mp4', 'video/x-m4v',
    ];

    if (!in_array($mimeType, $allowedTypes, true)) {
        throw new Exception("Invalid file type (detected: $mimeType). Only MP3, WAV, MP4, AAC, OGG are allowed.");
    }

    // Max file size: 50MB
    $maxSize = 50 * 1024 * 1024;
    if ($_FILES['audio']['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 50MB.');
    }

    $music = new Music();
    $uploadDir = dirname(__DIR__, 3) . '/uploads/music/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $originalName = $_FILES['audio']['name'];
    $tempPath = $_FILES['audio']['tmp_name'];
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = trim($safeBase, '-_.') ?: 'audio-track';
    $uniqueId = bin2hex(random_bytes(8));

    // Convert to MP3 using ffmpeg
    $outputFilename = $uniqueId . '_' . $safeBase . '.mp3';
    $outputPath = $uploadDir . $outputFilename;

    $ffmpegCmd = sprintf(
        'ffmpeg -i %s -codec:a libmp3lame -qscale:a 2 -map_metadata 0 -id3v2_version 3 %s 2>/dev/null',
        escapeshellarg($tempPath),
        escapeshellarg($outputPath)
    );

    $returnCode = 0;
    $output = [];
    exec($ffmpegCmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outputPath)) {
        // If ffmpeg fails, try direct copy for already-MP3 files
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'mp3' && $mimeType === 'audio/mpeg') {
            $outputFilename = $uniqueId . '_' . $safeBase . '.mp3';
            $outputPath = $uploadDir . $outputFilename;
            if (!move_uploaded_file($tempPath, $outputPath)) {
                throw new Exception('Failed to save file');
            }
        } else {
            throw new Exception('Failed to convert audio to MP3. Ensure ffmpeg is installed.');
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
        $duration = floatval($durationOutput[0]);
    }

    $fileSize = filesize($outputPath);
    $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
    if (empty($title)) {
        $title = pathinfo($originalName, PATHINFO_FILENAME);
    }
    $description = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 1000);
    $recordedAt = trim($_POST['recorded_at'] ?? '');
    if (!empty($recordedAt) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $recordedAt)) {
        $recordedAt = $recordedAt;
    } else {
        $recordedAt = null;
    }

    $trackData = [
        'filename' => $outputFilename,
        'original_filename' => $originalName,
        'title' => $title,
        'description' => $description,
        'recorded_at' => $recordedAt,
        'duration' => $duration,
        'file_size' => $fileSize,
        'sort_order' => 0,
    ];

    if ($music->addTrack($trackData)) {
        echo json_encode([
            'success' => true,
            'message' => 'Track uploaded and converted successfully',
            'track' => [
                'filename' => $outputFilename,
                'title' => $title,
                'duration' => $duration,
            ],
        ]);
    } else {
        // Remove file if DB insert failed
        if (file_exists($outputPath)) unlink($outputPath);
        throw new Exception('Failed to save track to database');
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
