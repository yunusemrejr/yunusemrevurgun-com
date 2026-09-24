<?php
/**
 * Admin — Socials
 * CRUD for the site-wide social profile links (site footer, contact page,
 * /more listing, home JSON-LD). Clean single-header admin pattern.
 */
require_once __DIR__ . '/../../../config/setPath.php';
require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Socials.php';
Auth::checkLogin();

$socials = new Socials();
$list = $socials->getAll();

$page = "socials";
$pageTitle = "Socials";
$actionButton = '<button type="button" class="admin-btn admin-btn-primary" id="addSocialBtn"><i class="bi bi-plus-lg me-2"></i>Add Social</button>';
include __DIR__ . '/../includes/header.php';
?>

<p class="admin-page-subtitle" style="margin: -1.25rem 0 1.5rem;">Social profiles shown in the site footer, the contact page, and the /more "Socials" listing. Visibility: <strong>Footer</strong> = site footer only, <strong>Contact</strong> = contact page only, <strong>Both</strong> = both. Inactive entries are hidden everywhere. Popup-type entries (e.g. the X/Twitter joke button) only ever appear in the footer and have no URL.</p>

<?php if (count($list) > 0): ?>
<div class="admin-table">
    <table>
        <thead>
            <tr>
                <th>Social</th>
                <th>URL</th>
                <th>Type</th>
                <th>Shown in</th>
                <th>Status</th>
                <th>Sort</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $item):
                $isPopup = $item['kind'] === Socials::KIND_POPUP;
            ?>
                <tr data-id="<?= $item['id'] ?>">
                    <td>
                        <div class="dl-app-cell">
                            <span class="soc-icon-cell"><?= Socials::iconSvg($item['icon'] ?? 'link') ?></span>
                            <div class="dl-app-text">
                                <span class="dl-app-title"><?= htmlspecialchars($item['name'] ?? 'Untitled') ?></span>
                                <?php if (!empty($item['handle'])): ?>
                                    <span class="dl-app-desc"><?= htmlspecialchars($item['handle']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($isPopup || empty($item['url'])): ?>
                            <span class="dl-muted">—</span>
                        <?php else: ?>
                            <a class="dl-source-link" href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer">
                                <?= htmlspecialchars(mb_strimwidth(preg_replace('#^https?://#', '', $item['url']), 0, 40, '…')) ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><span class="admin-badge admin-badge-secondary"><?= $isPopup ? 'Popup' : 'Link' ?></span></td>
                    <td><span class="admin-badge admin-badge-info<?= $item['visibility'] === 'both' ? '' : ' admin-badge-secondary' ?>"><?= htmlspecialchars(ucfirst($item['visibility'])) ?></span></td>
                    <td>
                        <span class="admin-badge<?= $item['active'] ? ' admin-badge-success' : ' admin-badge-secondary' ?>"><?= $item['active'] ? 'Active' : 'Inactive' ?></span>
                    </td>
                    <td><?= (int) $item['sort_order'] ?></td>
                    <td>
                        <div class="admin-page-actions">
                            <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-action="edit"
                                data-id="<?= $item['id'] ?>"
                                data-name="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>"
                                data-url="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>"
                                data-handle="<?= htmlspecialchars($item['handle'] ?? '', ENT_QUOTES) ?>"
                                data-icon="<?= htmlspecialchars($item['icon'] ?? 'link', ENT_QUOTES) ?>"
                                data-kind="<?= htmlspecialchars($item['kind'] ?? 'link', ENT_QUOTES) ?>"
                                data-visibility="<?= htmlspecialchars($item['visibility'] ?? 'both', ENT_QUOTES) ?>"
                                data-active="<?= $item['active'] ? '1' : '0' ?>"
                                data-sort="<?= (int) $item['sort_order'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>">
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
    <i class="bi bi-share"></i>
    <p>No socials yet. Add your first social profile.</p>
</div>
<?php endif; ?>

<!-- Add / Edit Modal -->
<div class="admin-upload-modal" id="socialModal" tabindex="-1" aria-hidden="true">
    <div class="admin-upload-modal-content">
        <div class="admin-modal-header">
            <h5 class="admin-modal-title" id="socialModalTitle">Add Social</h5>
            <button type="button" class="admin-btn-close" id="closeSocialModalBtn" aria-label="Close">&times;</button>
        </div>
        <form id="socialForm">
            <div class="admin-modal-body">
                <input type="hidden" id="socId" name="id" value="">

                <div class="admin-form-group">
                    <label for="socName" class="admin-form-label">Name <span class="dl-required">*</span></label>
                    <input type="text" class="admin-form-control" id="socName" name="name" placeholder="GitHub" required autocomplete="off">
                </div>

                <div class="admin-form-group">
                    <label for="socKind" class="admin-form-label">Type</label>
                    <select class="admin-form-control" id="socKind" name="kind">
                        <option value="link">Link (opens a profile URL)</option>
                        <option value="popup">Popup (footer joke button, e.g. X/Twitter)</option>
                    </select>
                    <small class="dl-help">Popup entries appear only in the site footer and ignore the URL field.</small>
                </div>

                <div class="admin-form-group">
                    <label for="socUrl" class="admin-form-label">URL</label>
                    <input type="url" class="admin-form-control" id="socUrl" name="url" placeholder="https://github.com/username" autocomplete="off">
                    <small class="dl-help" id="socUrlHelp">Required for link-type socials.</small>
                </div>

                <div class="admin-form-group">
                    <label for="socHandle" class="admin-form-label">Handle / display text</label>
                    <input type="text" class="admin-form-control" id="socHandle" name="handle" placeholder="@username" autocomplete="off">
                    <small class="dl-help">Shown on the contact page and /more listing (e.g. @yunusemrejr, or "Profile").</small>
                </div>

                <div class="admin-form-group">
                    <label for="socIcon" class="admin-form-label">Icon</label>
                    <div class="soc-icon-picker">
                        <select class="admin-form-control" id="socIcon" name="icon"></select>
                        <span class="soc-icon-preview" id="socIconPreview"></span>
                    </div>
                    <small class="dl-help">Brand icon used in the site footer and /more listing. "Link" is a generic fallback.</small>
                </div>

                <div class="admin-form-group">
                    <label for="socVisibility" class="admin-form-label">Shown in</label>
                    <select class="admin-form-control" id="socVisibility" name="visibility">
                        <option value="both">Footer + Contact page</option>
                        <option value="footer">Footer only</option>
                        <option value="contact">Contact page only</option>
                    </select>
                    <small class="dl-help">All active socials also appear in the /more "Socials" listing regardless of this setting.</small>
                </div>

                <div class="admin-form-group">
                    <label for="socSort" class="admin-form-label">Sort order</label>
                    <input type="number" class="admin-form-control" id="socSort" name="sort_order" value="0" min="0" max="9999" autocomplete="off">
                    <small class="dl-help">Lower numbers appear first.</small>
                </div>

                <div class="admin-form-check">
                    <input type="checkbox" id="socActive" name="active" value="1" checked>
                    <label for="socActive">Active (shown on the site)</label>
                </div>
            </div>
            <div class="admin-modal-footer">
                <div class="admin-upload-buttons">
                    <button type="button" class="admin-btn admin-btn-secondary" id="cancelSocialBtn">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" id="saveSocialBtn">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = '
<style>
.soc-icon-cell { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; color: var(--color-ink-body); flex-shrink: 0; }
.soc-icon-cell svg { width: 22px; height: 22px; }
.soc-icon-picker { display: flex; align-items: center; gap: 12px; }
.soc-icon-picker select { flex: 1; }
.soc-icon-preview { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; color: var(--color-ink-body); }
.soc-icon-preview svg { width: 24px; height: 24px; }
.admin-form-check { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.admin-form-check input { width: 16px; height: 16px; }
.admin-form-check label { color: var(--color-ink-body); font-size: 0.9rem; }
</style>
<script>
(function() {
    "use strict";
    var csrfMeta = document.querySelector("meta[name=\'csrf-token\']");
    var csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : "";
    var SAVE_URL = "' . FULL_BASE_PATH . 'api/admin/socials/save.php";
    var DELETE_URL = "' . FULL_BASE_PATH . 'api/admin/socials/delete.php";
    var ICONS = ' . json_encode(Socials::icons()) . ';

    var modal = document.getElementById("socialModal");
    var modalTitle = document.getElementById("socialModalTitle");
    var form = document.getElementById("socialForm");
    var idInput = document.getElementById("socId");
    var kindSelect = document.getElementById("socKind");
    var urlInput = document.getElementById("socUrl");
    var urlHelp = document.getElementById("socUrlHelp");
    var iconSelect = document.getElementById("socIcon");
    var iconPreview = document.getElementById("socIconPreview");
    var saveBtn = document.getElementById("saveSocialBtn");

    // Populate icon select once.
    Object.keys(ICONS).forEach(function(key) {
        var opt = document.createElement("option");
        opt.value = key;
        opt.textContent = key.charAt(0).toUpperCase() + key.slice(1);
        iconSelect.appendChild(opt);
    });

    function renderIconPreview(key) {
        var data = ICONS[key] || ICONS.link;
        iconPreview.innerHTML = "<svg viewBox=\"" + data[0] + "\" fill=\"currentColor\" aria-hidden=\"true\">" + data[1] + "</svg>";
    }

    function syncKindFields() {
        var popup = kindSelect.value === "popup";
        urlInput.disabled = popup;
        urlInput.required = !popup;
        urlHelp.textContent = popup ? "Popup entries have no URL." : "Required for link-type socials.";
        if (popup) urlInput.value = "";
    }

    function openModal(data) {
        form.reset();
        if (data) {
            modalTitle.textContent = "Edit Social";
            idInput.value = data.id;
            document.getElementById("socName").value = data.name;
            kindSelect.value = data.kind;
            syncKindFields();
            if (data.kind !== "popup") urlInput.value = data.url;
            document.getElementById("socHandle").value = data.handle;
            iconSelect.value = data.icon;
            document.getElementById("socVisibility").value = data.visibility;
            document.getElementById("socSort").value = data.sort;
            document.getElementById("socActive").checked = data.active === "1";
        } else {
            modalTitle.textContent = "Add Social";
            idInput.value = "";
            kindSelect.value = "link";
            syncKindFields();
            iconSelect.value = "github";
            document.getElementById("socVisibility").value = "both";
            document.getElementById("socSort").value = "0";
            document.getElementById("socActive").checked = true;
        }
        renderIconPreview(iconSelect.value);
        modal.style.display = "flex";
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        document.getElementById("socName").focus();
    }

    function closeModal() {
        modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    document.getElementById("addSocialBtn").addEventListener("click", function() { openModal(null); });
    document.getElementById("closeSocialModalBtn").addEventListener("click", closeModal);
    document.getElementById("cancelSocialBtn").addEventListener("click", closeModal);
    modal.addEventListener("click", function(e) { if (e.target === modal) closeModal(); });
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && modal.style.display === "flex") closeModal();
    });
    kindSelect.addEventListener("change", syncKindFields);
    iconSelect.addEventListener("change", function() { renderIconPreview(this.value); });

    document.querySelectorAll("[data-action=\'edit\']").forEach(function(btn) {
        btn.addEventListener("click", function() {
            openModal({
                id: this.getAttribute("data-id"),
                name: this.getAttribute("data-name"),
                url: this.getAttribute("data-url"),
                handle: this.getAttribute("data-handle"),
                icon: this.getAttribute("data-icon"),
                kind: this.getAttribute("data-kind"),
                visibility: this.getAttribute("data-visibility"),
                active: this.getAttribute("data-active"),
                sort: this.getAttribute("data-sort")
            });
        });
    });

    document.querySelectorAll("[data-action=\'delete\']").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var name = this.getAttribute("data-name") || "this social";
            if (!confirm("Delete \"" + name + "\" permanently?")) return;
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
