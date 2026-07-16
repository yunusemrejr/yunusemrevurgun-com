<?php
// Get the project root directory using __DIR__

require_once dirname(__DIR__, 3) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

// If global.php is not included, include it and restrict direct access
if (file_exists('../../../global.php')) {
    require_once '../../../global.php';
    restrictDirectAccess();
}  
?>
<?php
require_once __DIR__ . '/../../../global.php';
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Blog.php';
Auth::checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        http_response_code(403);
        $error = 'Invalid security token. Please refresh and try again.';
    }

    // Handle auto-save requests
    if (!isset($error) && isset($_POST['auto_save']) && $_POST['auto_save'] === '1') {
        // For auto-save, just return a simple success response
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Auto-saved']);
        exit;
    }

    if (!isset($error)) {
    try {
        $blog = new Blog();
        
        // Handle featured image upload
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../uploads/blog/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $tmpPath = $_FILES['featured_image']['tmp_name'];
            $mime = @mime_content_type($tmpPath) ?: '';
            $size = (int)($_FILES['featured_image']['size'] ?? 0);
            if (!in_array($mime, $allowedMime, true)) {
                throw new Exception('Invalid image format. Allowed: JPEG, PNG, WebP, GIF.');
            }
            if ($size <= 0 || $size > 5 * 1024 * 1024) {
                throw new Exception('Image size must be less than 5MB.');
            }
            $originalName = basename($_FILES['featured_image']['name']);
            $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
            // Extension comes from the detected MIME, never from the client filename
            $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $filename = uniqid('', true) . '_' . $sanitizedName . '.' . $extByMime[$mime];
            $upload_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_file)) {
                $_POST['featured_image'] = 'uploads/blog/' . $filename;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }
        
        // Ensure we have a valid slug
        if (empty($_POST['slug']) && !empty($_POST['title'])) {
            $_POST['slug'] = $blog->createSlug($_POST['title']);
        }

        $_POST['status'] = (($_POST['status'] ?? 'draft') === 'published') ? 'published' : 'draft';
        
        if (isset($_POST['content'])) {
            $content = (string)$_POST['content'];
            // Strip script tags and event handlers
            $content = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content);
            $content = preg_replace('#<style\b[^>]*>(.*?)</style>#is', '', $content);
            $content = preg_replace('#\s*on\w+\s*=\s*["\'][^"\']*["\']#is', '', $content);
            $_POST['content'] = $content;
        }

        if ($blog->createPost($_POST)) {
            header('Location: ' . FULL_BASE_PATH . 'admin/blog');
            exit;
        } else {
            $error = "Failed to create blog post. Please try again.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    }
}
?>
<?php
// Set page title
$page = "blog-create";
$pageTitle = "Create Blog Post";
// Include header
include __DIR__ . '/../includes/header.php';
?>

    <div class="blog-admin-form-container">
        <?php if (isset($error)): ?>
            <div class="blog-admin-alert blog-admin-alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?=  FULL_BASE_PATH ?>admin/blog/create" enctype="multipart/form-data" class="blog-admin-form admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="blog-admin-form-group">
                <label for="title" class="blog-admin-form-label">Title</label>
                <input type="text" class="blog-admin-form-control" id="title" name="title" required>
            </div>

            <div class="blog-admin-form-group">
                <label for="slug" class="blog-admin-form-label">Slug (URL)</label>
                <input type="text" class="blog-admin-form-control" id="slug" name="slug" placeholder="Auto-generated from title if left empty">
                <div class="blog-admin-form-text">The URL-friendly version of the title. Leave empty to auto-generate.</div>
            </div>

            <div class="blog-admin-form-group">
                <label class="blog-admin-form-label">Content</label>
                <div class="blog-admin-editor-toolbar">
                    <div class="btn-group">
                        <button type="button" data-command="bold" title="Bold">
                            <i class="bi bi-type-bold"></i>
                        </button>
                        <button type="button" data-command="italic" title="Italic">
                            <i class="bi bi-type-italic"></i>
                        </button>
                        <button type="button" data-command="underline" title="Underline">
                            <i class="bi bi-type-underline"></i>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" data-command="insertUnorderedList" title="Bullet List">
                            <i class="bi bi-list-ul"></i>
                        </button>
                        <button type="button" data-command="insertOrderedList" title="Numbered List">
                            <i class="bi bi-list-ol"></i>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" data-command="createLink" title="Insert Link">
                            <i class="bi bi-link"></i>
                        </button>
                        <button type="button" data-command="insertImage" title="Insert Image">
                            <i class="bi bi-image"></i>
                        </button>
                    </div>
                </div>
                <div contenteditable="true" class="blog-admin-content-editor" id="content" data-placeholder="Start writing your blog post..."></div>
                <textarea name="content" id="hidden-content" style="display: none;" aria-hidden="true"></textarea>
            </div>

            <div class="blog-admin-form-group">
                <label for="excerpt" class="blog-admin-form-label">Excerpt</label>
                <textarea class="blog-admin-form-control" id="excerpt" name="excerpt" rows="3" placeholder="A short summary of your blog post..."></textarea>
                <div class="blog-admin-form-text">Auto-filled as you write. Edit it anytime to lock your custom version.</div>
            </div>

            <div class="blog-admin-form-group">
                <label for="featured_image" class="blog-admin-form-label">Featured Image</label>
                <input type="file" class="blog-admin-form-control" id="featured_image" name="featured_image" accept="image/*">
                <div class="blog-admin-form-text">Supported formats: JPEG, PNG, GIF, WebP. Maximum size: 5MB.</div>
                <div id="image-preview" class="blog-admin-image-preview"></div>
            </div>

            <div class="blog-admin-form-group">
                <label for="meta_keywords" class="blog-admin-form-label">Meta Keywords</label>
                <input type="text" class="blog-admin-form-control" id="meta_keywords" name="meta_keywords" placeholder="keyword1, keyword2, keyword3">
                <div class="blog-admin-form-text">Auto-filled from the title and content. Edit anytime to lock your custom tags.</div>
            </div>

            <div class="blog-admin-form-group">
                <label for="meta_description" class="blog-admin-form-label">Meta Description</label>
                <textarea class="blog-admin-form-control" id="meta_description" name="meta_description" rows="3" placeholder="A custom meta description for search engines..."></textarea>
                <div class="blog-admin-form-text">Auto-filled as you write. Edit anytime to lock your custom description.</div>
            </div>

            <div class="blog-admin-form-group">
                <label for="status" class="blog-admin-form-label">Status</label>
                <select class="blog-admin-form-control" id="status" name="status" required>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>

            <div class="blog-admin-form-group">
                <label class="blog-admin-form-label">Preview</label>
                <div class="blog-admin-preview" id="preview"></div>
            </div>

            <div class="blog-admin-btn-group">
                <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Create Post</button>
                <a href="<?=  FULL_BASE_PATH ?>admin/blog" class="blog-admin-btn blog-admin-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php
// Include footer
include __DIR__ . '/../includes/footer.php';





?> 
