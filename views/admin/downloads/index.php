<?php
/**
 * Admin — Downloads
 * CRUD for the public /downloads page (desktop/offline apps). Files are hosted
 * externally (GitHub); only metadata + thumbnails are stored here. Uses the
 * clean single-header admin pattern (header.php renders the page header).
 */
require_once __DIR__ . '/../../../config/setPath.php';
require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Downloads.php';
Auth::checkLogin();

$downloads = new Downloads();
$list = $downloads->getAllDownloads();
$thumbBase = FULL_BASE_PATH . Downloads::UPLOAD_DIR . '/';

$page = "downloads";
$pageTitle = "Downloads";
$actionButton = '<button type="button" class="admin-btn admin-btn-primary" id="addDownloadBtn"><i class="bi bi-plus-lg me-2"></i>Add Download</button>';
include __DIR__ . '/../includes/header.php';
?>

<p class="admin-page-subtitle" style="margin: -1.25rem 0 1.5rem;">Desktop &amp; offline apps for visitors. Binaries stay on GitHub — only metadata and thumbnails are stored here.</p>

<?php if (count($list) > 0): ?>
<div class="admin-table">
    <table>
        <thead>
            <tr>
                <th>App</th>
                <th>Platforms</th>
                <th>Source</th>
                <th>Added</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $item):
                $platforms = Downloads::decodeList($item['platforms'] ?? null);
                $dependencies = Downloads::decodeList($item['dependencies'] ?? null);
                $hasThumb = !empty($item['thumbnail']);
            ?>
                <tr data-id="<?= $item['id'] ?>">
                    <td>
                        <div class="dl-app-cell">
                            <?php if ($hasThumb): ?>
                                <img src="<?= htmlspecialchars($thumbBase . $item['thumbnail']) ?>" alt="" class="dl-thumb" loading="lazy" width="56" height="56">
                            <?php else: ?>
                                <span class="dl-thumb dl-thumb-empty" aria-hidden="true"><i class="bi bi-box-arrow-down"></i></span>
                            <?php endif; ?>
                            <div class="dl-app-text">
                                <span class="dl-app-title"><?= htmlspecialchars($item['title'] ?? 'Untitled') ?></span>
                                <?php if (!empty($item['description'])): ?>
                                    <span class="dl-app-desc"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 90, '…')) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($dependencies)): ?>
                                    <span class="dl-app-deps"><i class="bi bi-box-seam"></i> <?= htmlspecialchars(implode(', ', $dependencies)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($platforms)): ?>
                            <?php foreach ($platforms as $p): ?>
                                <span class="admin-badge admin-badge-secondary dl-platform"><?= htmlspecialchars($p) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="dl-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="dl-source-link" href="<?= htmlspecialchars($item['download_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-github"></i> <?= htmlspecialchars(mb_strimwidth(preg_replace('#^https?://#', '', $item['download_url'] ?? ''), 0, 34, '…')) ?>
                        </a>
                    </td>
                    <td><?= !empty($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : '—' ?></td>
                    <td>
                        <div class="admin-page-actions">
                            <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit"
                                data-id="<?= $item['id'] ?>"
                                data-title="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>"
                                data-url="<?= htmlspecialchars($item['download_url'] ?? '', ENT_QUOTES) ?>"
                                data-description="<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>"
                                data-platforms="<?= htmlspecialchars(implode(', ', $platforms), ENT_QUOTES) ?>"
                                data-dependencies="<?= htmlspecialchars(implode(', ', $dependencies), ENT_QUOTES) ?>"
                                data-thumbnail="<?= $hasThumb ? htmlspecialchars($thumbBase . $item['thumbnail'], ENT_QUOTES) : '' ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete" data-id="<?= $item['id'] ?>" data-title="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>">
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
    <i class="bi bi-box-arrow-down"></i>
    <p>No downloads yet. Add your first desktop app.</p>
</div>
<?php endif; ?>

<!-- Add / Edit Modal -->
<div class="admin-upload-modal" id="downloadModal" tabindex="-1" aria-hidden="true">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title" id="downloadModalTitle">Add Download</h5>
            <button type="button" class="admin-btn-close" id="closeDownloadModalBtn" aria-label="Close">&times;</button>
        </div>
        <form id="downloadForm">
            <div class="admin-modal-body">
                <input type="hidden" id="dlId" name="id" value="">
                <input type="hidden" id="dlRemoveThumbnail" name="remove_thumbnail" value="0">

                <div class="admin-form-group">
                    <label for="dlTitle" class="admin-form-label">Title <span class="dl-required">*</span></label>
                    <input type="text" class="admin-form-control" id="dlTitle" name="title" placeholder="App name" required autocomplete="off">
                </div>

                <div class="admin-form-group">
                    <label for="dlUrl" class="admin-form-label">Download URL <span class="dl-required">*</span></label>
                    <input type="url" class="admin-form-control" id="dlUrl" name="download_url" placeholder="https://github.com/user/repo/releases" required autocomplete="off">
                    <small class="dl-help">Link to the GitHub release or repository — files are not hosted here.</small>
                </div>

                <div class="admin-form-group">
                    <label for="dlDescription" class="admin-form-label">Description</label>
                    <textarea class="admin-form-control" id="dlDescription" name="description" rows="3" placeholder="What does this app do?"></textarea>
                </div>

                <div class="admin-form-group">
                    <label for="dlPlatforms" class="admin-form-label">Supported Platforms</label>
                    <input type="text" class="admin-form-control" id="dlPlatforms" name="platforms" placeholder="Windows, macOS, Linux" autocomplete="off">
                    <small class="dl-help">Comma-separated list.</small>
                </div>

                <div class="admin-form-group">
                    <label for="dlDependencies" class="admin-form-label">Required Dependencies</label>
                    <input type="text" class="admin-form-control" id="dlDependencies" name="dependencies" placeholder=".NET 8 Runtime, Visual C++ Redistributable" autocomplete="off">
                    <small class="dl-help">Comma-separated list of anything the user must install first.</small>
                </div>

                <div class="admin-form-group">
                    <label class="admin-form-label">Thumbnail</label>
                    <div class="admin-upload-dropzone" id="dlDropzone">
                        <i class="bi bi-image admin-upload-dropzone-icon"></i>
                        <p class="admin-upload-dropzone-text">Drop an image or click to browse</p>
                        <p class="admin-upload-dropzone-hint">JPG, PNG, WebP, or GIF. Max 5MB.</p>
                        <input type="file" id="dlThumbnail" name="thumbnail" accept=".jpg,.jpeg,.png,.webp,.gif" class="admin-upload-input">
                    </div>
                    <div class="dl-preview" id="dlPreview" style="display:none;">
                        <img id="dlPreviewImg" src="" alt="Thumbnail preview">
                        <button type="button" class="admin-btn admin-btn-warning admin-btn-sm" id="dlRemoveThumbBtn" style="display:none;">
                            <i class="bi bi-x-circle me-2"></i>Remove thumbnail
                        </button>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <div class="admin-upload-buttons">
                    <button type="button" class="admin-btn admin-btn-secondary" id="cancelDownloadBtn">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" id="saveDownloadBtn">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = '
<style>
.dl-app-cell { display: flex; align-items: center; gap: 12px; }
.dl-thumb { width: 56px; height: 56px; object-fit: cover; border: 1px solid var(--color-border); border-radius: var(--radius-md); flex-shrink: 0; background: var(--color-bg-subtle); }
.dl-thumb-empty { display: flex; align-items: center; justify-content: center; color: var(--color-accent); font-size: 1.25rem; }
.dl-app-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.dl-app-title { font-weight: 600; color: var(--color-ink-body); }
.dl-app-desc { font-size: 0.8rem; color: var(--color-accent); }
.dl-app-deps { font-size: 0.75rem; color: var(--color-accent); }
.dl-platform { margin: 0 4px 4px 0; }
.dl-muted { color: var(--color-text-tertiary); }
.dl-source-link { color: var(--color-ink-body); text-decoration: none; font-size: 0.85rem; }
.dl-source-link:hover { color: var(--color-accent); text-decoration: underline; }
.dl-required { color: var(--color-danger); }
.dl-help { display: block; margin-top: 4px; color: var(--color-accent); font-size: 0.8rem; }
.dl-preview { margin-top: 12px; display: flex; align-items: center; gap: 12px; }
.dl-preview img { width: 80px; height: 80px; object-fit: cover; border: 1px solid var(--color-border); border-radius: var(--radius-md); }
</style>
<script>
(function() {
    "use strict";
    var csrfMeta = document.querySelector("meta[name=\'csrf-token\']");
    var csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : "";
    var SAVE_URL = "' . FULL_BASE_PATH . 'api/admin/downloads/save.php";
    var DELETE_URL = "' . FULL_BASE_PATH . 'api/admin/downloads/delete.php";

    var modal = document.getElementById("downloadModal");
    var modalTitle = document.getElementById("downloadModalTitle");
    var form = document.getElementById("downloadForm");
    var idInput = document.getElementById("dlId");
    var removeThumbInput = document.getElementById("dlRemoveThumbnail");
    var fileInput = document.getElementById("dlThumbnail");
    var dropzone = document.getElementById("dlDropzone");
    var preview = document.getElementById("dlPreview");
    var previewImg = document.getElementById("dlPreviewImg");
    var removeThumbBtn = document.getElementById("dlRemoveThumbBtn");
    var saveBtn = document.getElementById("saveDownloadBtn");

    var existingThumbUrl = "";   // thumbnail already saved on the server (edit mode)
    var pendingFile = null;      // newly selected file in this session
    var removed = false;         // user asked to remove the existing thumbnail

    function openModal(data) {
        form.reset();
        pendingFile = null;
        removed = false;
        removeThumbInput.value = "0";
        if (data) {
            modalTitle.textContent = "Edit Download";
            idInput.value = data.id;
            document.getElementById("dlTitle").value = data.title;
            document.getElementById("dlUrl").value = data.url;
            document.getElementById("dlDescription").value = data.description;
            document.getElementById("dlPlatforms").value = data.platforms;
            document.getElementById("dlDependencies").value = data.dependencies;
            existingThumbUrl = data.thumbnail || "";
        } else {
            modalTitle.textContent = "Add Download";
            idInput.value = "";
            existingThumbUrl = "";
        }
        renderPreview();
        modal.style.display = "flex";
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        document.getElementById("dlTitle").focus();
    }

    function closeModal() {
        modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        pendingFile = null;
        existingThumbUrl = "";
        removed = false;
    }

    function renderPreview() {
        if (pendingFile) {
            var reader = new FileReader();
            reader.onload = function(e) { previewImg.src = e.target.result; };
            reader.readAsDataURL(pendingFile);
            removeThumbBtn.style.display = "none";
            preview.style.display = "flex";
        } else if (existingThumbUrl && !removed) {
            previewImg.src = existingThumbUrl;
            removeThumbBtn.style.display = "";
            preview.style.display = "flex";
        } else {
            previewImg.src = "";
            preview.style.display = "none";
            removeThumbBtn.style.display = "none";
        }
    }

    document.getElementById("addDownloadBtn").addEventListener("click", function() { openModal(null); });
    document.getElementById("closeDownloadModalBtn").addEventListener("click", closeModal);
    document.getElementById("cancelDownloadBtn").addEventListener("click", closeModal);
    modal.addEventListener("click", function(e) { if (e.target === modal) closeModal(); });
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && modal.style.display === "flex") closeModal();
    });

    // File selection (input is inside the dropzone — guard against bubbled clicks).
    fileInput.addEventListener("change", function() {
        pendingFile = this.files[0] || null;
        removed = false;
        removeThumbInput.value = "0";
        renderPreview();
    });
    dropzone.addEventListener("click", function(e) {
        if (e.target.type === "file") return;
        fileInput.click();
    });
    dropzone.addEventListener("dragover", function(e) { e.preventDefault(); dropzone.classList.add("is-dragover"); });
    dropzone.addEventListener("dragleave", function() { dropzone.classList.remove("is-dragover"); });
    dropzone.addEventListener("drop", function(e) {
        e.preventDefault();
        dropzone.classList.remove("is-dragover");
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event("change"));
        }
    });

    removeThumbBtn.addEventListener("click", function() {
        removed = true;
        pendingFile = null;
        fileInput.value = "";
        removeThumbInput.value = "1";
        renderPreview();
    });

    // Edit buttons populate the modal from data attributes.
    document.querySelectorAll("[data-action=\'edit\']").forEach(function(btn) {
        btn.addEventListener("click", function() {
            openModal({
                id: this.getAttribute("data-id"),
                title: this.getAttribute("data-title"),
                url: this.getAttribute("data-url"),
                description: this.getAttribute("data-description"),
                platforms: this.getAttribute("data-platforms"),
                dependencies: this.getAttribute("data-dependencies"),
                thumbnail: this.getAttribute("data-thumbnail")
            });
        });
    });

    // Delete buttons.
    document.querySelectorAll("[data-action=\'delete\']").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var title = this.getAttribute("data-title") || "this download";
            if (!confirm("Delete \\"" + title + "\\" permanently?")) return;
            var fd = new FormData();
            fd.append("id", this.getAttribute("data-id"));
            fd.append("csrf_token", csrfToken);
            post(DELETE_URL, fd, function(resp) {
                if (resp.success) { location.reload(); }
                else { alert("Delete failed: " + (resp.message || "Unknown error")); }
            });
        });
    });

    form.addEventListener("submit", function(e) {
        e.preventDefault();
        var fd = new FormData(form);
        fd.append("csrf_token", csrfToken);
        saveBtn.disabled = true;
        saveBtn.textContent = "Saving...";
        post(SAVE_URL, fd, function(resp) {
            saveBtn.disabled = false;
            saveBtn.textContent = "Save";
            if (resp.success) { closeModal(); location.reload(); }
            else { alert("Save failed: " + (resp.message || "Unknown error")); }
        }, function() {
            saveBtn.disabled = false;
            saveBtn.textContent = "Save";
            alert("Save failed due to a network error.");
        });
    });

    function post(url, fd, onDone, onError) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.addEventListener("load", function() {
            try { onDone(JSON.parse(xhr.responseText)); }
            catch (err) { alert("Unexpected server response."); }
        });
        if (onError) xhr.addEventListener("error", onError);
        xhr.send(fd);
    }
})();
</script>';

include __DIR__ . '/../includes/footer.php';
?>
