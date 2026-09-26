<?php
require_once __DIR__ . '/../../../config/setPath.php';
require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Slop.php';
Auth::checkLogin();

$slop = new Slop();
$slopList = $slop->getAllSlops();

// First visit plants the deletable test example so the section is verifiable
// end to end; a sentinel keeps it from coming back after a full cleanup.
$seededNow = false;
$sentinel = dirname(__DIR__, 3) . '/' . Slop::UPLOAD_DIR . '/.seeded';
if (count($slopList) === 0 && !is_file($sentinel) && is_file(dirname(__DIR__, 3) . '/' . Slop::UPLOAD_DIR . '/' . Slop::EXAMPLE_FILE)) {
    try {
        $slop->seedExample();
        $slopList = $slop->getAllSlops();
        $seededNow = true;
        @touch($sentinel);
    } catch (Exception $seedError) {
        error_log('Slop example seeding failed: ' . $seedError->getMessage());
    }
}

$page = "slop";
$pageTitle = "Quality 3D AI Slop";
ob_start();
?>
<div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Quality 3D AI Slop</h1>
                <p class="admin-page-subtitle">Upload single-file HTML 3D pages, served publicly at /slop/&lt;slug&gt;</p>
            </div>
            <div class="admin-page-actions">
                <button type="button" class="admin-btn admin-btn-primary" id="uploadBtn">
                    <i class="bi bi-cloud-upload me-2"></i>Upload Slop
                </button>
            </div>
        </div>
<?php
$pageHeader = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>

        <?php if ($seededNow): ?>
            <div class="admin-alert admin-alert-info">
                A test example was added so you can check the public page. Delete it whenever you like.
            </div>
        <?php endif; ?>

        <?php if (count($slopList) > 0): ?>
            <div class="admin-videos-table">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Public URL</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slopList as $i => $item):
                            $size = $item['file_size'] > 0 ? round($item['file_size'] / 1024, 1) . ' KB' : '--';
                            $publicUrl = FULL_BASE_PATH . 'slop/' . rawurlencode($item['slug']);
                        ?>
                            <tr data-slop-id="<?= (int)$item['id'] ?>">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <span class="admin-video-title" data-field="title"><?= htmlspecialchars($item['title'] ?? 'Untitled') ?></span>
                                    <?php if (!empty($item['description'])): ?>
                                        <br><small class="admin-form-help"><?= htmlspecialchars(mb_substr($item['description'], 0, 120)) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><a href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" rel="noopener noreferrer">/slop/<?= htmlspecialchars($item['slug']) ?> ↗</a></td>
                                <td><?= $size ?></td>
                                <td><?= isset($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : '--' ?></td>
                                <td>
                                    <div class="admin-videos-actions">
                                        <button class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit" data-id="<?= (int)$item['id'] ?>" data-title="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>" data-slug="<?= htmlspecialchars($item['slug'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete" data-id="<?= (int)$item['id'] ?>">
                                            <i class="bi bi-trash"></i>
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
                <i class="bi bi-box me-2"></i>
                No slop yet. Upload an .html file to publish the first one.
                <div style="margin-top: 12px;">
                    <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="seedExampleBtn">Add test example</button>
                </div>
            </div>
        <?php endif; ?>


<!-- Upload Modal -->
<div class="admin-upload-modal" id="uploadModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Upload Slop</h5>
            <button type="button" class="admin-btn-close" id="closeUploadModalBtn">&times;</button>
        </div>
        <form id="uploadForm">
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label for="slopTitle" class="admin-form-label">Title <span class="admin-form-required">*</span></label>
                <input type="text" class="admin-form-control" id="slopTitle" name="title" placeholder="Slop title" required autocomplete="off" maxlength="255">
                <small class="admin-form-help">The public URL slug is generated from the title (conflicts get -1, -2, ...).</small>
            </div>
            <div class="admin-form-group">
                <label for="slopDescription" class="admin-form-label">Description (optional)</label>
                <textarea class="admin-form-control" id="slopDescription" name="description" rows="2" placeholder="Brief description — used for SEO when the file has none"></textarea>
            </div>
            <div class="admin-upload-dropzone" id="dropzone">
                <i class="bi bi-filetype-html admin-upload-dropzone-icon"></i>
                <p class="admin-upload-dropzone-text">Drop a single .html file here or click to browse</p>
                <p class="admin-upload-dropzone-hint">One self-contained HTML file. Max 5MB. Libraries must come from jsDelivr, unpkg, cdnjs, or inline code.</p>
                <input type="file" id="slopFile" name="slop" accept=".html,.htm,text/html" class="admin-upload-input">
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

<!-- Edit Modal -->
<div class="admin-upload-modal" id="editModal" tabindex="-1">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title">Edit Slop</h5>
            <button type="button" class="admin-btn-close" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editForm">
        <div class="admin-modal-body">
            <input type="hidden" id="editSlopId" name="id" value="">
            <div class="admin-form-group">
                <label for="editTitle" class="admin-form-label">Title <span class="admin-form-required">*</span></label>
                <input type="text" class="admin-form-control" id="editTitle" name="title" required autocomplete="off" maxlength="255">
            </div>
            <div class="admin-form-group">
                <label for="editDescription" class="admin-form-label">Description</label>
                <textarea class="admin-form-control" id="editDescription" name="description" rows="3"></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">Public URL slug</label>
                <input type="text" class="admin-form-control" id="editSlug" disabled>
                <small class="admin-form-help">Slugs never change after publishing, so indexed links keep working.</small>
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
.admin-form-help {
    display: block;
    margin-top: 4px;
    color: var(--color-text-tertiary);
    font-size: 0.85em;
}
.admin-form-required {
    color: var(--color-danger);
}
.admin-alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}
.admin-alert-info {
    background: rgba(13, 110, 253, 0.1);
    border: 1px solid rgba(13, 110, 253, 0.3);
}
</style>
<script>
(function() {
    var csrf = document.querySelector("meta[name=\'csrf-token\']");
    var csrfToken = csrf ? csrf.getAttribute("content") : "";

    function postForm(url, formData, onOk, onFail) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        function fail(message) {
            alert(message);
            if (typeof onFail === "function") onFail();
        }
        xhr.addEventListener("load", function() {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) { onOk(resp); }
                else { fail("Failed: " + (resp.message || "Unknown error")); }
            } catch(e) {
                fail("Request failed.");
            }
        });
        xhr.addEventListener("error", function() { fail("Request failed due to network error."); });
        xhr.send(formData);
    }

    // --- Upload Modal ---
    var uploadBtn = document.getElementById("uploadBtn");
    var uploadModal = document.getElementById("uploadModal");
    var closeUploadBtn = document.getElementById("closeUploadModalBtn");
    var cancelUploadBtn = document.getElementById("cancelUploadBtn");
    var startUploadBtn = document.getElementById("startUpload");
    var uploadForm = document.getElementById("uploadForm");
    var slopInput = document.getElementById("slopFile");
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

    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    slopInput.addEventListener("change", function() {
        uploadPreview.innerHTML = "";
        var file = this.files[0];
        if (!file) return;
        var size = (file.size / 1024).toFixed(1);
        var div = document.createElement("div");
        div.className = "admin-upload-preview-item";
        div.innerHTML = "<i class=\"bi bi-filetype-html\"></i> " + escapeHtml(file.name) + " (" + size + " KB)";
        uploadPreview.appendChild(div);
        var titleInput = document.getElementById("slopTitle");
        if (!titleInput.value) {
            titleInput.value = file.name.replace(/\\.html?$/i, "").replace(/[-_]+/g, " ").trim();
        }
    });

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
            slopInput.files = e.dataTransfer.files;
            slopInput.dispatchEvent(new Event("change"));
        }
    });
    dropzone.addEventListener("click", function(e) {
        if (e.target.type === "file") return;
        slopInput.click();
    });

    startUploadBtn.addEventListener("click", function() {
        var file = slopInput.files[0];
        if (!file) { alert("Please select a file."); return; }
        if (!document.getElementById("slopTitle").value.trim()) { alert("Please enter a title."); return; }

        var formData = new FormData(uploadForm);
        formData.append("csrf_token", csrfToken);

        startUploadBtn.disabled = true;
        startUploadBtn.textContent = "Uploading...";
        uploadProgress.style.display = "block";

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . FULL_BASE_PATH . 'api/admin/slop/upload.php", true);
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
            startUploadBtn.textContent = "Upload";
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
            startUploadBtn.textContent = "Upload";
            alert("Upload failed due to network error.");
            uploadProgress.style.display = "none";
        });

        xhr.send(formData);
    });

    // --- Edit Modal ---
    var editModal = document.getElementById("editModal");
    var closeEditBtn = document.getElementById("closeEditModalBtn");
    var cancelEditBtn = document.getElementById("cancelEditBtn");
    var editForm = document.getElementById("editForm");

    function openEditModal(id, title, desc, slug) {
        document.getElementById("editSlopId").value = id;
        document.getElementById("editTitle").value = title;
        document.getElementById("editDescription").value = desc;
        document.getElementById("editSlug").value = slug;
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

    editForm.addEventListener("submit", function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append("csrf_token", csrfToken);
        var submitBtn = this.querySelector("[type=\"submit\"]");
        submitBtn.disabled = true;
        submitBtn.textContent = "Saving...";
        postForm("' . FULL_BASE_PATH . 'api/admin/slop/update.php", formData, function() {
            closeEditModal();
            location.reload();
        }, function() {
            submitBtn.disabled = false;
            submitBtn.textContent = "Save Changes";
        });
    });

    // --- Row actions ---
    document.querySelectorAll("[data-action=\"edit\"]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            openEditModal(
                this.getAttribute("data-id"),
                this.getAttribute("data-title"),
                this.getAttribute("data-description"),
                this.getAttribute("data-slug")
            );
        });
    });

    document.querySelectorAll("[data-action=\"delete\"]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            if (!confirm("Delete this slop? The HTML file will be removed too.")) return;
            var formData = new FormData();
            formData.append("action", "delete");
            formData.append("id", this.getAttribute("data-id"));
            formData.append("csrf_token", csrfToken);
            postForm("' . FULL_BASE_PATH . 'api/admin/slop/delete.php", formData, function() {
                location.reload();
            });
        });
    });

    // --- Seed test example ---
    var seedBtn = document.getElementById("seedExampleBtn");
    if (seedBtn) {
        seedBtn.addEventListener("click", function() {
            seedBtn.disabled = true;
            var formData = new FormData();
            formData.append("action", "seed_example");
            formData.append("csrf_token", csrfToken);
            postForm("' . FULL_BASE_PATH . 'api/admin/slop/update.php", formData, function() {
                location.reload();
            }, function() {
                seedBtn.disabled = false;
            });
        });
    }
})();
</script>
';
include __DIR__ . '/../includes/footer.php';
?>
