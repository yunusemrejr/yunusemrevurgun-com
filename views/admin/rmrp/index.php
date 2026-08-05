<?php
/**
 * Admin — Random Memories for Random People (RMRP)
 * CRUD list for the public /rmrp page. Sibling of the Updates admin, using
 * the clean single-header pattern (header.php renders the page header).
 */
require_once dirname(__DIR__, 3) . '/config/setPath.php';

require_once dirname(__DIR__, 3) . '/global.php';
restrictDirectAccess();
verifyAdminAction();
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Rmrp.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$rmrp = new Rmrp();

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get paginated memories
$allMemories = $rmrp->getPaginatedMemories($offset, $itemsPerPage);
$totalMemories = $rmrp->getTotalMemories();
$totalPages = ceil($totalMemories / $itemsPerPage);

// Handle delete action via POST with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $id = (int)$_POST['delete'];
        if ($rmrp->deleteMemory($id)) {
            $_SESSION['success'] = 'Memory deleted successfully.';
        } else {
            $_SESSION['error'] = 'Failed to delete memory.';
        }
    }
    header('Location: ' . FULL_BASE_PATH . 'admin/rmrp');
    exit;
}

$page = "rmrp";
$pageTitle = "Random Memories";
$actionButton = '<a href="' . FULL_BASE_PATH . 'admin/rmrp/create" class="admin-btn admin-btn-primary"><i class="bi bi-plus-circle me-2"></i>Add New Memory</a>';
include __DIR__ . '/../includes/header.php';
?>

<p class="admin-page-subtitle" style="margin: -1.25rem 0 1.5rem;">Random memories for random people — fragments and fleeting thoughts, separate from the Updates log.</p>

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
            <i class="bi bi-dice-5 me-2"></i>All Memories
        </h5>
    </div>
    <div class="admin-card-body">
        <?php if (count($allMemories) > 0): ?>
            <div class="admin-table-scroll">
                <table class="admin-table">
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
                        <?php foreach ($allMemories as $memory): ?>
                            <tr>
                                <td><?= htmlspecialchars($memory['title']) ?></td>
                                <td><?= !empty($memory['memory_date']) ? date('M d, Y', strtotime($memory['memory_date'])) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($memory['category'] ?? 'General') ?></td>
                                <td>
                                    <?php if ($memory['importance'] === 'high'): ?>
                                        <span class="admin-badge admin-badge-danger">High</span>
                                    <?php elseif ($memory['importance'] === 'medium'): ?>
                                        <span class="admin-badge admin-badge-warning">Medium</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-info">Low</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="admin-action-buttons">
                                        <a href="<?= FULL_BASE_PATH ?>admin/rmrp/edit?id=<?= $memory['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                        <form method="POST" class="admin-inline-form" onsubmit="return confirm('Are you sure you want to delete this memory?');" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="delete" value="<?= $memory['id'] ?>">
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

            <?php echo renderPagination($currentPage, $totalPages, FULL_BASE_PATH . 'admin/rmrp'); ?>

        <?php else: ?>
            <p class="admin-empty-state">
                <i class="bi bi-dice-5 me-2"></i>
                No memories yet. <a href="<?= FULL_BASE_PATH ?>admin/rmrp/create">Write your first memory</a>.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
