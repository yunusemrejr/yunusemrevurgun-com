<?php
/**
 * Admin — Create a Random Memory (RMRP).
 * Clean single-header pattern; form mirrors the Updates create page with
 * rmrp class names (markdown editor kit in assets/css/admin-rmrp.css).
 */
require_once dirname(__DIR__, 3) . '/config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

require_once dirname(__DIR__, 3) . '/global.php';
restrictDirectAccess();
verifyAdminAction();
require_once dirname(__DIR__, 3) . '/models/Auth.php';
require_once dirname(__DIR__, 3) . '/models/Rmrp.php';
Auth::checkLogin();

$rmrp = new Rmrp();
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
                $result = $rmrp->createMemory($data);
                if ($result) {
                    header('Location: ' . FULL_BASE_PATH . 'admin/rmrp');
                    exit;
                } else {
                    $error = 'Failed to create memory.';
                }
            } catch (Exception $e) {
                if (getenv('MODE') === 'development') {
                    error_log("Rmrp create error: " . $e->getMessage());
                }
                $error = 'An error occurred while creating the memory.';
            }
        }
    }
}

$page = "rmrp-create";
$pageTitle = "Create Memory";
$actionButton = '<a href="' . FULL_BASE_PATH . 'admin/rmrp" class="admin-btn admin-btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Memories</a>';
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
            <i class="bi bi-dice-5 me-2"></i>Memory Details
        </h5>
    </div>
    <div class="admin-card-body">
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="admin-form-group">
                <label for="title" class="admin-form-label">Title</label>
                <input type="text" class="admin-form-control" id="title" name="title" placeholder="Enter memory title" required>
            </div>

            <div class="admin-form-group">
                <label for="content" class="admin-form-label">Memory</label>
                <div class="rmrp-md-editor">
                    <div class="rmrp-md-toolbar" role="toolbar" aria-label="Formatting toolbar">
                        <button type="button" data-md="bold" title="Bold" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
                        <button type="button" data-md="italic" title="Italic" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
                        <button type="button" data-md="link" title="Insert link" aria-label="Insert link"><i class="bi bi-link-45deg"></i></button>
                        <span class="rmrp-md-sep" aria-hidden="true"></span>
                        <button type="button" data-md="heading" title="Heading" aria-label="Heading"><i class="bi bi-heading"></i></button>
                        <button type="button" data-md="bullet" title="Bullet list" aria-label="Bullet list"><i class="bi bi-list-ul"></i></button>
                        <button type="button" data-md="quote" title="Quote" aria-label="Quote"><i class="bi bi-quote"></i></button>
                        <span class="rmrp-md-sep" aria-hidden="true"></span>
                        <button type="button" data-md="code" title="Inline code" aria-label="Inline code"><i class="bi bi-code-slash"></i></button>
                        <button type="button" data-md="hr" title="Horizontal rule" aria-label="Horizontal rule"><i class="bi bi-hr"></i></button>
                    </div>
                    <textarea class="admin-form-control" id="content" name="content" rows="10" placeholder="Write your memory in markdown — **bold**, *italic*, [links](https://…), lists, quotes, headings."></textarea>
                    <div class="rmrp-md-preview" aria-live="polite"></div>
                </div>
                <div class="rmrp-md-help">
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
                <input type="text" class="admin-form-control" id="category" name="category" placeholder="e.g., Childhood, Travel, Work, Family">
                <div class="admin-form-text">Optional: Categorize this memory</div>
            </div>

            <div class="admin-form-group">
                <label for="importance" class="admin-form-label">Importance</label>
                <select class="admin-form-control" id="importance" name="importance">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
                <div class="admin-form-text">Set the importance level for this memory</div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="bi bi-check-circle me-2"></i>Create Memory
                </button>
                <a href="<?= FULL_BASE_PATH ?>admin/rmrp" class="admin-btn admin-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
