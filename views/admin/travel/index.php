<?php
require_once __DIR__ . '/../../../config/setPath.php';
require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Travel.php';
Auth::checkLogin();

$travel = new Travel();
$locations = $travel->getAllLocations();

$page = "travel";
$pageTitle = "Travel";
include __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Travel</h1>
                <p class="admin-page-subtitle">Manage travel locations and photos</p>
            </div>
            <div class="admin-page-actions">
                <button type="button" class="admin-btn admin-btn-primary" id="addLocationBtn">
                    <i class="bi bi-plus-lg me-2"></i>Add Location
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="admin-section">
            <div class="admin-stats-grid">
                <div class="admin-stat-card">
                    <div class="admin-stat-value"><?= $travel->getTotalLocations() ?></div>
                    <div class="admin-stat-label">Locations</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-value"><?= $travel->getTotalCountries() ?></div>
                    <div class="admin-stat-label">Countries</div>
                </div>
            </div>
        </div>

        <!-- Locations Table -->
        <div class="admin-section">
            <div class="admin-section-header">
                <h2 class="admin-section-title">All Locations</h2>
            </div>

            <?php if (count($locations) > 0): ?>
            <div class="admin-table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>City</th>
                            <th>Lat</th>
                            <th>Lng</th>
                            <th>Visited</th>
                            <th>Photos</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): 
                            $imageCount = $travel->getTotalImagesByLocation($location['id']);
                            $visitedDate = !empty($location['visited']) ? date('M d, Y', strtotime($location['visited'])) : '—';
                        ?>
                            <tr data-location-id="<?= $location['id'] ?>">
                                <td><?= htmlspecialchars($location['country']) ?></td>
                                <td><?= htmlspecialchars($location['city']) ?></td>
                                <td><code><?= htmlspecialchars($location['lat']) ?></code></td>
                                <td><code><?= htmlspecialchars($location['lng']) ?></code></td>
                                <td><?= $visitedDate ?></td>
                                <td><?= $imageCount ?></td>
                                <td>
                                    <div class="admin-table-actions">
                                        <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="manage-photos" data-id="<?= $location['id'] ?>" data-country="<?= htmlspecialchars($location['country'], ENT_QUOTES, 'UTF-8') ?>" data-city="<?= htmlspecialchars($location['city'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-images"></i> Photos
                                        </button>
                                        <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit-location" data-id="<?= $location['id'] ?>" data-country="<?= htmlspecialchars($location['country'], ENT_QUOTES, 'UTF-8') ?>" data-city="<?= htmlspecialchars($location['city'], ENT_QUOTES, 'UTF-8') ?>" data-lat="<?= htmlspecialchars($location['lat']) ?>" data-lng="<?= htmlspecialchars($location['lng']) ?>" data-visited="<?= htmlspecialchars($location['visited'] ?? '') ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete-location" data-id="<?= $location['id'] ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="admin-empty-state">
                <i class="bi bi-geo-alt me-2"></i>
                No travel locations yet. <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" id="emptyAddLocationBtn">Add your first location</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add/Edit Location Modal -->
<div class="admin-upload-modal" id="locationModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title" id="locationModalTitle">Add Location</h5>
            <button type="button" class="admin-btn-close" id="closeLocationModalBtn">&times;</button>
        </div>
        <form id="locationForm">
        <div class="admin-modal-body">
            <input type="hidden" id="locationId" name="locationId" value="">
            <div class="admin-form-group">
                <label for="locationCountry" class="admin-form-label">Country *</label>
                <input type="text" class="admin-form-control" id="locationCountry" name="country" placeholder="e.g. Turkey" autocomplete="off" required>
            </div>
            <div class="admin-form-group">
                <label for="locationCity" class="admin-form-label">City *</label>
                <input type="text" class="admin-form-control" id="locationCity" name="city" placeholder="e.g. Istanbul" autocomplete="off" required>
            </div>
            <div class="admin-form-group">
                <label for="locationLat" class="admin-form-label">Latitude *</label>
                <input type="number" step="any" class="admin-form-control" id="locationLat" name="lat" placeholder="41.0082" required>
            </div>
            <div class="admin-form-group">
                <label for="locationLng" class="admin-form-label">Longitude *</label>
                <input type="number" step="any" class="admin-form-control" id="locationLng" name="lng" placeholder="28.9784" required>
            </div>
            <div class="admin-form-group">
                <label for="locationVisited" class="admin-form-label">Visited Date (optional)</label>
                <input type="date" class="admin-form-control" id="locationVisited" name="visited">
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn admin-btn-secondary" id="cancelLocationBtn">Cancel</button>
                <button type="button" class="admin-btn admin-btn-primary" id="saveLocationBtn">Save Location</button>
            </div>
        </div>
        </form>
    </div>
</div>

<!-- Manage Photos Modal -->
<div class="admin-upload-modal" id="photosModal" tabindex="-1">
    <div class="admin-upload-modal-content admin-photos-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title" id="photosModalTitle">Manage Photos</h5>
            <button type="button" class="admin-btn-close" id="closePhotosModalBtn">&times;</button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="photoLocationId" value="">
            
            <!-- Upload Section -->
            <div class="admin-upload-dropzone" id="photoDropzone">
                <i class="bi bi-cloud-upload admin-upload-dropzone-icon"></i>
                <p class="admin-upload-dropzone-text">Drag and drop photos here or click to browse</p>
                <input type="file" id="photoFiles" multiple accept="image/*" class="admin-upload-input">
            </div>
            <div class="admin-upload-progress" id="photoUploadProgress" style="display: none;">
                <div class="admin-upload-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <!-- Existing Photos Grid -->
            <div class="admin-section" style="margin-top: var(--space-6);">
                <h6 class="admin-section-title" style="font-size: var(--text-sm);">Existing Photos</h6>
                <div class="admin-gallery-grid" id="photoGrid" style="margin-top: var(--space-3);">
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn" id="linkFromGalleryBtn" style="background:#e3e2de;color:#434343;border:1px solid rgba(132,144,164,0.25);"><i class="bi bi-link-45deg"></i> Link from Gallery</button>
                <button type="button" class="admin-btn admin-btn-secondary" id="closePhotosBtn">Close</button>
                <button type="button" class="admin-btn admin-btn-primary" id="uploadPhotosBtn" disabled>Upload Photos</button>
            </div>
        </div>
    </div>
</div>

<!-- Gallery Picker Modal -->
<div class="admin-upload-modal" id="galleryPickerModal" tabindex="-1">
    <div class="admin-upload-modal-content" style="max-width:720px;">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Select Gallery Image</h5>
            <button type="button" class="admin-btn-close" id="closeGalleryPickerBtn">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p style="font-size:0.85rem;color:#8fa6a6;margin-bottom:1rem;">Click an image to link it to this travel location. The file is not copied — it remains in the gallery.</p>
            <div class="admin-gallery-grid" id="galleryPickerGrid" style="margin-top:0;">
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
