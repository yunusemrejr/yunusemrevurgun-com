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
        'importance' => $importance
    ];
    
    if (!$error) {
        try {
            $result = $updates->createUpdate($data);
            if ($result) {
                $success = true;
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
include __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="admin-content">
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
    </div>
</main>


     <?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
