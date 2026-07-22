<?php
/**
 * Admin Export API
 * Exports all website data (content, images, videos, music, SEO metadata)
 * as a single ZIP file.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Must be admin-authenticated
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/global.php';
require_once dirname(__DIR__, 2) . '/models/Auth.php';

if (!Auth::checkLogin()) {
    http_response_code(401);
    echo 'Authentication required';
    exit;
}

// Increase execution time for large exports
set_time_limit(120);
ini_set('memory_limit', '256M');

// Load all models
require_once dirname(__DIR__, 2) . '/models/Blog.php';
require_once dirname(__DIR__, 2) . '/models/Updates.php';
require_once dirname(__DIR__, 2) . '/models/Gallery.php';
require_once dirname(__DIR__, 2) . '/models/Portfolio.php';
require_once dirname(__DIR__, 2) . '/models/Music.php';
require_once dirname(__DIR__, 2) . '/models/Travel.php';
require_once dirname(__DIR__, 2) . '/models/Videos.php';
require_once dirname(__DIR__, 2) . '/models/Downloads.php';
require_once dirname(__DIR__, 2) . '/models/Settings.php';

$blog = new Blog();
$updates = new Updates();
$gallery = new Gallery();
$portfolio = new Portfolio();
$music = new Music();
$travel = new Travel();
$videos = new Videos();
$downloads = new Downloads();
$settings = new Settings();

// Create a temp directory for the export
$exportDir = sys_get_temp_dir() . '/yev-export-' . bin2hex(random_bytes(8));
@mkdir($exportDir, 0755, true);

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $f) {
        $path = $dir . '/' . $f;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

try {
    // ==================== 1. Export Database Content as JSON ====================

    $dataDir = $exportDir . '/data';
    @mkdir($dataDir, 0755, true);

    // Blog posts
    $allPosts = $blog->getAllPosts();
    file_put_contents($dataDir . '/blog-posts.json', json_encode($allPosts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Updates
    $allUpdates = $updates->getAllUpdates();
    file_put_contents($dataDir . '/updates.json', json_encode($allUpdates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Gallery
    $galleryImages = $gallery->getAllImages(true);
    $galleryAlbums = $gallery->getAllAlbums();
    file_put_contents($dataDir . '/gallery-images.json', json_encode($galleryImages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($dataDir . '/gallery-albums.json', json_encode($galleryAlbums, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Portfolio
    $allProjects = $portfolio->getAllProjects();
    file_put_contents($dataDir . '/portfolio-projects.json', json_encode($allProjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Music
    $allTracks = $music->getAllTracks(true);
    file_put_contents($dataDir . '/music-tracks.json', json_encode($allTracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $allLinks = $music->getAllLinks();
    file_put_contents($dataDir . '/music-links.json', json_encode($allLinks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Videos
    $allVideos = $videos->getAllVideos(true);
    file_put_contents($dataDir . '/videos.json', json_encode($allVideos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Downloads (metadata only — binaries live on GitHub)
    $allDownloads = $downloads->getAllDownloads();
    file_put_contents($dataDir . '/downloads.json', json_encode($allDownloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Travel
    $travelLocations = $travel->getAllLocations();
    $travelData = [];
    foreach ($travelLocations as $loc) {
        $loc['images'] = $travel->getImagesByLocation($loc['id']);
        $travelData[] = $loc;
    }
    file_put_contents($dataDir . '/travel-locations.json', json_encode($travelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Settings / SEO metadata
    $allSettings = $settings->getAllSettings();
    file_put_contents($dataDir . '/settings.json', json_encode($allSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // ==================== 2. Export Manifest & SEO files ====================

    $rootDir = dirname(__DIR__, 2);

    $extraFiles = ['robots.txt', 'manifest.json', 'sitemap.xml', '.htaccess'];
    foreach ($extraFiles as $ef) {
        $efPath = $rootDir . '/' . $ef;
        if (file_exists($efPath)) {
            copy($efPath, $exportDir . '/' . $ef);
        }
    }

    // ==================== 3. Copy Uploaded Files ====================

    function copyDir($src, $dst, $maxFiles = 500) {
        if (!is_dir($src)) return;
        @mkdir($dst, 0755, true);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $count = 0;
        foreach ($files as $file) {
            if ($count >= $maxFiles) break;
            $destPath = $dst . '/' . $files->getSubPathname();
            if ($file->isDir()) {
                @mkdir($destPath, 0755, true);
            } else {
                copy($file->getRealPath(), $destPath);
            }
            $count++;
        }
    }

    // Gallery images (limit to 500 files)
    if (is_dir($rootDir . '/uploads/gallery')) {
        copyDir($rootDir . '/uploads/gallery', $exportDir . '/uploads/gallery');
    }

    // Travel images
    if (is_dir($rootDir . '/uploads/travel')) {
        copyDir($rootDir . '/uploads/travel', $exportDir . '/uploads/travel');
    }

    // Music files
    if (is_dir($rootDir . '/uploads/music')) {
        copyDir($rootDir . '/uploads/music', $exportDir . '/uploads/music');
    }

    // Videos (uploaded files only, not URL references)
    if (is_dir($rootDir . '/uploads/videos')) {
        copyDir($rootDir . '/uploads/videos', $exportDir . '/uploads/videos');
    }

    // Download thumbnails
    if (is_dir($rootDir . '/uploads/downloads')) {
        copyDir($rootDir . '/uploads/downloads', $exportDir . '/uploads/downloads');
    }

    // Static assets (only travel images that might be referenced)
    if (is_dir($rootDir . '/assets/images/travel')) {
        copyDir($rootDir . '/assets/images/travel', $exportDir . '/assets/images/travel');
    }

    // ==================== 4. Create ZIP ====================

    $zipPath = sys_get_temp_dir() . '/yev-export-' . bin2hex(random_bytes(8)) . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Failed to create ZIP archive');
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($exportDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isFile()) continue;
        $relativePath = substr($file->getRealPath(), strlen($exportDir) + 1);
        $zip->addFile($file->getRealPath(), $relativePath);
    }

    $zip->close();

    // ==================== 5. Stream ZIP to browser ====================

    $zipSize = filesize($zipPath);
    $filename = 'yunusemrevurgun-export-' . date('Y-m-d-His') . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $zipSize);
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    readfile($zipPath);

    // ==================== 6. Cleanup ====================

    unlink($zipPath);
    rrmdir($exportDir);
    exit;

} catch (Exception $e) {
    // Cleanup on error
    if (file_exists($zipPath ?? '')) unlink($zipPath);
    rrmdir($exportDir);

    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Export failed: ' . $e->getMessage();
    exit;
}
?>
