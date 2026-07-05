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
include __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="admin-content">
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
    </div>
</main>

<!-- Upload Modal -->
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
})();
</script>';

include __DIR__ . '/../includes/footer.php';
?>
