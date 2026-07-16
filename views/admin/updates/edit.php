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
Auth::checkLogin();

// Get update ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if update exists
$updates = new Updates();
$update = $updates->getUpdateById($id);
if (!$update) {
    header('Location: ' . FULL_BASE_PATH . 'admin/updates');
    exit;
}

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
    } elseif (isset($_POST['delete'])) {
        if ($updates->deleteUpdate($id)) {
            header('Location: ' . FULL_BASE_PATH . 'admin/updates');
            exit;
        } else {
            $error = 'Failed to delete update.';
        }
    } else {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim(strip_tags((string)($_POST['content'] ?? '')));
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
        'importance' => $importance
    ];
    
    if (!$error) {
        try {
            $result = $updates->updateUpdate($id, $data);
            if ($result) {
                $success = true;
                // Refresh update data
                $update = $updates->getUpdateById($id);
            } else {
                $error = 'Failed to update.';
            }
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Updates edit error: " . $e->getMessage());
            }
            $error = 'An error occurred while updating.';
        }
    }
    }
}
?>
<?php
// Set page title
$page = "updates-edit";
$pageTitle = "Edit Update";
// Include header
include __DIR__ . '/../includes/header.php';
?> 
<main class="admin-main">
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <p class="admin-page-subtitle">Update announcement details</p>
            </div>
            <div class="admin-page-actions">
                <a href="<?= FULL_BASE_PATH ?>admin/updates" class="admin-btn admin-btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Updates
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="admin-alert admin-alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Update saved successfully!
            </div>
        <?php endif; ?>

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
                        <input type="text" class="admin-form-control" id="title" name="title" value="<?= htmlspecialchars($update['title']) ?>" placeholder="Enter update title" required>
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="content" class="admin-form-label">Description</label>
                        <textarea class="admin-form-control" id="content" name="content" rows="6" placeholder="Enter update description or content"><?= htmlspecialchars($update['description']) ?></textarea>
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="date" class="admin-form-label">Date</label>
                        <input type="date" class="admin-form-control" id="date" name="date" value="<?= htmlspecialchars($update['update_date']) ?>">
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="category" class="admin-form-label">Category</label>
                        <input type="text" class="admin-form-control" id="category" name="category" value="<?= htmlspecialchars($update['category'] ?? '') ?>" placeholder="e.g., Work, Education, Personal">
                        <div class="admin-form-text">Optional: Categorize this update</div>
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="importance" class="admin-form-label">Importance</label>
                        <select class="admin-form-control" id="importance" name="importance">
                            <option value="low" <?= $update['importance'] === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $update['importance'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= $update['importance'] === 'high' ? 'selected' : '' ?>>High</option>
                        </select>
                        <div class="admin-form-text">Set the importance level for this update</div>
                    </div>
                    
                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update
                        </button>
                    </div>
                </form>

                <form method="POST" class="admin-form-actions" onsubmit="return confirm('Are you sure you want to delete this update?');" style="margin-top: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="delete" value="<?= $id ?>">
                    <button type="submit" class="admin-btn admin-btn-danger">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>
        

     <?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?> 
