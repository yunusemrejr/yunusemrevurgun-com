<?php
/**
 * Videos Update API
 * Handles updating video metadata and adding URL-based videos.
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
    $action = $_POST['action'] ?? '';
    $videos = new Videos();

    if ($action === 'add_url') {
        // Add a URL-based video (YouTube, Odysee, etc.)
        $url = trim($_POST['url'] ?? '');
        if (empty($url)) throw new Exception('URL is required');

        $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
        if (empty($title)) throw new Exception('Title is required');

        $description = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 2000);

        // Parse URL to determine platform and extract video ID
        $parsed = parseVideoUrl($url);

        $videoData = [
            'title' => $title,
            'description' => $description,
            'type' => 'url',
            'url' => $url,
            'platform' => $parsed['platform'],
            'video_id' => $parsed['video_id'],
            'thumbnail_url' => $parsed['thumbnail_url'],
            'duration' => 0,
            'file_size' => 0,
        ];

        if ($videos->addVideo($videoData)) {
            echo json_encode(['success' => true, 'message' => 'Video added successfully']);
        } else {
            throw new Exception('Failed to add video to database');
        }
    } else {
        // Update existing video metadata
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid video ID');

        $video = $videos->getVideoById($id);
        if (!$video) throw new Exception('Video not found');

        $data = [];
        $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
        if (!empty($title)) $data['title'] = $title;
        if (isset($_POST['description'])) {
            $data['description'] = mb_substr(trim(strip_tags((string)($_POST['description'] ?? ''))), 0, 2000);
        }

        // For URL videos, allow updating the URL
        if ($video['type'] === 'url' && !empty($_POST['url'])) {
            $url = trim($_POST['url']);
            $parsed = parseVideoUrl($url);
            $data['url'] = $url;
            $data['platform'] = $parsed['platform'];
            $data['video_id'] = $parsed['video_id'];
            $data['thumbnail_url'] = $parsed['thumbnail_url'];
        }

        if (empty($data)) throw new Exception('No data to update');

        if ($videos->updateVideo($id, $data)) {
            echo json_encode(['success' => true, 'message' => 'Video updated successfully']);
        } else {
            throw new Exception('Failed to update video');
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Parse a video URL to determine platform, extract video ID, and generate thumbnail URL.
 * Supports YouTube and Odysee.
 */
function parseVideoUrl(string $url): array {
    $result = [
        'platform' => 'unknown',
        'video_id' => '',
        'thumbnail_url' => '',
    ];

    // YouTube
    $youtubePatterns = [
        '#(?:https?://)?(?:www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_-]{11})#',
        '#(?:https?://)?(?:www\.)?youtu\.be/([a-zA-Z0-9_-]{11})#',
        '#(?:https?://)?(?:www\.)?youtube\.com/embed/([a-zA-Z0-9_-]{11})#',
        '#(?:https?://)?(?:www\.)?youtube\.com/shorts/([a-zA-Z0-9_-]{11})#',
    ];
    foreach ($youtubePatterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            $result['platform'] = 'youtube';
            $result['video_id'] = $matches[1];
            $result['thumbnail_url'] = 'https://img.youtube.com/vi/' . $matches[1] . '/hqdefault.jpg';
            return $result;
        }
    }

    // Odysee
    $odyseePatterns = [
        '#(?:https?://)?(?:www\.)?odysee\.com/\$/(?:embed|download)/([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)#',
        '#(?:https?://)?(?:www\.)?odysee\.com/(@[a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)(?:\?|$|#)#',
    ];
    foreach ($odyseePatterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            $result['platform'] = 'odysee';
            // For Odysee, store the full content path
            if (count($matches) >= 3) {
                $result['video_id'] = $matches[1] . '/' . $matches[2];
            } else {
                $result['video_id'] = $matches[1];
            }
            return $result;
        }
    }

    // Generic: store the URL as-is
    $result['platform'] = 'other';
    $result['video_id'] = $url;

    return $result;
}
