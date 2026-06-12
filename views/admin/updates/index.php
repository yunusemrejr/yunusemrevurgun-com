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
// Include footer
include __DIR__ . '/../includes/footer.php';
?>  
