<?php
require_once __DIR__ . '/../../../config/setPath.php';

require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Updates.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$updates = new Updates();

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get paginated updates
$allUpdates = $updates->getPaginatedUpdates($offset, $itemsPerPage);
$totalUpdates = $updates->getTotalUpdates();
$totalPages = ceil($totalUpdates / $itemsPerPage);

// Handle delete action via POST with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $id = (int)$_POST['delete'];
        if ($updates->deleteUpdate($id)) {
            $_SESSION['success'] = 'Update deleted successfully.';
        } else {
            $_SESSION['error'] = 'Failed to delete update.';
        }
    }
    header('Location: ' . FULL_BASE_PATH . 'admin/updates');
    exit;
}
?>
<?php
// Set page title
$page = "updates";
$pageTitle = "Updates";
// Include header
include __DIR__ . '/../includes/header.php';
?>
 
<main class="admin-main">
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <p class="admin-page-subtitle">Manage your site updates and announcements</p>
            </div>
            <div class="admin-page-actions">
                <a href="<?= FULL_BASE_PATH ?>admin/updates/create" class="admin-btn admin-btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add New Update
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="admin-alert admin-alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="admin-alert admin-alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-bell me-2"></i>All Updates
                </h5>
            </div>
            <div class="admin-card-body">
                <?php if (count($allUpdates) > 0): ?>
                    <div class="admin-updates-table admin-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Importance</th>
                                    <th>Mastodon</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUpdates as $update): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($update['title']) ?></td>
                                        <td><?= !empty($update['update_date']) ? date('M d, Y', strtotime($update['update_date'])) : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($update['category'] ?? 'General') ?></td>
                                        <td>
                                            <?php if ($update['importance'] === 'high'): ?>
                                                <span class="admin-badge admin-badge-danger">High</span>
                                            <?php elseif ($update['importance'] === 'medium'): ?>
                                                <span class="admin-badge admin-badge-warning">Medium</span>
                                            <?php else: ?>
                                                <span class="admin-badge admin-badge-info">Low</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $mSync = $update['mastodon_sync_status'] ?? 'not_requested';
                                            $mUrl = $update['mastodon_status_url'] ?? '';
                                            $mErr = $update['mastodon_last_error'] ?? '';
                                            ?>
                                            <?php if ($mSync === 'published'): ?>
                                                <span class="admin-badge admin-badge-success">Published</span>
                                                <?php if ($mUrl !== ''): ?>
                                                    <a class="dl-source-link" style="display:block;margin-top:4px;" href="<?= htmlspecialchars($mUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" title="Open Mastodon post">Mastodon ↗</a>
                                                <?php endif; ?>
                                            <?php elseif ($mSync === 'pending'): ?>
                                                <span class="admin-badge admin-badge-info">Pending</span>
                                                <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" style="display:block;margin-top:4px;" data-mastodon-retry="<?= $update['id'] ?>" title="Publish to Mastodon now">Retry</button>
                                            <?php elseif ($mSync === 'failed'): ?>
                                                <span class="admin-badge admin-badge-danger" title="<?= htmlspecialchars($mErr, ENT_QUOTES) ?>">Failed</span>
                                                <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" style="display:block;margin-top:4px;" data-mastodon-retry="<?= $update['id'] ?>" title="Retry Mastodon posting">Retry</button>
                                            <?php else: ?>
                                                <span class="admin-badge admin-badge-secondary">Not posted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="admin-action-buttons">
                                                <a href="<?= FULL_BASE_PATH ?>admin/updates/edit?id=<?= $update['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </a>
                                                <form method="POST" class="admin-inline-form" onsubmit="return confirm('Are you sure you want to delete this update?');" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="delete" value="<?= $update['id'] ?>">
                                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php echo renderPagination($currentPage, $totalPages, FULL_BASE_PATH . 'admin/updates'); ?>
                    
                <?php else: ?>
                    <p class="admin-empty-state">
                        <i class="bi bi-inbox me-2"></i>
                        No updates yet. <a href="<?= FULL_BASE_PATH ?>admin/updates/create">Create your first update</a>.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
        

     <?php
$pageScripts = '
<script>
(function() {
    "use strict";
    var csrfMeta = document.querySelector("meta[name=\'csrf-token\']");
    var csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : "";
    var RETRY_URL = "' . FULL_BASE_PATH . 'api/admin/mastodon/retry.php";
    document.querySelectorAll("[data-mastodon-retry]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var id = this.getAttribute("data-mastodon-retry");
            if (!confirm("Publish update #" + id + " to Mastodon now?")) return;
            this.disabled = true;
            this.textContent = "Posting...";
            var fd = new FormData();
            fd.append("id", id);
            fd.append("csrf_token", csrfToken);
            var xhr = new XMLHttpRequest();
            xhr.open("POST", RETRY_URL, true);
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            xhr.addEventListener("load", function() {
                var resp;
                try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = null; }
                if (resp && resp.success) { location.reload(); }
                else { alert("Mastodon posting failed: " + ((resp && resp.message) || "Unknown error")); location.reload(); }
            });
            xhr.addEventListener("error", function() { alert("Network error while posting to Mastodon."); location.reload(); });
            xhr.send(fd);
        });
    });
})();
</script>';
include __DIR__ . '/../includes/footer.php';
?>  
