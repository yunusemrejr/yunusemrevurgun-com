<?php

require_once dirname(__DIR__, 2) . '/config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

if (file_exists('../../global.php')) {
    require_once '../../global.php';
    restrictDirectAccess();
}  

require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Blog.php';
require_once __DIR__ . '/../../models/Updates.php';
require_once __DIR__ . '/../../models/Gallery.php';
require_once __DIR__ . '/../../models/Portfolio.php';
require_once __DIR__ . '/../../models/Music.php';
require_once __DIR__ . '/../../models/Videos.php';
require_once __DIR__ . '/../../models/Downloads.php';
Auth::checkLogin();

$blog = new Blog();
$updates = new Updates();
$gallery = new Gallery();
$portfolio = new Portfolio();
$music = new Music();
$videos = new Videos();
$downloads = new Downloads();

$recentPosts = $blog->getRecentPosts(5);
$recentUpdates = $updates->getUpdates(5);
$recentImages = $gallery->getRecentImages(5);
$recentProjects = $portfolio->getRecentProjects(5);
$recentTracks = $music->getRecentTracks(5);
$recentVideos = $videos->getRecentVideos(5);

$totalPosts = $blog->getTotalPosts();
$totalUpdates = $updates->getTotalUpdates();
$totalImages = $gallery->getTotalImages();
$totalProjects = $portfolio->getTotalProjects();
$totalTracks = $music->getTotalActiveTracks();
$totalVideos = $videos->getTotalActiveVideos();
$totalDownloads = $downloads->getTotalDownloads();

$page = "dashboard";
$pageTitle = "Dashboard";

include __DIR__ . '/includes/header.php';
?>

    <div class="admin-stats-grid">
        <div class="admin-stat-card">
            <i class="bi bi-file-earmark-text admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalPosts ?></h3>
            <p class="admin-stat-label">Blog Posts</p>
            <a href="<?= FULL_BASE_PATH ?>admin/blog" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>
        
        <div class="admin-stat-card">
            <i class="bi bi-clock-history admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalUpdates ?></h3>
            <p class="admin-stat-label">Updates</p>
            <a href="<?= FULL_BASE_PATH ?>admin/updates" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>
        
        <div class="admin-stat-card">
            <i class="bi bi-images admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalImages ?></h3>
            <p class="admin-stat-label">Gallery Images</p>
            <a href="<?= FULL_BASE_PATH ?>admin/gallery" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>
        
        <div class="admin-stat-card">
            <i class="bi bi-briefcase admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalProjects ?></h3>
            <p class="admin-stat-label">Portfolio Projects</p>
            <a href="<?= FULL_BASE_PATH ?>admin/portfolio" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>

        <div class="admin-stat-card">
            <i class="bi bi-music-note-beamed admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalTracks ?></h3>
            <p class="admin-stat-label">Music Tracks</p>
            <a href="<?= FULL_BASE_PATH ?>admin/music" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>
        
        <div class="admin-stat-card">
            <i class="bi bi-camera-reel admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalVideos ?></h3>
            <p class="admin-stat-label">Videos</p>
            <a href="<?= FULL_BASE_PATH ?>admin/videos" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>

        <div class="admin-stat-card">
            <i class="bi bi-box-arrow-down admin-stat-icon"></i>
            <h3 class="admin-stat-number"><?= $totalDownloads ?></h3>
            <p class="admin-stat-label">Downloads</p>
            <a href="<?= FULL_BASE_PATH ?>admin/downloads" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title">System Management</h5>
        </div>
        <div class="admin-card-body">
            <div class="system-grid">
                <div class="system-item">
                    <i class="bi bi-diagram-2 system-item-icon"></i>
                    <div class="system-item-content">
                        <h6 class="system-item-title">Sitemap</h6>
                        <p class="system-item-desc">Regenerate XML sitemap for search engines</p>
                        <button id="regenerate-sitemap-btn" class="admin-btn admin-btn-secondary admin-btn-sm">
                            <i class="bi bi-arrow-clockwise"></i>
                            Regenerate
                        </button>
                    </div>
                </div>
                <div class="system-item">
                    <i class="bi bi-gear system-item-icon"></i>
                    <div class="system-item-content">
                        <h6 class="system-item-title">Settings</h6>
                        <p class="system-item-desc">Manage site configuration and preferences</p>
                        <a href="<?= FULL_BASE_PATH ?>admin/settings" class="admin-btn admin-btn-secondary admin-btn-sm">
                            <i class="bi bi-sliders"></i>
                            Settings
                        </a>
                    </div>
                </div>
                <div class="system-item">
                    <i class="bi bi-download system-item-icon"></i>
                    <div class="system-item-content">
                        <h6 class="system-item-title">Export Data</h6>
                        <p class="system-item-desc">Download all website data as a ZIP archive (content, images, settings)</p>
                        <a href="<?= FULL_BASE_PATH ?>api/admin/export.php" class="admin-btn admin-btn-secondary admin-btn-sm" download>
                            <i class="bi bi-file-zip"></i>
                            Download Export
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-header-row">
                    <h5 class="admin-card-title">Recent Blog Posts</h5>
                    <a href="<?= FULL_BASE_PATH ?>admin/blog/create" class="admin-btn admin-btn-primary admin-btn-sm">New Post</a>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (count($recentPosts) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($recentPosts as $post): ?>
                            <a href="<?= FULL_BASE_PATH ?>admin/blog/edit?id=<?= $post['id'] ?>" class="list-group-item list-group-item-action">
                                <div class="list-item-row">
                                    <h6 class="list-item-title"><?= htmlspecialchars($post['title'] ?? 'Untitled') ?></h6>
                                    <span class="list-item-date"><?= isset($post['created_at']) ? date('M d, Y', strtotime($post['created_at'])) : 'No date' ?></span>
                                </div>
                                <div class="list-item-meta">
                                    <span class="admin-badge admin-badge-<?= isset($post['status']) && $post['status'] === 'published' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($post['status'] ?? 'draft') ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <p>No blog posts yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-header-row">
                    <h5 class="admin-card-title">Recent Updates</h5>
                    <a href="<?= FULL_BASE_PATH ?>admin/updates/create" class="admin-btn admin-btn-primary admin-btn-sm">New Update</a>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (count($recentUpdates) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($recentUpdates as $update): ?>
                            <a href="<?= FULL_BASE_PATH ?>admin/updates/edit?id=<?= $update['id'] ?>" class="list-group-item list-group-item-action">
                                <div class="list-item-row">
                                    <h6 class="list-item-title"><?= htmlspecialchars($update['title'] ?? 'Untitled') ?></h6>
                                    <span class="list-item-date"><?= isset($update['update_date']) ? date('M d, Y', strtotime($update['update_date'])) : 'No date' ?></span>
                                </div>
                                <div class="list-item-meta">
                                    <span class="list-item-excerpt"><?= isset($update['content']) ? htmlspecialchars(substr($update['content'], 0, 100)) . '...' : 'No content' ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">
                        <i class="bi bi-clock-history"></i>
                        <p>No updates yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-header-row">
                    <h5 class="admin-card-title">Recent Gallery Images</h5>
                    <a href="<?= FULL_BASE_PATH ?>admin/gallery" class="admin-btn admin-btn-primary admin-btn-sm">Manage</a>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (count($recentImages) > 0): ?>
                    <div class="dashboard-gallery-grid">
                        <?php foreach ($recentImages as $image): ?>
                            <div class="dashboard-gallery-item">
                                <?php
                                $thumbnailPath = FULL_BASE_PATH . 'uploads/gallery/thumbnails/' . $image['filename'];
                                $originalPath = FULL_BASE_PATH . 'uploads/gallery/' . $image['filename'];
                                $imagePath = file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/gallery/thumbnails/' . $image['filename']) ? $thumbnailPath : $originalPath;
                                ?>
                                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($image['title'] ?? 'Image') ?>" class="dashboard-gallery-img" loading="lazy">
                                <p class="dashboard-gallery-title"><?= htmlspecialchars($image['title'] ?? 'Untitled') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">
                        <i class="bi bi-images"></i>
                        <p>No gallery images yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-header-row">
                    <h5 class="admin-card-title">Recent Portfolio Projects</h5>
                    <a href="<?= FULL_BASE_PATH ?>admin/portfolio/create" class="admin-btn admin-btn-primary admin-btn-sm">New Project</a>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (count($recentProjects) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($recentProjects as $project): ?>
                            <a href="<?= FULL_BASE_PATH ?>admin/portfolio/edit?id=<?= $project['id'] ?>" class="list-group-item list-group-item-action">
                                <div class="list-item-row">
                                    <h6 class="list-item-title"><?= htmlspecialchars($project['title'] ?? 'Untitled') ?></h6>
                                    <span class="list-item-date"><?= isset($project['created_at']) ? date('M d, Y', strtotime($project['created_at'])) : 'No date' ?></span>
                                </div>
                                <div class="list-item-meta">
                                    <span class="list-item-excerpt"><?= isset($project['description']) ? htmlspecialchars(substr($project['description'], 0, 100)) . '...' : 'No description' ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">
                        <i class="bi bi-briefcase"></i>
                        <p>No portfolio projects yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
include __DIR__ . '/includes/footer.php';
?>
