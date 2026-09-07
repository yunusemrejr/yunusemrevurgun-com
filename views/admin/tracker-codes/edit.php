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
require_once __DIR__ . '/../../../models/Tracker.php';
Auth::checkLogin();

$tracker = new Tracker();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No tracker code ID specified.";
    header("Location: " . FULL_BASE_PATH . "admin/tracker-codes");
    exit();
}

$id = $_GET['id'];
$trackerCode = $tracker->getTrackerCodeById($id);

// Check if tracker code exists
if (!$trackerCode) {
    $_SESSION['error'] = "Tracker code not found.";
    header("Location: " . FULL_BASE_PATH . "admin/tracker-codes");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'code' => $_POST['code'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    if ($tracker->updateTrackerCode($id, $data)) {
        $_SESSION['success'] = "Tracker code updated successfully.";
        header("Location: " . FULL_BASE_PATH . "admin/tracker-codes");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update tracker code.";
    }
}
?>
<?php
// Set page title
$page = "tracker-codes-edit";
$pageTitle = "Edit Tracker Code";
// Include header
ob_start();
?>
<div class="admin-page-header">
            <div>
                <p class="admin-page-subtitle">Update tracker code details</p>
            </div>
            <div class="admin-page-actions">
                <a href="<?= FULL_BASE_PATH ?>admin/tracker-codes" class="admin-btn admin-btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Tracker Codes
                </a>
            </div>
        </div>
<?php
$pageHeader = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>



        <?php if (isset($_SESSION['error'])): ?>
            <div class="admin-alert admin-alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-code-slash me-2"></i>Tracker Code Details
                </h5>
            </div>
            <div class="admin-card-body">
                <form method="post" action="" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="admin-form-group">
                        <label for="name" class="admin-form-label">Name</label>
                        <input type="text" class="admin-form-control" id="name" name="name" value="<?= htmlspecialchars($trackerCode['name']) ?>" placeholder="e.g., Google Analytics, Facebook Pixel" required>
                        <div class="admin-form-text">Enter a descriptive name for this tracker code</div>
                    </div>

                    <div class="admin-form-group">
                        <label for="code" class="admin-form-label">Tracking Code</label>
                        <textarea class="admin-form-control" id="code" name="code" rows="8" placeholder="Paste the full tracking code snippet here..." required><?= htmlspecialchars($trackerCode['code']) ?></textarea>
                        <div class="admin-form-text">Paste the complete tracking code snippet (script tags included)</div>
                    </div>

                    <div class="admin-form-group">
                        <div class="admin-form-check">
                            <input type="checkbox" class="admin-form-check-input" id="is_active" name="is_active" <?= $trackerCode['is_active'] ? 'checked' : '' ?>>
                            <label class="admin-form-check-label" for="is_active">Active</label>
                        </div>
                        <div class="admin-form-text">Uncheck to disable this tracking code without deleting it</div>
                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Tracker Code
                        </button>
                        <a href="<?= FULL_BASE_PATH ?>admin/tracker-codes" class="admin-btn admin-btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
