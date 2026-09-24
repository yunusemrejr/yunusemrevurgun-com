<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

require_once dirname(__DIR__, 3) . '/global.php';
restrictDirectAccess();
?>
<?php
require_once __DIR__ . '/../../../global.php';
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Updates.php';
require_once dirname(__DIR__, 3) . '/services/MastodonService.php';
Auth::checkLogin();

$updates = new Updates();
$success = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (isset($_POST['auto_save']) && $_POST['auto_save'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Draft cached locally']);
        exit;
    } else {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['date'] ?? '')) ? $_POST['date'] : date('Y-m-d');
    $category = mb_substr(trim(strip_tags((string)($_POST['category'] ?? ''))), 0, 50);
    $importance = in_array(($_POST['importance'] ?? 'medium'), ['low', 'medium', 'high'], true) ? $_POST['importance'] : 'medium';

    if ($title === '') {
        $error = 'Title is required.';
    }

    $data = [
        'title' => $title,
        'content' => $content,
        'date' => $date,
        'category' => $category,
        'importance' => $importance,
        'post_to_mastodon' => isset($_POST['post_to_mastodon']),
        'post_to_bluesky' => isset($_POST['post_to_bluesky']),
    ];

    if (!$error) {
        try {
            $result = $updates->createUpdate($data);
            if ($result) {
                $success = true;
                $flashSuccess = [];
                $flashError = [];

                // Mastodon cross-posting — the update is already saved locally;
                // a failure never rolls back or deletes the update.
                if (!empty($data['post_to_mastodon'])) {
                    $mastodon = new MastodonService();
                    $updates->incrementMastodonAttempt($result);
                    if ($mastodon->isConfigured()) {
                        $visibility = in_array(($_POST['mastodon_visibility'] ?? ''), ['public', 'unlisted', 'private'], true)
                            ? $_POST['mastodon_visibility'] : null;
                        $pub = $mastodon->publish(
                            $title,
                            $content,
                            Updates::canonicalUpdateUrl($result),
                            MastodonService::idempotencyKeyForUpdate($result),
                            $visibility
                        );
                        if ($pub['success']) {
                            $updates->updateMastodonSync($result, [
                                'mastodon_status_id' => $pub['status_id'],
                                'mastodon_status_url' => $pub['status_url'],
                                'mastodon_sync_status' => 'published',
                                'mastodon_last_error' => null,
                                'mastodon_published_at' => date('Y-m-d H:i:s'),
                            ]);
                            $flashSuccess[] = 'Mastodon';
                        } else {
                            $updates->updateMastodonSync($result, [
                                'mastodon_sync_status' => 'failed',
                                'mastodon_last_error' => $pub['error'],
                            ]);
                            $flashError[] = 'Mastodon: ' . $pub['error'];
                        }
                    } else {
                        $updates->updateMastodonSync($result, [
                            'mastodon_sync_status' => 'failed',
                            'mastodon_last_error' => 'Mastodon is not configured (MASTODON_BASE_URL / MASTODON_ACCESS_TOKEN missing).',
                        ]);
                        $flashError[] = 'Mastodon is not configured (MASTODON_BASE_URL / MASTODON_ACCESS_TOKEN).';
                    }
                }

                // Bluesky cross-posting — independent of the Mastodon result.
                if (!empty($data['post_to_bluesky'])) {
                    $bluesky = new BlueskyService();
                    $updates->incrementBlueskyAttempt($result);
                    if ($bluesky->isConfigured()) {
                        $pub = $bluesky->publish(
                            $title,
                            $content,
                            Updates::canonicalUpdateUrl($result),
                            BlueskyService::rkeyForUpdate($result)
                        );
                        if ($pub['success']) {
                            $updates->updateBlueskySync($result, [
                                'bluesky_status_uri' => $pub['status_uri'],
                                'bluesky_status_url' => $pub['status_url'],
                                'bluesky_sync_status' => 'published',
                                'bluesky_last_error' => null,
                                'bluesky_published_at' => date('Y-m-d H:i:s'),
                            ]);
                            $flashSuccess[] = 'Bluesky';
                        } else {
                            $updates->updateBlueskySync($result, [
                                'bluesky_sync_status' => 'failed',
                                'bluesky_last_error' => $pub['error'],
                            ]);
                            $flashError[] = 'Bluesky: ' . $pub['error'];
                        }
                    } else {
                        $updates->updateBlueskySync($result, [
                            'bluesky_sync_status' => 'failed',
                            'bluesky_last_error' => 'Bluesky is not configured (BLUESKY_HANDLE / BLUESKY_APP_PASSWORD missing).',
                        ]);
                        $flashError[] = 'Bluesky is not configured (BLUESKY_HANDLE / BLUESKY_APP_PASSWORD).';
                    }
                }

                if ($flashSuccess) {
                    $_SESSION['success'] = 'Update created and published to ' . implode(' and ', $flashSuccess) . '.';
                }
                if ($flashError) {
                    $_SESSION['error'] = 'Update saved, but cross-posting failed: ' . implode(' | ', $flashError);
                }
                if (!$flashSuccess && !$flashError) {
                    $_SESSION['success'] = 'Update created successfully.';
                }
                // Redirect to updates list after successful creation
                header('Location: ' . FULL_BASE_PATH . 'admin/updates');
                exit;
            } else {
                $error = 'Failed to create update.';
            }
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Updates create error: " . $e->getMessage());
            }
            $error = 'An error occurred while creating the update.';
        }
    }
    }
}
?>
<?php
// Set page title
$page = "updates-create";
$pageTitle = "Create Update";
// Include header
ob_start();
?>
<div class="admin-page-header">
            <div>
                <p class="admin-page-subtitle">Add a new site update or announcement</p>
            </div>
            <div class="admin-page-actions">
                <a href="<?= FULL_BASE_PATH ?>admin/updates" class="admin-btn admin-btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Updates
                </a>
            </div>
        </div>
<?php
$pageHeader = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>




        <?php if ($error): ?>
            <div class="admin-alert admin-alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-bell me-2"></i>Update Details
                </h5>
            </div>
            <div class="admin-card-body">
                <form method="POST" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="admin-form-group">
                        <label for="title" class="admin-form-label">Title</label>
                        <input type="text" class="admin-form-control" id="title" name="title" placeholder="Enter update title" required>
                    </div>

                    <div class="admin-form-group">
                        <label for="content" class="admin-form-label">Description</label>
                        <div class="updates-md-editor">
                            <div class="updates-md-toolbar" role="toolbar" aria-label="Formatting toolbar">
                                <button type="button" data-md="bold" title="Bold" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
                                <button type="button" data-md="italic" title="Italic" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
                                <button type="button" data-md="link" title="Insert link" aria-label="Insert link"><i class="bi bi-link-45deg"></i></button>
                                <span class="updates-md-sep" aria-hidden="true"></span>
                                <button type="button" data-md="heading" title="Heading" aria-label="Heading"><i class="bi bi-heading"></i></button>
                                <button type="button" data-md="bullet" title="Bullet list" aria-label="Bullet list"><i class="bi bi-list-ul"></i></button>
                                <button type="button" data-md="quote" title="Quote" aria-label="Quote"><i class="bi bi-quote"></i></button>
                                <span class="updates-md-sep" aria-hidden="true"></span>
                                <button type="button" data-md="code" title="Inline code" aria-label="Inline code"><i class="bi bi-code-slash"></i></button>
                                <button type="button" data-md="hr" title="Horizontal rule" aria-label="Horizontal rule"><i class="bi bi-hr"></i></button>
                            </div>
                            <textarea class="admin-form-control" id="content" name="content" rows="10" placeholder="Write your update in markdown — **bold**, *italic*, [links](https://…), lists, quotes, headings."></textarea>
                            <div class="updates-md-preview" aria-live="polite"></div>
                        </div>
                        <div class="updates-md-help">
                            Markdown supported: <code>**bold**</code>, <code>*italic*</code>, <code>[label](https://…)</code>,
                            <code># Heading</code>, <code>- list</code>, <code>&gt; quote</code>, <code>`code`</code>.
                            Single Enter = line skip, blank line = new paragraph. Links open in a new tab.
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label for="date" class="admin-form-label">Date</label>
                        <input type="date" class="admin-form-control" id="date" name="date" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="admin-form-group">
                        <label for="category" class="admin-form-label">Category</label>
                        <input type="text" class="admin-form-control" id="category" name="category" placeholder="e.g., Work, Education, Personal">
                        <div class="admin-form-text">Optional: Categorize this update</div>
                    </div>

                    <div class="admin-form-group">
                        <label for="importance" class="admin-form-label">Importance</label>
                        <select class="admin-form-control" id="importance" name="importance">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                        <div class="admin-form-text">Set the importance level for this update</div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Mastodon cross-posting</label>
                        <div class="admin-form-check" style="margin-bottom: 0.75rem;">
                            <input type="checkbox" id="post_to_mastodon" name="post_to_mastodon" value="1" checked class="admin-form-check-input">
                            <label for="post_to_mastodon" class="admin-form-check-label">Post to Mastodon</label>
                        </div>
                        <div id="mastodonOptions">
                            <div class="admin-form-group">
                                <label for="mastodon_visibility" class="admin-form-label">Visibility</label>
                                <select class="admin-form-control" id="mastodon_visibility" name="mastodon_visibility">
                                    <option value="public">Public</option>
                                    <option value="unlisted">Unlisted</option>
                                    <option value="private">Private (followers only)</option>
                                </select>
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-form-label">Mastodon preview</label>
                                <div class="admin-mastodon-preview" id="mastodonPreview" data-limit="500">—</div>
                                <div class="admin-form-text" id="mastodonCharCount"></div>
                            </div>
                        </div>
                        <div class="admin-form-text">The update is always saved on the website first. If Mastodon is unavailable, the post can be retried later from the Updates list.</div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Bluesky cross-posting</label>
                        <div class="admin-form-check" style="margin-bottom: 0.75rem;">
                            <input type="checkbox" id="post_to_bluesky" name="post_to_bluesky" value="1" checked class="admin-form-check-input">
                            <label for="post_to_bluesky" class="admin-form-check-label">Post to Bluesky</label>
                        </div>
                        <div id="blueskyOptions">
                            <div class="admin-form-group">
                                <label class="admin-form-label">Bluesky preview</label>
                                <div class="admin-mastodon-preview" id="blueskyPreview" data-limit="300">—</div>
                                <div class="admin-form-text" id="blueskyCharCount"></div>
                            </div>
                        </div>
                        <div class="admin-form-text">The update is always saved on the website first. If Bluesky is unavailable, the post can be retried later from the Updates list.</div>
                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Create Update
                        </button>
                        <a href="<?= FULL_BASE_PATH ?>admin/updates" class="admin-btn admin-btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>



     <?php
$pageScripts = '
<style>
.admin-mastodon-preview {
  background: var(--color-bg-subtle);
  border: 1px solid var(--color-border);
  padding: 12px 14px;
  font-size: 0.875rem;
  color: var(--color-ink-body);
  white-space: pre-wrap;
  word-break: break-word;
  min-height: 70px;
  margin-top: 6px;
}
.admin-mastodon-preview.is-over {
  border-color: var(--color-danger);
  color: var(--color-danger);
}
</style>
<script>
(function() {
    "use strict";
    var titleEl = document.getElementById("title");
    var contentEl = document.getElementById("content");
    var postChk = document.getElementById("post_to_mastodon");
    var optionsEl = document.getElementById("mastodonOptions");
    var postBsky = document.getElementById("post_to_bluesky");
    var optionsBsky = document.getElementById("blueskyOptions");
    // Canonical base matches Updates::canonicalUpdateUrl() (production domain).
    var CANONICAL_BASE = "https://yunusemrevurgun.com/updates/";

    function mdToPlain(md) {
        var t = String(md || "");
        t = t.replace(/```[a-z]*\n?/gi, "");
        t = t.replace(/`([^`]+)`/g, "$1");
        t = t.replace(/!\[([^\]]*)\]\([^)]*\)/g, "$1");
        t = t.replace(/\[([^\]]+)\]\([^)]*\)/g, "$1");
        t = t.replace(/^#{1,6}\s+/gm, "");
        t = t.replace(/^>\s?/gm, "");
        t = t.replace(/^\s*[-*+]\s+/gm, "• ");
        t = t.replace(/\*\*([^*]+)\*\*/g, "$1");
        t = t.replace(/__([^_]+)__/g, "$1");
        t = t.replace(/\*([^*]+)\*/g, "$1");
        t = t.replace(/_([^_]+)_/g, "$1");
        t = t.replace(/\n{3,}/g, "\n\n");
        return t.trim();
    }

    function renderPreview(previewEl, countEl, checked, limit) {
        if (!checked) {
            previewEl.textContent = "—";
            countEl.textContent = "";
            return;
        }
        var title = (titleEl.value || "").trim();
        var body = mdToPlain(contentEl.value);
        var text = title + (title && body ? "\n\n" : "") + body;
        var hasUrl = body.indexOf(CANONICAL_BASE) !== -1;
        var link = hasUrl ? "" : "Read more:\n" + CANONICAL_BASE + "{id}";
        var full = text + (text && link ? "\n\n" : "") + link;
        if (!full.trim()) full = "—";
        previewEl.textContent = full;
        var len = full.length;
        countEl.textContent = "≈ " + len + " / " + limit + " characters" + (len > limit ? " — will be truncated to fit" : "");
        previewEl.classList.toggle("is-over", len > limit);
    }

    function renderAll() {
        renderPreview(
            document.getElementById("mastodonPreview"),
            document.getElementById("mastodonCharCount"),
            postChk ? postChk.checked : false,
            parseInt((document.getElementById("mastodonPreview") || {}).getAttribute ? (document.getElementById("mastodonPreview").getAttribute("data-limit") || "500") : "500", 10)
        );
        renderPreview(
            document.getElementById("blueskyPreview"),
            document.getElementById("blueskyCharCount"),
            postBsky ? postBsky.checked : false,
            parseInt((document.getElementById("blueskyPreview") || {}).getAttribute ? (document.getElementById("blueskyPreview").getAttribute("data-limit") || "300") : "300", 10)
        );
    }

    if (postChk && optionsEl) {
        postChk.addEventListener("change", function() {
            optionsEl.style.display = postChk.checked ? "" : "none";
            renderAll();
        });
        optionsEl.style.display = postChk.checked ? "" : "none";
    }
    if (postBsky && optionsBsky) {
        postBsky.addEventListener("change", function() {
            optionsBsky.style.display = postBsky.checked ? "" : "none";
            renderAll();
        });
        optionsBsky.style.display = postBsky.checked ? "" : "none";
    }
    if (titleEl) titleEl.addEventListener("input", renderAll);
    if (contentEl) contentEl.addEventListener("input", renderAll);
    renderAll();
})();
</script>';
include __DIR__ . '/../includes/footer.php';
?>
