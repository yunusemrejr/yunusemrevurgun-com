<?php
require_once __DIR__ . '/../../../config/setPath.php';
require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Music.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$music = new Music();

$tab = isset($_GET['tab']) && $_GET['tab'] === 'archived' ? 'archived' : 'active';
$isArchived = $tab === 'archived';

$itemsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

if ($isArchived) {
    $tracks = $music->getArchivedTracks($offset, $itemsPerPage);
    $totalTracks = $music->getTotalArchivedTracks();
} else {
    $tracks = $music->getActiveTracks($offset, $itemsPerPage);
    $totalTracks = $music->getTotalActiveTracks();
}
$totalPages = ceil($totalTracks / $itemsPerPage);

$baseUrl = FULL_BASE_PATH . 'admin/music';
if ($isArchived) $baseUrl .= '?tab=archived';

$page = "music";
$pageTitle = "Music";
ob_start();
?>
<div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Music</h1>
                <p class="admin-page-subtitle">Manage audio tracks</p>
            </div>
            <div class="admin-page-actions">
                <button type="button" class="admin-btn admin-btn-primary" id="uploadBtn">
                    <i class="bi bi-cloud-upload me-2"></i>Upload Track
                </button>
            </div>
        </div>
<?php
$pageHeader = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>




        <div class="admin-tabs">
            <a href="<?= FULL_BASE_PATH ?>admin/music?tab=active" class="admin-tab <?= !$isArchived ? 'active' : '' ?>">Active</a>
            <a href="<?= FULL_BASE_PATH ?>admin/music?tab=archived" class="admin-tab <?= $isArchived ? 'active' : '' ?>">Archived</a>
        </div>

        <?php if (count($tracks) > 0): ?>
            <div class="admin-music-table">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Recorded</th>
                            <th>Duration</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracks as $i => $track):
                            $duration = $track['duration'] > 0 ? gmdate('i:s', round($track['duration'])) : '--:--';
                            $size = $track['file_size'] > 0 ? round($track['file_size'] / 1024 / 1024, 1) . ' MB' : '--';
                            $recordedDate = !empty($track['recorded_at']) ? date('M d, Y', strtotime($track['recorded_at'])) : '--';
                        ?>
                            <tr data-track-id="<?= $track['id'] ?>">
                                <td><?= $offset + $i + 1 ?></td>
                                <td>
                                    <span class="admin-music-title" data-field="title"><?= htmlspecialchars($track['title'] ?? 'Untitled') ?></span>
                                </td>
                                <td>
                                    <span class="admin-music-desc" data-field="description"><?= htmlspecialchars(mb_substr($track['description'] ?? '', 0, 80)) ?></span>
                                </td>
                                <td><?= $recordedDate ?></td>
                                <td><?= $duration ?></td>
                                <td><?= $size ?></td>
                                <td><?= isset($track['uploaded_at']) ? date('M d, Y', strtotime($track['uploaded_at'])) : '--' ?></td>
                                <td>
                                    <div class="admin-music-actions">
                                        <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit" data-id="<?= $track['id'] ?>" data-title="<?= htmlspecialchars($track['title'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($track['description'] ?? '', ENT_QUOTES) ?>" data-recorded-at="<?= htmlspecialchars($track['recorded_at'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($isArchived): ?>
                                            <button class="admin-btn admin-btn-warning admin-btn-sm" data-action="restore" data-id="<?= $track['id'] ?>">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="archive" data-id="<?= $track['id'] ?>">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete" data-id="<?= $track['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="gallery-pagination">
                <?php echo renderPagination($currentPage, $totalPages, $baseUrl); ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="bi bi-music-note-beamed me-2"></i>
                <?= $isArchived ? 'No archived tracks.' : 'No tracks yet. <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" id="emptyUploadBtn">Upload your first track</button>' ?>
            </div>
        <?php endif; ?>

        <!-- ==================== LINKS SECTION ==================== -->
        <div class="admin-section" style="margin-top:var(--space-8);">
            <div class="admin-section-header">
                <h2 class="admin-section-title">Platform Links</h2>
                <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" id="addLinkBtn">
                    <i class="bi bi-plus-lg me-2"></i>Add Link
                </button>
            </div>
            <p style="font-size:0.85rem;color:#8fa6a6;margin-bottom:1rem;">Links to external platforms like Spotify, Apple Music, etc.</p>

            <?php
            $links = $music->getAllLinks();
            if (count($links) > 0): ?>
            <div class="admin-gallery-grid" id="adminMusicLinksGrid">
                <?php foreach ($links as $link):
                    $platformNames = [
                        'spotify' => 'Spotify',
                        'apple-music' => 'Apple Music',
                        'youtube-music' => 'YouTube Music',
                        'soundcloud' => 'SoundCloud',
                        'bandcamp' => 'Bandcamp',
                        'amazon-music' => 'Amazon Music',
                        'deezer' => 'Deezer',
                        'tidal' => 'Tidal',
                        'other' => 'Other',
                    ];
                    $platformIcons = [
                        'spotify' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>',
                        'apple-music' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#FA243B"><path d="M12 0C5.372 0 0 5.372 0 12s5.372 12 12 12 12-5.372 12-12S18.628 0 12 0zm4.26 17.323c-.234.383-.7.508-1.083.274-2.958-1.804-6.664-2.748-10.866-1.94-.475.09-.943-.23-1.032-.705-.09-.476.23-.943.706-1.033 4.648-.894 8.77.158 12.104 2.198.382.234.508.7.274 1.082zm1.169-3.654c-.292.478-.874.63-1.353.338-3.388-2.08-8.533-2.726-12.543-1.49-.586.18-1.218-.156-1.398-.742-.18-.586.155-1.218.742-1.398 4.526-1.391 10.166-.66 14.004 1.687.478.292.63.874.338 1.353zm.115-3.857c-4.072-2.416-10.788-2.637-14.663-1.466-.708.214-1.454-.18-1.668-.888-.214-.708.18-1.454.888-1.668 4.442-1.345 11.844-1.092 16.475 1.635.635.377.842 1.187.465 1.822-.377.634-1.187.841-1.822.464z"/></svg>',
                        'youtube-music' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#FF0000"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm6.56 16.087c-.224.652-.864 1.06-1.565 1.06-2.11-.018-4.467-.018-6.828-.018-2.36 0-4.718 0-6.828.018-.7 0-1.34-.407-1.564-1.06-.262-.785-.262-2.09-.262-4.087s0-3.302.262-4.087c.224-.652.864-1.06 1.565-1.06 2.11.018 4.467.018 6.828.018 2.36 0 4.718 0 6.828-.018.7 0 1.34.407 1.565 1.06.261.785.261 2.09.261 4.087s0 3.302-.262 4.087zM10.5 8.75v6.5l6-3.25-6-3.25z"/></svg>',
                        'soundcloud' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#FF5500"><path d="M1.175 12.225c-.194 0-.353.16-.353.353v4.412c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-4.412c0-.194-.16-.353-.353-.353zm2.382 2.647c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.647 0c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.647 0c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.647 0c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.117-2.647c-.194 0-.353.16-.353.353v4.412c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-4.412c0-.194-.16-.353-.353-.353zM21.58 10.18c-1.23 0-2.36.437-3.232 1.153-.14-2.828-2.457-5.098-5.343-5.098-.275 0-.544.028-.808.074-.16.028-.334.039-.497.039-.883 0-1.675.338-2.28.885-.083.075-.18.135-.248.22-.078.098-.105.214-.105.337v7.268c0 .194.16.353.353.353h11.16c1.554 0 2.823-1.269 2.823-2.823 0-1.554-1.27-2.823-2.823-2.823z"/></svg>',
                        'bandcamp' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#629AA9"><path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/></svg>',
                        'deezer' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#FEAA2D"><path d="M18.81 4.19v3.38H24V4.19h-5.19zM0 20.62h5.19v-3.38H0v3.38zm6.23 0h5.19v-3.38H6.23v3.38zm6.24 0h5.19v-3.38h-5.19v3.38zm6.34 0H24v-3.38h-5.19v3.38zM0 16.43h5.19v-3.38H0v3.38zm6.23 0h5.19v-3.38H6.23v3.38zm6.24 0h5.19v-3.38h-5.19v3.38zm6.34 0H24v-3.38h-5.19v3.38zM0 12.24h5.19V8.86H0v3.38zm6.23 0h5.19V8.86H6.23v3.38zm12.58 0H24V8.86h-5.19v3.38z"/></svg>',
                        'tidal' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#000000"><path d="M12.012 3.992L8.008 7.996 4.004 3.992 0 7.996 4.004 12 8.008 7.996l4.004 4.004 4.004-4.004-4.004-4.004zm0 8.008l-4.004 4.004 4.004 4.004 4.004-4.004-4.004-4.004z"/></svg>',
                    ];
                    $pf = $link['platform'];
                    $pname = $platformNames[$pf] ?? 'Other';
                    $picon = $platformIcons[$pf] ?? '';
                ?>
                <div class="admin-gallery-item" data-link-id="<?= $link['id'] ?>">
                    <div class="admin-card">
                        <div class="admin-link-card-body">
                            <div class="admin-link-platform-icon"><?= $picon ?></div>
                            <div class="admin-link-info">
                                <h5 class="admin-card-title"><?= htmlspecialchars($link['title']) ?></h5>
                                <p class="admin-card-text" style="font-size:0.8rem;"><?= htmlspecialchars($pname) ?></p>
                                <?php if (!empty($link['description'])): ?>
                                <p class="admin-card-text" style="font-size:0.75rem;color:#8fa6a6;"><?= htmlspecialchars(mb_substr($link['description'], 0, 100)) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="admin-card-body" style="border-top:1px solid rgba(132,144,164,0.15);padding-top:0.5rem;">
                            <div style="display:flex;gap:0.5rem;">
                                <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit-link" data-id="<?= $link['id'] ?>" data-title="<?= htmlspecialchars($link['title'], ENT_QUOTES) ?>" data-url="<?= htmlspecialchars($link['url'], ENT_QUOTES) ?>" data-platform="<?= htmlspecialchars($link['platform'], ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($link['description'] ?? '', ENT_QUOTES) ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete-link" data-id="<?= $link['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="admin-empty-state">
                <i class="bi bi-link-45deg me-2"></i>
                No links yet. <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" id="emptyAddLinkBtn">Add your first link</button>
            </div>
            <?php endif; ?>
        </div>

<div class="admin-upload-modal" id="uploadModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Upload Audio Track</h5>
            <button type="button" class="admin-btn-close" id="closeUploadModalBtn">&times;</button>
        </div>
        <form id="uploadForm">
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label for="trackTitle" class="admin-form-label">Title</label>
                <input type="text" class="admin-form-control" id="trackTitle" name="title" placeholder="Track title" autocomplete="off">
            </div>
            <div class="admin-form-group">
                <label for="trackDescription" class="admin-form-label">Description (optional)</label>
                <textarea class="admin-form-control" id="trackDescription" name="description" rows="2" placeholder="Brief description"></textarea>
            </div>
            <div class="admin-form-group">
                <label for="trackRecordedAt" class="admin-form-label">Recorded date (optional)</label>
                <input type="date" class="admin-form-control" id="trackRecordedAt" name="recorded_at">
            </div>
            <div class="admin-upload-dropzone" id="dropzone">
                <i class="bi bi-cloud-upload admin-upload-dropzone-icon"></i>
                <p class="admin-upload-dropzone-text">Drop MP3, WAV, or MP4 file here or click to browse</p>
                <p class="admin-upload-dropzone-hint">Files are automatically converted to MP3. Max 50MB.</p>
                <input type="file" id="audioFile" name="audio" accept=".mp3,.wav,.mp4,.aac,.ogg" class="admin-upload-input">
            </div>
            <div class="admin-upload-preview" id="uploadPreview"></div>
            <div class="admin-upload-progress" id="uploadProgress" style="display: none;">
                <div class="admin-upload-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn admin-btn-secondary" id="cancelUploadBtn">Cancel</button>
                <button type="button" class="admin-btn admin-btn-primary" id="startUpload">Upload & Convert</button>
            </div>
        </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="admin-upload-modal" id="editModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Edit Track</h5>
            <button type="button" class="admin-btn-close" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editForm">
        <div class="admin-modal-body">
            <input type="hidden" id="editTrackId" name="id" value="">
            <div class="admin-form-group">
                <label for="editTitle" class="admin-form-label">Title</label>
                <input type="text" class="admin-form-control" id="editTitle" name="title" required autocomplete="off">
            </div>
            <div class="admin-form-group">
                <label for="editDescription" class="admin-form-label">Description</label>
                <textarea class="admin-form-control" id="editDescription" name="description" rows="3"></textarea>
            </div>
            <div class="admin-form-group">
                <label for="editRecordedAt" class="admin-form-label">Recorded date</label>
                <input type="date" class="admin-form-control" id="editRecordedAt" name="recorded_at">
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn admin-btn-secondary" id="cancelEditBtn">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
            </div>
        </div>
        </form>
    </div>
</div>

<!-- Add/Edit Link Modal -->
<div class="admin-upload-modal" id="linkModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title" id="linkModalTitle">Add Link</h5>
            <button type="button" class="admin-btn-close" id="closeLinkModalBtn">&times;</button>
        </div>
        <form id="linkForm">
        <div class="admin-modal-body">
            <input type="hidden" id="linkId" name="id" value="">
            <input type="hidden" id="linkAction" name="action" value="create">
            <div class="admin-form-group">
                <label for="linkTitle" class="admin-form-label">Title *</label>
                <input type="text" class="admin-form-control" id="linkTitle" name="title" placeholder="e.g. Listen on Spotify" autocomplete="off" required>
            </div>
            <div class="admin-form-group">
                <label for="linkUrl" class="admin-form-label">URL *</label>
                <input type="url" class="admin-form-control" id="linkUrl" name="url" placeholder="https://open.spotify.com/track/..." autocomplete="off" required>
            </div>
            <div class="admin-form-group">
                <label for="linkPlatform" class="admin-form-label">Platform</label>
                <select class="admin-form-select" id="linkPlatform" name="platform">
                    <option value="spotify">Spotify</option>
                    <option value="apple-music">Apple Music</option>
                    <option value="youtube-music">YouTube Music</option>
                    <option value="soundcloud">SoundCloud</option>
                    <option value="bandcamp">Bandcamp</option>
                    <option value="amazon-music">Amazon Music</option>
                    <option value="deezer">Deezer</option>
                    <option value="tidal">Tidal</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label for="linkDescription" class="admin-form-label">Description (optional)</label>
                <textarea class="admin-form-control" id="linkDescription" name="description" rows="2" placeholder="Brief description"></textarea>
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn admin-btn-secondary" id="cancelLinkBtn">Cancel</button>
                <button type="button" class="admin-btn admin-btn-primary" id="saveLinkBtn">Save Link</button>
            </div>
        </div>
        </form>
    </div>
</div>

<?php
$pageScripts = '
<script>
(function() {
    var csrf = document.querySelector("meta[name=\'csrf-token\']");
    var csrfToken = csrf ? csrf.getAttribute("content") : "";

    // Upload modal
    var uploadBtn = document.getElementById("uploadBtn");
    var emptyUploadBtn = document.getElementById("emptyUploadBtn");
    var uploadModal = document.getElementById("uploadModal");
    var closeUploadBtn = document.getElementById("closeUploadModalBtn");
    var cancelUploadBtn = document.getElementById("cancelUploadBtn");
    var startUploadBtn = document.getElementById("startUpload");
    var uploadForm = document.getElementById("uploadForm");
    var audioInput = document.getElementById("audioFile");
    var dropzone = document.getElementById("dropzone");
    var uploadPreview = document.getElementById("uploadPreview");
    var uploadProgress = document.getElementById("uploadProgress");
    var progressBar = uploadProgress.querySelector(".admin-upload-progress-bar");

    function openUploadModal() {
        uploadModal.style.display = "flex";
        uploadModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeUploadModal() {
        uploadModal.style.display = "none";
        uploadModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        uploadForm.reset();
        uploadPreview.innerHTML = "";
        uploadProgress.style.display = "none";
        progressBar.style.width = "0%";
        progressBar.setAttribute("aria-valuenow", "0");
    }

    if (uploadBtn) uploadBtn.addEventListener("click", openUploadModal);
    if (emptyUploadBtn) emptyUploadBtn.addEventListener("click", openUploadModal);
    if (closeUploadBtn) closeUploadBtn.addEventListener("click", closeUploadModal);
    if (cancelUploadBtn) cancelUploadBtn.addEventListener("click", closeUploadModal);
    uploadModal.addEventListener("click", function(e) {
        if (e.target === uploadModal) closeUploadModal();
    });

    // File preview
    audioInput.addEventListener("change", function() {
        uploadPreview.innerHTML = "";
        var file = this.files[0];
        if (!file) return;
        var size = (file.size / 1024 / 1024).toFixed(1);
        var div = document.createElement("div");
        div.className = "admin-upload-preview-item";
        div.innerHTML = "<i class=\"bi bi-file-earmark-music\"></i> " + escapeHtml(file.name) + " (" + size + " MB)";
        uploadPreview.appendChild(div);
    });

    // Drag & drop
    dropzone.addEventListener("dragover", function(e) {
        e.preventDefault();
        dropzone.classList.add("is-dragover");
    });
    dropzone.addEventListener("dragleave", function() {
        dropzone.classList.remove("is-dragover");
    });
    dropzone.addEventListener("drop", function(e) {
        e.preventDefault();
        dropzone.classList.remove("is-dragover");
        if (e.dataTransfer.files.length > 0) {
            audioInput.files = e.dataTransfer.files;
            audioInput.dispatchEvent(new Event("change"));
        }
    });
    dropzone.addEventListener("click", function() {
        audioInput.click();
    });

    // Upload
    startUploadBtn.addEventListener("click", function() {
        var file = audioInput.files[0];
        if (!file) { alert("Please select a file."); return; }

        var formData = new FormData(uploadForm);
        formData.append("csrf_token", csrfToken);

        startUploadBtn.disabled = true;
        startUploadBtn.textContent = "Converting...";
        uploadProgress.style.display = "block";

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/music/upload.php", true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

        xhr.upload.addEventListener("progress", function(e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = pct + "%";
                progressBar.setAttribute("aria-valuenow", pct);
            }
        });

        xhr.addEventListener("load", function() {
            startUploadBtn.disabled = false;
            startUploadBtn.textContent = "Upload & Convert";
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    closeUploadModal();
                    location.reload();
                } else {
                    alert("Upload failed: " + (resp.message || "Unknown error"));
                    uploadProgress.style.display = "none";
                }
            } catch(e) {
                alert("Upload failed. Check server logs.");
                uploadProgress.style.display = "none";
            }
        });

        xhr.addEventListener("error", function() {
            startUploadBtn.disabled = false;
            startUploadBtn.textContent = "Upload & Convert";
            alert("Upload failed due to network error.");
            uploadProgress.style.display = "none";
        });

        xhr.send(formData);
    });

    // Edit modal
    var editModal = document.getElementById("editModal");
    var closeEditBtn = document.getElementById("closeEditModalBtn");
    var cancelEditBtn = document.getElementById("cancelEditBtn");
    var editForm = document.getElementById("editForm");

    function openEditModal(id, title, desc, recordedAt) {
        document.getElementById("editTrackId").value = id;
        document.getElementById("editTitle").value = title;
        document.getElementById("editDescription").value = desc;
        document.getElementById("editRecordedAt").value = recordedAt || "";
        editModal.style.display = "flex";
        editModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeEditModal() {
        editModal.style.display = "none";
        editModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    if (closeEditBtn) closeEditBtn.addEventListener("click", closeEditModal);
    if (cancelEditBtn) cancelEditBtn.addEventListener("click", closeEditModal);
    editModal.addEventListener("click", function(e) {
        if (e.target === editModal) closeEditModal();
    });

    // Edit buttons
    document.querySelectorAll("[data-action=\"edit\"]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            openEditModal(
                this.getAttribute("data-id"),
                this.getAttribute("data-title"),
                this.getAttribute("data-description"),
                this.getAttribute("data-recorded-at")
            );
        });
    });

    editForm.addEventListener("submit", function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append("csrf_token", csrfToken);

        var submitBtn = this.querySelector("[type=\"submit\"]");
        submitBtn.disabled = true;
        submitBtn.textContent = "Saving...";

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/music/update.php", true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.addEventListener("load", function() {
            submitBtn.disabled = false;
            submitBtn.textContent = "Save Changes";
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    closeEditModal();
                    location.reload();
                } else {
                    alert("Update failed: " + (resp.message || "Unknown error"));
                }
            } catch(e) {
                alert("Update failed.");
            }
        });
        xhr.send(formData);
    });

    // Archive / Restore / Delete
    document.querySelectorAll("[data-action=\"archive\"], [data-action=\"restore\"], [data-action=\"delete\"]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var action = this.getAttribute("data-action");
            var id = this.getAttribute("data-id");
            var label = action === "delete" ? "delete" : (action === "archive" ? "archive" : "restore");
            if (action === "delete" && !confirm("Delete this track permanently?")) return;

            var formData = new FormData();
            formData.append("id", id);
            formData.append("action", action);
            formData.append("csrf_token", csrfToken);

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/music/delete.php", true);
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            xhr.addEventListener("load", function() {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert("Failed to " + label + " track: " + (resp.message || "Unknown error"));
                    }
                } catch(e) {
                    alert("Failed to " + label + " track.");
                }
            });
            xhr.send(formData);
        });
    });

    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== LINK MANAGEMENT ====================

    var linkModal = document.getElementById("linkModal");
    var linkForm = document.getElementById("linkForm");

    function openLinkModal(id, title, url, platform, desc) {
        document.getElementById("linkModalTitle").textContent = id ? "Edit Link" : "Add Link";
        document.getElementById("linkId").value = id || "";
        document.getElementById("linkAction").value = id ? "update" : "create";
        document.getElementById("linkTitle").value = title || "";
        document.getElementById("linkUrl").value = url || "";
        document.getElementById("linkPlatform").value = platform || "spotify";
        document.getElementById("linkDescription").value = desc || "";
        linkModal.style.display = "flex";
        linkModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeLinkModal() {
        linkModal.style.display = "none";
        linkModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        linkForm.reset();
    }

    function saveLink() {
        var id = document.getElementById("linkId").value;
        var action = document.getElementById("linkAction").value;
        var title = document.getElementById("linkTitle").value.trim();
        var url = document.getElementById("linkUrl").value.trim();
        var platform = document.getElementById("linkPlatform").value;
        var desc = document.getElementById("linkDescription").value.trim();

        if (!title || !url) {
            alert("Title and URL are required.");
            return;
        }

        var formData = new FormData();
        formData.append("csrf_token", csrfToken);
        formData.append("action", action);
        formData.append("title", title);
        formData.append("url", url);
        formData.append("platform", platform);
        formData.append("description", desc);
        if (id) formData.append("id", id);

        var saveBtn = document.getElementById("saveLinkBtn");
        saveBtn.disabled = true;
        saveBtn.textContent = "Saving...";

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/music/links.php", true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.addEventListener("load", function() {
            saveBtn.disabled = false;
            saveBtn.textContent = "Save Link";
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    closeLinkModal();
                    location.reload();
                } else {
                    alert("Failed: " + (resp.message || "Unknown error"));
                }
            } catch(e) {
                alert("Failed to save link.");
            }
        });
        xhr.addEventListener("error", function() {
            saveBtn.disabled = false;
            saveBtn.textContent = "Save Link";
            alert("Network error.");
        });
        xhr.send(formData);
    }

    function deleteLink(id) {
        if (!id) return;
        if (!confirm("Delete this link?")) return;

        var formData = new FormData();
        formData.append("csrf_token", csrfToken);
        formData.append("action", "delete");
        formData.append("id", id);

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/music/links.php", true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.addEventListener("load", function() {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    location.reload();
                } else {
                    alert("Failed to delete: " + (resp.message || "Unknown error"));
                }
            } catch(e) {
                alert("Failed to delete link.");
            }
        });
        xhr.send(formData);
    }

    // Link modal events
    document.getElementById("addLinkBtn")?.addEventListener("click", function() { openLinkModal(null); });
    document.getElementById("emptyAddLinkBtn")?.addEventListener("click", function() { openLinkModal(null); });
    document.getElementById("closeLinkModalBtn")?.addEventListener("click", closeLinkModal);
    document.getElementById("cancelLinkBtn")?.addEventListener("click", closeLinkModal);
    document.getElementById("saveLinkBtn")?.addEventListener("click", saveLink);

    linkModal?.addEventListener("click", function(e) {
        if (e.target === linkModal) closeLinkModal();
    });

    linkForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        saveLink();
    });

    // Edit/delete link buttons
    document.querySelectorAll("[data-action=\"edit-link\"]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            openLinkModal(
                this.getAttribute("data-id"),
                this.getAttribute("data-title"),
                this.getAttribute("data-url"),
                this.getAttribute("data-platform"),
                this.getAttribute("data-description")
            );
        });
    });

    document.querySelectorAll("[data-action=\"delete-link\"]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            deleteLink(this.getAttribute("data-id"));
        });
    });
})();
</script>';

include __DIR__ . '/../includes/footer.php';
?>
