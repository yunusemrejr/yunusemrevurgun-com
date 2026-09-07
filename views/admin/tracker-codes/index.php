<?php
require_once __DIR__ . '/../../../config/setPath.php';

require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();
verifyAdminAction();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Tracker.php';
Auth::checkLogin();

$tracker = new Tracker();
$trackerCodes = $tracker->getAllTrackerCodes();

// Handle delete action via POST with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $id = $_POST['id'];
        if ($tracker->deleteTrackerCode($id)) {
            $_SESSION['success'] = "Tracker code deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete tracker code.";
        }
    }
    header("Location: " . FULL_BASE_PATH . "admin/tracker-codes");
    exit();
}
?>
<?php
// Set page title
$page = "tracker-codes";
$pageTitle = "Tracker Codes";
// Include header
ob_start();
?>
<div class="admin-page-header">
            <div>
                <p class="admin-page-subtitle">Manage your analytics and tracking codes</p>
            </div>
            <div class="admin-page-actions">
                <a href="<?= FULL_BASE_PATH ?>admin/tracker-codes/create" class="admin-btn admin-btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add New Tracker Code
                </a>
            </div>
        </div>
<?php
$pageHeader = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>




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
                    <i class="bi bi-code-slash me-2"></i>All Tracker Codes
                </h5>
            </div>
            <div class="admin-card-body">
                <?php if (count($trackerCodes) > 0): ?>
                    <div class="admin-tracker-table admin-table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trackerCodes as $code): ?>
                                <tr>
                                    <td><?= htmlspecialchars($code['id']) ?></td>
                                    <td><?= htmlspecialchars($code['name']) ?></td>
                                    <td>
                                        <?php if ($code['is_active']): ?>
                                            <span class="admin-badge admin-badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="admin-action-buttons">
                                            <a href="<?= FULL_BASE_PATH ?>admin/tracker-codes/edit?id=<?= $code['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </a>
                                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Are you sure you want to delete this tracker code?');" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="id" value="<?= $code['id'] ?>">
                                                <button type="submit" name="delete" class="admin-btn admin-btn-danger admin-btn-sm">
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
                <?php else: ?>
                    <p class="admin-empty-state">
                        <i class="bi bi-inbox me-2"></i>
                        No tracker codes yet. <a href="<?= FULL_BASE_PATH ?>admin/tracker-codes/create">Create your first tracker code</a>.
                    </p>
                <?php endif; ?>
            </div>
        </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
