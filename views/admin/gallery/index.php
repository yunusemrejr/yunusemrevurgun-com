<?php
require_once __DIR__ . '/../../../config/setPath.php';

require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Gallery.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$gallery = new Gallery();

$tab = isset($_GET['tab']) && $_GET['tab'] === 'archived' ? 'archived' : 'active';
$isArchived = $tab === 'archived';

// Pagination settings
$itemsPerPage = 12;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get images based on tab
if ($isArchived) {
    $images = $gallery->getArchivedImages($offset, $itemsPerPage);
    $totalImages = $gallery->getTotalArchivedImages();
} else {
    $images = $gallery->getActiveImages($offset, $itemsPerPage);
    $totalImages = $gallery->getTotalActiveImages();
}
$totalPages = ceil($totalImages / $itemsPerPage);

// Build base URL for pagination preserving tab
$baseUrl = FULL_BASE_PATH . 'admin/gallery';
if ($isArchived) {
    $baseUrl .= '?tab=archived';
}
?>
<?php
$page = "gallery";
$pageTitle = "Gallery";
include __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Gallery</h1>
                <p class="admin-page-subtitle">Manage your gallery images</p>
            </div>
            <div class="admin-page-actions">
                <button type="button" class="admin-btn admin-btn-primary" id="uploadBtn">
                    <i class="bi bi-cloud-upload me-2"></i>Upload Images
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="admin-tabs">
            <a href="<?= FULL_BASE_PATH ?>admin/gallery?tab=active" class="admin-tab <?= !$isArchived ? 'active' : '' ?>">
                Active
            </a>
            <a href="<?= FULL_BASE_PATH ?>admin/gallery?tab=archived" class="admin-tab <?= $isArchived ? 'active' : '' ?>">
                Archived
            </a>
        </div>

        <?php if (count($images) > 0): ?>
            <div class="admin-gallery-grid">
                <?php foreach ($images as $image): ?>
                    <div class="admin-gallery-item" data-image-id="<?= $image['id'] ?>">
                        <div class="admin-card">
                            <div class="admin-gallery-image-container">
                                <img class="admin-gallery-image" src="<?= FULL_BASE_PATH . 'uploads/gallery/' . htmlspecialchars($image['filename']) ?>" alt="<?= htmlspecialchars($image['title'] ?? '') ?>">
                                <div class="admin-gallery-overlay">
                                    <button class="admin-btn <?= $isArchived ? 'admin-btn-warning' : 'admin-btn-secondary' ?>" data-action="<?= $isArchived ? 'restore' : 'archive' ?>" data-id="<?= $image['id'] ?>">
                                        <i class="bi bi-<?= $isArchived ? 'arrow-counterclockwise' : 'archive' ?>"></i>
                                    </button>
                                    <button class="admin-btn admin-btn-danger" data-action="delete" data-id="<?= $image['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="admin-card-body">
                                <h5 class="admin-card-title" data-field="title" data-id="<?= $image['id'] ?>"><?= htmlspecialchars($image['title'] ?? 'Untitled') ?></h5>
                                <p class="admin-card-text">
                                    <?= date('M d, Y', strtotime($image['uploaded_at'] ?? 'now')) ?>
                                </p>
                                <div class="admin-gallery-actions">
                                    <button class="admin-btn admin-btn-secondary" data-action="edit" data-id="<?= $image['id'] ?>" data-title="<?= htmlspecialchars($image['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ($isArchived): ?>
                                        <button class="admin-btn admin-btn-warning" data-action="restore" data-id="<?= $image['id'] ?>">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                    <?php else: ?>
                                        <button class="admin-btn admin-btn-secondary" data-action="archive" data-id="<?= $image['id'] ?>">
                                            <i class="bi bi-archive"></i> Archive
                                        </button>
                                    <?php endif; ?>
                                    <button class="admin-btn admin-btn-danger" data-action="delete" data-id="<?= $image['id'] ?>">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gallery-pagination">
                <?php echo renderPagination($currentPage, $totalPages, $baseUrl); ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="bi bi-images me-2"></i>
                <?= $isArchived ? 'No archived images.' : 'No active images. <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" id="emptyUploadBtn">Upload your first image</button>' ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Upload Modal -->
<div class="admin-upload-modal" id="uploadModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Upload Images</h5>
            <button type="button" class="admin-btn-close" id="closeUploadModalBtn">&times;</button>
        </div>
        <form id="uploadForm">
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label for="imageTitle" class="admin-form-label">Image Title (optional)</label>
                <input type="text" class="admin-form-control" id="imageTitle" name="imageTitle" placeholder="Default title" autocomplete="off">
            </div>
            <div class="admin-upload-dropzone" id="dropzone">
                <i class="bi bi-cloud-upload admin-upload-dropzone-icon"></i>
                <p class="admin-upload-dropzone-text">Drag and drop images here or click to browse</p>
                <input type="file" id="images" name="images" multiple accept="image/*" class="admin-upload-input">
            </div>
            <div class="admin-upload-preview" id="uploadPreview"></div>
            <div class="admin-upload-progress" id="uploadProgress" style="display: none;">
                <div class="admin-upload-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn admin-btn-secondary" id="cancelUploadBtn">Cancel</button>
                <button type="button" class="admin-btn admin-btn-primary" id="startUpload">Upload</button>
            </div>
        </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
