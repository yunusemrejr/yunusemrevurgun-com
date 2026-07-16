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

$portfolio = new Portfolio();
$success = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_url = trim($_POST['project_url'] ?? '');
    $github_url = trim($_POST['github_url'] ?? '');
    $technologies = trim($_POST['technologies'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $completion_date = $_POST['completion_date'] ?? date('Y-m-d');
    $featured = isset($_POST['featured']) ? 1 : 0;

    // Server-side validation
    if (empty($title)) {
        $error = 'Project title is required.';
    } elseif (mb_strlen($title) > 255) {
        $error = 'Project title must not exceed 255 characters.';
    }

    // Validate URLs if provided
    if (!empty($project_url) && !filter_var($project_url, FILTER_VALIDATE_URL)) {
        $error = 'Invalid project URL format.';
    }
    if (!empty($github_url) && !filter_var($github_url, FILTER_VALIDATE_URL)) {
        $error = 'Invalid GitHub URL format.';
    }

    // Handle image upload (only if no other errors)
    $image = null;
    $imageError = false;
    if (empty($error) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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
            $imageError = true;
        } elseif (!in_array($ext, $allowedExt, true)) {
            $error = 'Invalid file extension. Allowed: .jpg, .jpeg, .png, .webp, .gif';
            $imageError = true;
        } elseif ($size <= 0 || $size > 5 * 1024 * 1024) {
            $error = 'Image size must be less than 5MB.';
            $imageError = true;
        } else {
            // Verify it's actually a valid image
            $imgInfo = @getimagesize($tmpPath);
            if ($imgInfo === false) {
                $error = 'The uploaded file is not a valid image.';
                $imageError = true;
            }
        }

        if (!$imageError) {
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = uniqid('', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($_FILES['image']['name']));
            $upload_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_file)) {
                $image = 'uploads/portfolio/' . $filename;
            } else {
                $error = 'Failed to upload image.';
                $imageError = true;
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
        'featured' => $featured,
        'image' => $image
    ];
    
    if (empty($error)) {
        try {
            $result = $portfolio->createProject($data);
            if ($result) {
                $success = true;
                // Redirect to portfolio list after successful creation
                header('Location: ' . FULL_BASE_PATH . 'admin/portfolio');
                exit;
            } else {
                $error = 'Failed to create project.';
            }
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Portfolio create error: " . $e->getMessage());
            }
            $error = 'An error occurred while creating the project.';
        }
    }
}
?>
<?php
// Set page title
$page = "portfolio-create";
$pageTitle = "Create Portfolio Project";
// Include header
include __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="admin-content">
        <div class="portfolio-admin-form-container">
            <div class="portfolio-admin-back-btn">
                <a href="<?php echo FULL_BASE_PATH; ?>admin/portfolio" class="portfolio-admin-btn portfolio-admin-btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Projects
                </a>
            </div>

            <?php if ($error): ?>
                <div class="portfolio-admin-alert portfolio-admin-alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="portfolio-admin-form">
                <h2 class="admin-page-title" style="margin-bottom: 1.5rem;">Create New Portfolio Project</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="portfolio-admin-form-group">
                        <label for="title" class="portfolio-admin-form-label">Title</label>
                        <input type="text" class="portfolio-admin-form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="portfolio-admin-form-group">
                        <label for="description" class="portfolio-admin-form-label">Description</label>
                        <textarea class="portfolio-admin-form-control" id="description" name="description" rows="5"></textarea>
                    </div>
                    
                    <div class="portfolio-admin-form-group">
                        <label for="technologies" class="portfolio-admin-form-label">Technologies</label>
                        <input type="text" class="portfolio-admin-form-control" id="technologies" name="technologies" placeholder="E.g., HTML, CSS, JavaScript, PHP, MySQL">
                        <div class="portfolio-admin-form-text">Separate multiple technologies with commas</div>
                    </div>

                    <div class="portfolio-admin-form-group">
                        <label for="category" class="portfolio-admin-form-label">Category</label>
                        <input type="text" class="portfolio-admin-form-control" id="category" name="category" placeholder="E.g., AI/ML, OT DataOps, Web Dev">
                        <div class="portfolio-admin-form-text">Used for frontend filter buttons</div>
                    </div>
                    
                    <div class="portfolio-admin-form-group">
                        <label for="project_url" class="portfolio-admin-form-label">Project URL</label>
                        <input type="url" class="portfolio-admin-form-control" id="project_url" name="project_url" placeholder="https://example.com">
                    </div>
                    
                    <div class="portfolio-admin-form-group">
                        <label for="github_url" class="portfolio-admin-form-label">GitHub URL</label>
                        <input type="url" class="portfolio-admin-form-control" id="github_url" name="github_url" placeholder="https://github.com/username/repo">
                    </div>
                    
                    <div class="portfolio-admin-form-group">
                        <label for="completion_date" class="portfolio-admin-form-label">Completion Date</label>
                        <input type="date" class="portfolio-admin-form-control" id="completion_date" name="completion_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="portfolio-admin-form-check">
                        <input type="checkbox" class="portfolio-admin-form-check-input" id="featured" name="featured">
                        <label class="portfolio-admin-form-check-label" for="featured">Featured Project</label>
                    </div>
                    
                    <div class="portfolio-admin-form-group">
                        <label for="image" class="portfolio-admin-form-label">Project Image</label>
                        <input type="file" class="portfolio-admin-form-control" id="image" name="image" accept="image/*">
                    </div>
                    
                    <div class="portfolio-admin-btn-group">
                        <button type="submit" class="portfolio-admin-btn portfolio-admin-btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Create Project
                        </button>
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
