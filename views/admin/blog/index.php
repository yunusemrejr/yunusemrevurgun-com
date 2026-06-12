<?php
require_once __DIR__ . '/../../../config/setPath.php';

require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Blog.php';
require_once __DIR__ . '/../includes/pagination.php';
Auth::checkLogin();

$blog = new Blog();

// Handle delete action via POST with CSRF — MUST be before any output
$response = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['delete'] ?? '') === 'yes') {
    header('Content-Type: application/json');
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    } elseif (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'No post ID provided']);
    } else {
        $id = (int)$_POST['id'];
        if ($blog->deletePost($id)) {
            echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete post']);
        }
    }
    exit;
}

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get paginated posts
$posts = $blog->getPaginatedPosts($offset, $itemsPerPage);
$totalPosts = $blog->getTotalPosts();
$totalPages = ceil($totalPosts / $itemsPerPage);

// Set page context, title and action button
$page = "blog";
$pageTitle = "Blog Posts";
$actionButton = '<a href="' .  FULL_BASE_PATH . 'admin/blog/create" class="blog-admin-btn blog-admin-btn-primary"><i class="bi bi-cloud-upload"></i> Create New Post</a>';

// Include header
include __DIR__ . '/../includes/header.php';
?>

    <div class="blog-admin-table-container">
        <table class="blog-admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr data-post-id="<?= $post['id'] ?>">
                    <td>
                        <strong><?= htmlspecialchars($post['title'] ?? '') ?></strong>
                        <?php if (!empty($post['excerpt'])): ?>
                            <br><small style="color: var(--blog-admin-text-secondary); opacity: 0.8;"><?= htmlspecialchars(substr($post['excerpt'], 0, 100)) ?><?= strlen($post['excerpt']) > 100 ? '...' : '' ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($post['status'] === 'published'): ?>
                            <span class="blog-admin-badge blog-admin-badge-published">Published</span>
                        <?php else: ?>
                            <span class="blog-admin-badge blog-admin-badge-draft">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($post['created_at']) ? date('M d, Y', strtotime($post['created_at'])) : 'N/A' ?></td>
                    <td>
                        <div class="blog-admin-action-buttons">
                            <a href="<?=  FULL_BASE_PATH ?>blog/<?= $post['slug'] ?>" class="blog-admin-btn blog-admin-btn-secondary" target="_blank">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <a href="<?=  FULL_BASE_PATH ?>admin/blog/edit?id=<?= $post['id'] ?>" class="blog-admin-btn blog-admin-btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <button class="blog-admin-btn blog-admin-btn-danger delete-blog-post" data-post-id="<?= $post['id'] ?>" data-post-title="<?= htmlspecialchars($post['title']) ?>">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="blog-admin-pagination">
        <?php echo renderPagination($currentPage, $totalPages,  FULL_BASE_PATH . 'admin/blog'); ?>
    </div>

<?php
// Set page-specific scripts
$pageScripts = <<<EOT
 
EOT;

// Include footer
include __DIR__ . '/../includes/footer.php';
?>  
