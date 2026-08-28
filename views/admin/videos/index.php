<?php
require_once __DIR__ . '/../../../config/setPath.php';
require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Videos.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$videos = new Videos();

$tab = isset($_GET['tab']) && $_GET['tab'] === 'archived' ? 'archived' : 'active';
$isArchived = $tab === 'archived';

$itemsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

if ($isArchived) {
    $videoList = $videos->getArchivedVideos($offset, $itemsPerPage);
    $totalVideos = $videos->getTotalArchivedVideos();
} else {
    $videoList = $videos->getActiveVideos($offset, $itemsPerPage);
    $totalVideos = $videos->getTotalActiveVideos();
}
$totalPages = ceil($totalVideos / $itemsPerPage);

$baseUrl = FULL_BASE_PATH . 'admin/videos';
if ($isArchived) $baseUrl .= '?tab=archived';

$page = "videos";
$pageTitle = "Videos";
include __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Videos</h1>
                <p class="admin-page-subtitle">Manage video uploads and embedded videos</p>
            </div>
            <div class="admin-page-actions">
                <button type="button" class="admin-btn admin-btn-primary" id="addUrlBtn">
                    <i class="bi bi-link-45deg me-2"></i>Add URL
                </button>
                <button type="button" class="admin-btn admin-btn-primary" id="uploadBtn">
                    <i class="bi bi-cloud-upload me-2"></i>Upload Video
                </button>
            </div>
        </div>

        <div class="admin-tabs">
            <a href="<?= FULL_BASE_PATH ?>admin/videos?tab=active" class="admin-tab <?= !$isArchived ? 'active' : '' ?>">Active</a>
            <a href="<?= FULL_BASE_PATH ?>admin/videos?tab=archived" class="admin-tab <?= $isArchived ? 'active' : '' ?>">Archived</a>
        </div>

        <?php if (count($videoList) > 0): ?>
            <div class="admin-videos-table">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Platform</th>
                            <th>Duration</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videoList as $i => $video): 
                            $duration = $video['duration'] > 0 ? gmdate('i:s', intval($video['duration'])) : '--:--';
                            $size = $video['file_size'] > 0 ? round($video['file_size'] / 1024 / 1024, 1) . ' MB' : '--';
                            $typeLabel = $video['type'] === 'url' ? 'URL' : 'File';
                            $platformLabel = !empty($video['platform']) ? ucfirst($video['platform']) : '--';
                            $thumbnail = '';
                            if ($video['type'] === 'url' && !empty($video['thumbnail_url'])) {
                                $thumbnail = $video['thumbnail_url'];
                            }
                        ?>
                            <tr data-video-id="<?= $video['id'] ?>">
                                <td><?= $offset + $i + 1 ?></td>
                                <td>
                                    <div class="admin-video-title-cell">
                                        <?php if (!empty($thumbnail)): ?>
                                            <img src="<?= htmlspecialchars($thumbnail) ?>" alt="" class="admin-video-thumb" loading="lazy" width="80" height="45">
                                        <?php endif; ?>
                                        <span class="admin-video-title" data-field="title"><?= htmlspecialchars($video['title'] ?? 'Untitled') ?></span>
                                    </div>
                                </td>
                                <td><span class="admin-badge admin-badge-<?= $video['type'] === 'url' ? 'info' : 'secondary' ?>"><?= $typeLabel ?></span></td>
                                <td><?= $platformLabel ?></td>
                                <td><?= $duration ?></td>
                                <td><?= $size ?></td>
                                <td><?= isset($video['uploaded_at']) ? date('M d, Y', strtotime($video['uploaded_at'])) : '--' ?></td>
                                <td>
                                    <div class="admin-videos-actions">
                                        <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit" data-id="<?= $video['id'] ?>" data-title="<?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($video['description'] ?? '', ENT_QUOTES) ?>" data-type="<?= $video['type'] ?>" data-url="<?= htmlspecialchars($video['url'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($isArchived): ?>
                                            <button class="admin-btn admin-btn-warning admin-btn-sm" data-action="restore" data-id="<?= $video['id'] ?>">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="archive" data-id="<?= $video['id'] ?>">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete" data-id="<?= $video['id'] ?>">
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
                <i class="bi bi-camera-reel me-2"></i>
                <?= $isArchived ? 'No archived videos.' : 'No videos yet. Add a URL or upload a video.' ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Upload Modal -->
<div class="admin-upload-modal" id="uploadModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Upload Video</h5>
            <button type="button" class="admin-btn-close" id="closeUploadModalBtn">&times;</button>
        </div>
        <form id="uploadForm">
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label for="videoTitle" class="admin-form-label">Title</label>
                <input type="text" class="admin-form-control" id="videoTitle" name="title" placeholder="Video title" autocomplete="off">
            </div>
            <div class="admin-form-group">
                <label for="videoDescription" class="admin-form-label">Description (optional)</label>
                <textarea class="admin-form-control" id="videoDescription" name="description" rows="2" placeholder="Brief description"></textarea>
            </div>
            <div class="admin-upload-dropzone" id="dropzone">
                <i class="bi bi-camera-reel admin-upload-dropzone-icon"></i>
                <p class="admin-upload-dropzone-text">Drop MP4, WebM, OGG, MOV, AVI file here or click to browse</p>
                <p class="admin-upload-dropzone-hint">Videos are converted to MP4 (H.264/AAC). Max 200MB.</p>
                <input type="file" id="videoFile" name="video" accept=".mp4,.webm,.ogg,.mov,.avi,.wmv" class="admin-upload-input">
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

<!-- Add URL Modal -->
<div class="admin-upload-modal" id="addUrlModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Add Video URL</h5>
            <button type="button" class="admin-btn-close" id="closeAddUrlModalBtn">&times;</button>
        </div>
        <form id="addUrlForm">
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label for="urlTitle" class="admin-form-label">Title <span class="admin-form-required">*</span></label>
                <input type="text" class="admin-form-control" id="urlTitle" name="title" placeholder="Video title" required autocomplete="off">
            </div>
            <div class="admin-form-group">
                <label for="urlDescription" class="admin-form-label">Description (optional)</label>
                <textarea class="admin-form-control" id="urlDescription" name="description" rows="2" placeholder="Brief description"></textarea>
            </div>
            <div class="admin-form-group">
                <label for="videoUrl" class="admin-form-label">Video URL <span class="admin-form-required">*</span></label>
                <input type="url" class="admin-form-control" id="videoUrl" name="url" placeholder="https://www.youtube.com/watch?v=... or https://odysee.com/..." required autocomplete="off">
                <small class="admin-form-help">Supports YouTube and Odysee URLs. Will be embedded via iframe.</small>
            </div>
        </div>
        <div class="admin-modal-footer">
            <div class="admin-upload-buttons">
                <button type="button" class="admin-btn admin-btn-secondary" id="cancelAddUrlBtn">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Add Video</button>
            </div>
        </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="admin-upload-modal" id="editModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Edit Video</h5>
            <button type="button" class="admin-btn-close" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editForm">
        <div class="admin-modal-body">
            <input type="hidden" id="editVideoId" name="id" value="">
            <div class="admin-form-group">
                <label for="editTitle" class="admin-form-label">Title <span class="admin-form-required">*</span></label>
                <input type="text" class="admin-form-control" id="editTitle" name="title" required autocomplete="off">
            </div>
            <div class="admin-form-group">
                <label for="editDescription" class="admin-form-label">Description</label>
                <textarea class="admin-form-control" id="editDescription" name="description" rows="3"></textarea>
            </div>
            <div class="admin-form-group" id="editUrlGroup" style="display:none;">
                <label for="editUrl" class="admin-form-label">Video URL</label>
                <input type="url" class="admin-form-control" id="editUrl" name="url" placeholder="https://www.youtube.com/watch?v=...">
                <small class="admin-form-help">Update the URL for embedded videos.</small>
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
<style>
.admin-video-title-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.admin-video-thumb {
    border-radius: 4px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #e0e0e0;
}
.admin-form-help {
    display: block;
    margin-top: 4px;
    color: var(--color-text-tertiary);
    font-size: 0.85em;
}
.admin-form-required {
    color: #a66060;
}
</style>
<script>
(function() {
    var csrf = document.querySelector("meta[name=\'csrf-token\']");
    var csrfToken = csrf ? csrf.getAttribute("content") : "";

    // --- Upload Modal ---
    var uploadBtn = document.getElementById("uploadBtn");
    var uploadModal = document.getElementById("uploadModal");
    var closeUploadBtn = document.getElementById("closeUploadModalBtn");
    var cancelUploadBtn = document.getElementById("cancelUploadBtn");
    var startUploadBtn = document.getElementById("startUpload");
    var uploadForm = document.getElementById("uploadForm");
    var videoInput = document.getElementById("videoFile");
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
    if (closeUploadBtn) closeUploadBtn.addEventListener("click", closeUploadModal);
    if (cancelUploadBtn) cancelUploadBtn.addEventListener("click", closeUploadModal);
    uploadModal.addEventListener("click", function(e) {
        if (e.target === uploadModal) closeUploadModal();
    });

    // File preview
    videoInput.addEventListener("change", function() {
        uploadPreview.innerHTML = "";
        var file = this.files[0];
        if (!file) return;
        var size = (file.size / 1024 / 1024).toFixed(1);
        var div = document.createElement("div");
        div.className = "admin-upload-preview-item";
        div.innerHTML = "<i class=\"bi bi-camera-reel\"></i> " + escapeHtml(file.name) + " (" + size + " MB)";
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
            videoInput.files = e.dataTransfer.files;
            videoInput.dispatchEvent(new Event("change"));
        }
    });
    dropzone.addEventListener("click", function(e) {
        if (e.target.type === "file") return;
        videoInput.click();
    });

    // Upload
    startUploadBtn.addEventListener("click", function() {
        var file = videoInput.files[0];
        if (!file) { alert("Please select a file."); return; }

        var formData = new FormData(uploadForm);
        formData.append("csrf_token", csrfToken);

        startUploadBtn.disabled = true;
        startUploadBtn.textContent = "Converting...";
        uploadProgress.style.display = "block";

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/videos/upload.php", true);
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

    // --- Add URL Modal ---
    var addUrlBtn = document.getElementById("addUrlBtn");
    var addUrlModal = document.getElementById("addUrlModal");
    var closeAddUrlBtn = document.getElementById("closeAddUrlModalBtn");
    var cancelAddUrlBtn = document.getElementById("cancelAddUrlBtn");
    var addUrlForm = document.getElementById("addUrlForm");

    function openAddUrlModal() {
        addUrlModal.style.display = "flex";
        addUrlModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeAddUrlModal() {
        addUrlModal.style.display = "none";
        addUrlModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        addUrlForm.reset();
    }

    if (addUrlBtn) addUrlBtn.addEventListener("click", openAddUrlModal);
    if (closeAddUrlBtn) closeAddUrlBtn.addEventListener("click", closeAddUrlModal);
    if (cancelAddUrlBtn) cancelAddUrlBtn.addEventListener("click", closeAddUrlModal);
    addUrlModal.addEventListener("click", function(e) {
        if (e.target === addUrlModal) closeAddUrlModal();
    });

    addUrlForm.addEventListener("submit", function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append("action", "add_url");
        formData.append("csrf_token", csrfToken);

        var submitBtn = this.querySelector("[type=\"submit\"]");
        submitBtn.disabled = true;
        submitBtn.textContent = "Adding...";

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/videos/update.php", true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.addEventListener("load", function() {
            submitBtn.disabled = false;
            submitBtn.textContent = "Add Video";
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    closeAddUrlModal();
                    location.reload();
                } else {
                    alert("Failed to add video: " + (resp.message || "Unknown error"));
                }
            } catch(e) {
                alert("Failed to add video.");
            }
        });
        xhr.send(formData);
    });

    // --- Edit Modal ---
    var editModal = document.getElementById("editModal");
    var closeEditBtn = document.getElementById("closeEditModalBtn");
    var cancelEditBtn = document.getElementById("cancelEditBtn");
    var editForm = document.getElementById("editForm");

    function openEditModal(id, title, desc, type, url) {
        document.getElementById("editVideoId").value = id;
        document.getElementById("editTitle").value = title;
        document.getElementById("editDescription").value = desc;
        var urlGroup = document.getElementById("editUrlGroup");
        if (type === "url") {
            urlGroup.style.display = "";
            document.getElementById("editUrl").value = url || "";
        } else {
            urlGroup.style.display = "none";
            document.getElementById("editUrl").value = "";
        }
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
                this.getAttribute("data-type"),
                this.getAttribute("data-url")
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
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/videos/update.php", true);
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
            if (action === "delete" && !confirm("Delete this video permanently?")) return;

            var formData = new FormData();
            formData.append("id", id);
            formData.append("action", action);
            formData.append("csrf_token", csrfToken);

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/videos/delete.php", true);
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            xhr.addEventListener("load", function() {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert("Failed to " + action + " video: " + (resp.message || "Unknown error"));
                    }
                } catch(e) {
                    alert("Failed to " + action + " video.");
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
