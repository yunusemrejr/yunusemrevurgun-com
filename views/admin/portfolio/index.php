<?php
require_once __DIR__ . '/../../../config/setPath.php';

require_once __DIR__ . '/../../../global.php';
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Portfolio.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$portfolio = new Portfolio();

// Handle delete action via POST with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $id = (int)$_POST['delete'];
        if ($portfolio->deleteProject($id)) {
            $_SESSION['success'] = 'Project deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete project.';
        }
    }
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio');
    exit;
}

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get paginated projects
$projects = $portfolio->getPaginatedProjects($offset, $itemsPerPage);
$totalProjects = $portfolio->getTotalProjects();
$totalPages = ceil($totalProjects / $itemsPerPage);
?>
<?php
// Set page title
$page = "portfolio";
$pageTitle = "Portfolio Projects";
// Include header
ob_start();
?>
<div class="admin-page-header">
            <div class="admin-page-heading">
                <h1 class="admin-page-title">Portfolio Projects</h1>
                <p class="admin-page-subtitle">Manage your portfolio projects</p>
            </div>
            <div class="admin-page-actions">
                <a href="<?= FULL_BASE_PATH ?>admin/portfolio/create" class="admin-btn admin-btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>New Project
                </a>
            </div>
        </div>
<?php
$pageHeader = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>




        <?php if (isset($_SESSION['success'])): ?>
            <div class="portfolio-admin-alert portfolio-admin-alert-success" role="alert">
                <i class="bi bi-check-circle"></i>
                <span><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="portfolio-admin-alert portfolio-admin-alert-danger" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                <span><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

                <?php if (count($projects) > 0): ?>
                    <div class="portfolio-admin-table-container admin-list admin-table-scroll">
                            <table class="portfolio-admin-table admin-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Technologies</th>
                                    <th>Completion Date</th>
                                    <th>Featured</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($project['title']) ?></strong>
                                            <?php if (!empty($project['description'])): ?>
                                                <small class="portfolio-admin-text-muted"><?= htmlspecialchars(mb_substr($project['description'], 0, 120)) ?><?= mb_strlen($project['description']) > 120 ? '…' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($project['category'])): ?>
                                                <span class="portfolio-admin-badge portfolio-admin-badge-featured"><?= htmlspecialchars($project['category']) ?></span>
                                            <?php else: ?>
                                                <span class="portfolio-admin-text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $techList = array_filter(array_map('trim', explode(',', (string)($project['technologies'] ?? '')))); ?>
                                            <?php if ($techList !== []): ?>
                                                <span class="portfolio-admin-tags"><?php foreach ($techList as $tech): ?><span class="portfolio-admin-badge portfolio-admin-badge-featured"><?= htmlspecialchars($tech) ?></span><?php endforeach; ?></span>
                                            <?php else: ?>
                                                <span class="portfolio-admin-text-muted">No technologies specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="admin-nowrap">
                                            <?= isset($project['completion_date']) ? date('M d, Y', strtotime($project['completion_date'])) : 'No date' ?>
                                        </td>
                                        <td>
                                            <?php if (isset($project['featured']) && $project['featured'] == 1): ?>
                                                <span class="portfolio-admin-badge portfolio-admin-badge-featured">Featured</span>
                                            <?php else: ?>
                                                <span class="portfolio-admin-badge portfolio-admin-badge-draft">Regular</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="portfolio-admin-action-buttons">
                                                <a href="<?= FULL_BASE_PATH ?>admin/portfolio/edit?id=<?= $project['id'] ?>" class="portfolio-admin-btn portfolio-admin-btn-primary portfolio-admin-btn-sm">
                                                    <i class="bi bi-pencil"></i>Edit
                                                </a>
                                                <form method="POST" class="admin-inline-form" onsubmit="return confirm('Are you sure you want to delete this project?');" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="delete" value="<?= $project['id'] ?>">
                                                    <button type="submit" class="portfolio-admin-btn portfolio-admin-btn-danger portfolio-admin-btn-sm">
                                                        <i class="bi bi-trash"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="portfolio-admin-pagination">
                        <?php echo renderPagination($currentPage, $totalPages, FULL_BASE_PATH . 'admin/portfolio'); ?>
                    </div>

                <?php else: ?>
                    <div class="portfolio-admin-alert portfolio-admin-alert-info">
                        <i class="bi bi-info-circle"></i>
                        <span>No portfolio projects yet. <a href="<?= FULL_BASE_PATH ?>admin/portfolio/create" class="portfolio-admin-btn portfolio-admin-btn-primary portfolio-admin-btn-sm" style="margin-left: 0.5rem;">Create your first project</a></span>
                    </div>
                <?php endif; ?>

     <?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
