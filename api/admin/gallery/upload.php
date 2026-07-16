<?php
/**
 * Gallery Upload API
 * Handles image uploads for the gallery
 */

// Keep upload failures JSON-only in production.
error_reporting(E_ALL);
ini_set('display_errors', getenv('MODE') === 'development' ? '1' : '0');

// Allow API access but prevent direct browser access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && 
    (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Include required files
require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Gallery.php';
require_once dirname(__DIR__, 3) . '/includes/csrf.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
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

// Validate CSRF token
if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check if files were uploaded
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        throw new Exception('No images selected');
    }
    
    $gallery = new Gallery();
    $uploadedFiles = [];
    $errors = [];
    
    // Get image title
    $imageTitle = mb_substr(trim(strip_tags((string)($_POST['imageTitle'] ?? 'Untitled'))), 0, 120);
    $imageTitle = $imageTitle !== '' ? $imageTitle : 'Untitled';
    
    // Get album_id if provided
    $albumId = isset($_POST['album_id']) && $_POST['album_id'] !== '' && $_POST['album_id'] !== '0' ? (int)$_POST['album_id'] : null;
    
    // Process each uploaded file
    $fileCount = count($_FILES['images']['name']);
    if ($fileCount > 12) {
        throw new Exception('Please upload 12 images or fewer at once.');
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        // Check for upload errors
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "File " . ($i + 1) . ": " . getUploadErrorMessage($_FILES['images']['error'][$i]);
            continue;
        }

        // Validate file type using finfo (not client-supplied $_FILES type)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['images']['tmp_name'][$i]);
        finfo_close($finfo);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];

        if (!in_array($mimeType, $allowedTypes, true)) {
            $errors[] = "File " . ($i + 1) . ": Invalid file content (detected: $mimeType). Only JPEG, PNG, GIF, WebP, and HEIC are allowed.";
            continue;
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['images']['size'][$i] > $maxSize) {
            $errors[] = "File " . ($i + 1) . ": File too large. Maximum size is 5MB.";
            continue;
        }

        // Generate unique filename
        $originalName = $_FILES['images']['name'][$i];
        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ];
        $extension = $extensionByMime[$mimeType] ?? strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Handle HEIC/HEIF conversion to JPEG
        $processingSource = $_FILES['images']['tmp_name'][$i];
        $removeProcessingSource = false;

        if (in_array($mimeType, ['image/heic', 'image/heif'])) {
            $convertedPath = convertHeicToJpeg($processingSource);
            if ($convertedPath === false) {
                $errors[] = "File " . ($i + 1) . ": HEIC/HEIF format cannot be processed on this server. Please convert to JPEG before uploading.";
                continue;
            }
            $processingSource = $convertedPath;
            $removeProcessingSource = true;
            $extension = 'jpg';
            $originalName = pathinfo($originalName, PATHINFO_FILENAME) . '.jpg';
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBase = trim($safeBase, '-_.') ?: 'gallery-image';
        $filename = bin2hex(random_bytes(10)) . '_' . $safeBase . '.' . $extension;

        // Set upload path using absolute path resolution
        $uploadDir = dirname(__DIR__, 3) . '/uploads/gallery/';
        $uploadPath = $uploadDir . $filename;

        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move file to target (handles both original uploads and converted files)
        $fileMoved = $removeProcessingSource
            ? rename($processingSource, $uploadPath)
            : move_uploaded_file($processingSource, $uploadPath);

        if ($fileMoved) {
            // Add to database
            $imageData = [
                'filename' => $filename,
                'title' => $imageTitle,
                'album_id' => $albumId,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            if ($gallery->addImage($imageData)) {
                $uploadedFiles[] = $filename;
            } else {
                // Remove file if database insert failed
                unlink($uploadPath);
                $errors[] = "File " . ($i + 1) . ": Failed to save to database";
            }
        } else {
            $errors[] = "File " . ($i + 1) . ": Failed to upload file";
        }

        // Clean up any remaining temp file from conversion
        if ($removeProcessingSource && file_exists($processingSource)) {
            unlink($processingSource);
        }
    }

    // Prepare response
    $response = [
        'success' => count($uploadedFiles) > 0,
        'uploaded_count' => count($uploadedFiles),
        'total_count' => $fileCount,
        'uploaded_files' => $uploadedFiles
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = count($uploadedFiles) . ' files uploaded successfully, ' . count($errors) . ' failed';
    } else {
        $response['message'] = 'All files uploaded successfully';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload failed: ' . $e->getMessage()
    ]);
}

/**
 * Convert HEIC/HEIF image to JPEG
 * Tries Imagick extension first, falls back to exec(convert)
 */
function convertHeicToJpeg(string $sourcePath): string|false
{
    // Method 1: Imagick extension
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($sourcePath);
            $format = $imagick->getImageFormat();
            if (in_array(strtoupper($format), ['HEIC', 'HEIF'])) {
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $tempPath = tempnam(sys_get_temp_dir(), 'heic_') . '.jpg';
                $imagick->writeImage($tempPath);
                $imagick->clear();
                return $tempPath;
            }
        } catch (Exception $e) {
            // Fall through to next method
        }
    }

    // Method 2: exec() with ImageMagick convert command
    $whichConvert = trim(shell_exec('which convert 2>/dev/null') ?? '');
    if (!empty($whichConvert) && is_executable($whichConvert)) {
        $tempPath = tempnam(sys_get_temp_dir(), 'heic_') . '.jpg';
        $cmd = escapeshellcmd($whichConvert) . ' '
            . escapeshellarg($sourcePath) . ' '
            . '-quality 90 '
            . escapeshellarg($tempPath)
            . ' 2>/dev/null';
        exec($cmd, $output, $returnCode);
        if ($returnCode === 0 && file_exists($tempPath) && filesize($tempPath) > 0) {
            return $tempPath;
        }
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    return false;
}

/**
 * Get human-readable upload error message
 */
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
?>
