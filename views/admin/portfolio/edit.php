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
require_once __DIR__ . '/../../../models/Portfolio.php';
Auth::checkLogin();

// Get project ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if project exists
$portfolio = new Portfolio();
$project = $portfolio->getProjectById($id);
if (!$project) {
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio');
    exit;
}

$success = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    // Handle delete submission first
    if (isset($_POST['delete']) && !empty($_POST['delete'])) {
        $portfolio->deleteProject((int)$_POST['delete']);
        header('Location: ' . FULL_BASE_PATH . 'admin/portfolio');
        exit;
    }

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $project_url = $_POST['project_url'] ?? '';
    $github_url = $_POST['github_url'] ?? '';
    $technologies = $_POST['technologies'] ?? '';
    $category = $_POST['category'] ?? '';
    $completion_date = $_POST['completion_date'] ?? date('Y-m-d');
    $featured = isset($_POST['featured']) ? 1 : 0;  // Convert to integer for database

    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../uploads/portfolio/';

        // Validate MIME type and extension
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $tmpPath = $_FILES['image']['tmp_name'];
        $mime = @mime_content_type($tmpPath) ?: '';
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $size = (int)($_FILES['image']['size'] ?? 0);

        if (!in_array($mime, $allowedMime, true)) {
            $error = 'Invalid image format. Allowed: JPEG, PNG, WebP, GIF.';
        } elseif (!in_array($ext, $allowedExt, true)) {
            $error = 'Invalid file extension. Allowed: .jpg, .jpeg, .png, .webp, .gif';
        } elseif ($size <= 0 || $size > 5 * 1024 * 1024) {
            $error = 'Image size must be less than 5MB.';
        } else {
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($_FILES['image']['name']));
            $upload_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_file)) {
                $image = 'uploads/portfolio/' . $filename;
            } else {
                $error = 'Failed to upload image.';
            }
        }
    }

    $data = [
        'title' => $title,
        'description' => $description,
        'project_url' => $project_url,
        'github_url' => $github_url,
        'technologies' => $technologies,
        'category' => $category,
        'completion_date' => $completion_date,
        'featured' => $featured
    ];

    if ($image) {
        $data['image'] = $image;
    }

    if (empty($error)) {
        $result = $portfolio->updateProject($id, $data);
        if ($result) {
            $success = true;
            // Refresh project data
            $project = $portfolio->getProjectById($id);
        } else {
            $error = 'Failed to update project.';
        }
    }
}
?>
<?php
// Set page title
$page = "portfolio-edit";
$pageTitle = "Edit Portfolio Project";
// Include header
include __DIR__ . '/../includes/header.php';
?>


        <div class="portfolio-admin-form-container">
            <div class="portfolio-admin-back-btn">
                <a href="<?= FULL_BASE_PATH ?>admin/portfolio" class="portfolio-admin-btn portfolio-admin-btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Projects
                </a>
            </div>

            <?php if ($success): ?>
                <div class="portfolio-admin-alert portfolio-admin-alert-success" role="alert">
                    <i class="bi bi-check-circle"></i>
                    <span>Project updated successfully!</span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="portfolio-admin-alert portfolio-admin-alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="portfolio-admin-form">
                <h2 class="admin-page-title" style="margin-bottom: 1.5rem;">Edit Portfolio Project</h2>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="portfolio-admin-form-group">
                        <label for="title" class="portfolio-admin-form-label">Title</label>
                        <input type="text" class="portfolio-admin-form-control" id="title" name="title" value="<?= htmlspecialchars($project['title']) ?>" required>
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="description" class="portfolio-admin-form-label">Description</label>
                        <textarea class="portfolio-admin-form-control" id="description" name="description" rows="5"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="technologies" class="portfolio-admin-form-label">Technologies</label>
                        <input type="text" class="portfolio-admin-form-control" id="technologies" name="technologies" value="<?= htmlspecialchars($project['technologies'] ?? '') ?>" placeholder="E.g., HTML, CSS, JavaScript, PHP, MySQL">
                        <div class="portfolio-admin-form-text">Separate multiple technologies with commas</div>
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="category" class="portfolio-admin-form-label">Category</label>
                        <input type="text" class="portfolio-admin-form-control" id="category" name="category" value="<?= htmlspecialchars($project['category'] ?? '') ?>" placeholder="E.g., AI/ML, OT DataOps, Web Dev">
                        <div class="portfolio-admin-form-text">Used for frontend filter buttons</div>
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="project_url" class="portfolio-admin-form-label">Project URL</label>
                        <input type="url" class="portfolio-admin-form-control" id="project_url" name="project_url" value="<?= htmlspecialchars($project['project_url'] ?? '') ?>" placeholder="https://example.com">
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="github_url" class="portfolio-admin-form-label">GitHub URL</label>
                        <input type="url" class="portfolio-admin-form-control" id="github_url" name="github_url" value="<?= htmlspecialchars($project['github_url'] ?? '') ?>" placeholder="https://github.com/username/repo">
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="completion_date" class="portfolio-admin-form-label">Completion Date</label>
                        <input type="date" class="portfolio-admin-form-control" id="completion_date" name="completion_date" value="<?= htmlspecialchars($project['completion_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="portfolio-admin-form-check">
                        <input type="checkbox" class="portfolio-admin-form-check-input" id="featured" name="featured" <?= (isset($project['featured']) && $project['featured'] == 1) ? 'checked' : '' ?>>
                        <label class="portfolio-admin-form-check-label" for="featured">Featured Project</label>
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="image" class="portfolio-admin-form-label">Project Image</label>
                        <?php if (!empty($project['image'])): ?>
                            <div class="portfolio-admin-image-preview">
                                <img src="<?= FULL_BASE_PATH ?><?= htmlspecialchars($project['image']) ?>" alt="Project Image" class="portfolio-image-preview">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="portfolio-admin-form-control" id="image" name="image" accept="image/*">
                        <div class="portfolio-admin-form-text">Leave empty to keep the current image.</div>
                    </div>

                    <div class="portfolio-admin-btn-group">
                        <button type="submit" class="portfolio-admin-btn portfolio-admin-btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Project
                        </button>
                    </div>
                </form>

                <form method="POST" class="portfolio-admin-btn-group" onsubmit="return confirm('Are you sure you want to delete this project?');" style="margin-top: 1rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="delete" value="<?= $id ?>">
                    <button type="submit" class="portfolio-admin-btn portfolio-admin-btn-danger">
                        <i class="bi bi-trash me-2"></i>Delete Project
                    </button>
                </form>
            </div>
        </div>



     <?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
