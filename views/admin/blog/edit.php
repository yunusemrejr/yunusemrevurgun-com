<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 3) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
} 
?>
<?php
require_once __DIR__ . '/../../../global.php';
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Blog.php';
Auth::checkLogin();

// Get post ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if post exists
$blog = new Blog();
$post = $blog->getPostById($id);
if (!$post) {
    header('Location: ' .  FULL_BASE_PATH . 'admin/blog');
    exit;
}

$success = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
    // Handle auto-save requests
    if (isset($_POST['auto_save']) && $_POST['auto_save'] === '1') {
        // For auto-save, just return a simple success response
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Auto-saved']);
        exit;
    }
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $content = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', (string)$content);
    $content = preg_replace('#<style\b[^>]*>(.*?)</style>#is', '', $content);
    $content = preg_replace('#\s*on\w+\s*=\s*["\'][^"\']*["\']#is', '', $content);
    $excerpt = $_POST['excerpt'] ?? '';
    $status = (($_POST['status'] ?? 'draft') === 'published') ? 'published' : 'draft';
    
    // Ensure we have a slug or generate one
    if (empty($_POST['slug'])) {
        $_POST['slug'] = $blog->createSlug($title);
    }
    
    // Prepare the data array
    $data = [
        'title' => $title,
        'content' => $content,
        'excerpt' => $excerpt,
        'status' => $status,
        'slug' => $_POST['slug'],
        'meta_keywords' => $_POST['meta_keywords'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? ''
    ];
    
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
            $error = 'Invalid image format. Allowed: JPEG, PNG, WebP, GIF.';
        } elseif ($size <= 0 || $size > 5 * 1024 * 1024) {
            $error = 'Image size must be less than 5MB.';
        }
        if (empty($error)) {
            $originalName = basename($_FILES['featured_image']['name']);
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $originalName);
            $filename = uniqid('', true) . '_' . $sanitizedName;
            $upload_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_file)) {
                $data['featured_image'] = 'uploads/blog/' . $filename;
                // Delete old featured image only after new upload succeeds
                if (!empty($post['featured_image'])) {
                    $old_image = __DIR__ . '/../../../' . $post['featured_image'];
                    if (file_exists($old_image)) {
                        unlink($old_image);
                    }
                }
            } else {
                $error = 'Failed to upload image.';
            }
        }
    } elseif (isset($_POST['remove_featured_image']) && $_POST['remove_featured_image'] === '1') {
        // Handle image removal
        if (!empty($post['featured_image'])) {
            $old_image = __DIR__ . '/../../../' . $post['featured_image'];
            if (file_exists($old_image)) {
                unlink($old_image);
            }
        }
        // Explicitly set featured_image to NULL in the data array
        $data['featured_image'] = null;
    }
    
    if (empty($error)) {
        try {
            $result = $blog->updatePost($id, $data);
            if ($result) {
                $success = true;
                // Refresh post data
                $post = $blog->getPostById($id);
            } else {
                $error = 'Failed to update post.';
            }
        } catch (Exception $e) {
            if (getenv('MODE') === 'development') {
                error_log("Blog edit error: " . $e->getMessage());
            }
            $error = 'An error occurred while updating the post.';
        }
    }
    }
}
?>
<?php
// Set page title
$page = "blog-edit";
$pageTitle = "Edit Blog Post";
// Include header
include __DIR__ . '/../includes/header.php';
?>
    <div class="blog-admin-form-container">
        <?php if ($success): ?>
            <div class="blog-admin-alert blog-admin-alert-success">
                Post updated successfully!
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="blog-admin-alert blog-admin-alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= FULL_BASE_PATH ?>admin/blog/edit?id=<?= $id ?>" enctype="multipart/form-data" class="blog-admin-form admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="blog-admin-form-group">
                <label for="title" class="blog-admin-form-label">Title</label>
                <input type="text" class="blog-admin-form-control" id="title" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
            </div>

            <div class="blog-admin-form-group">
                <label for="slug" class="blog-admin-form-label">Slug (URL)</label>
                <input type="text" class="blog-admin-form-control" id="slug" name="slug" value="<?= htmlspecialchars($post['slug']) ?>">
                <div class="blog-admin-form-text">The URL-friendly version of the title. Updates automatically when title changes.</div>
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
                <div contenteditable="true" class="blog-admin-content-editor" id="content"><?= htmlspecialchars($post['content']) ?></div>
                <textarea name="content" id="hidden-content" style="display: none;" aria-hidden="true"><?= htmlspecialchars($post['content']) ?></textarea>
            </div>

            <div class="blog-admin-form-group">
                <label for="excerpt" class="blog-admin-form-label">Excerpt</label>
                <textarea class="blog-admin-form-control" id="excerpt" name="excerpt" rows="3" placeholder="A short summary of your blog post..."><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                <div class="blog-admin-form-text">Auto-filled as you write until you edit this field manually.</div>
            </div>

            <div class="blog-admin-form-group">
                <label for="featured_image" class="blog-admin-form-label">Featured Image</label>
                <?php if (!empty($post['featured_image'])): ?>
                    <div class="blog-admin-image-preview">
                        <img src="<?= FULL_BASE_PATH . htmlspecialchars($post['featured_image']) ?>" alt="Featured Image" class="featured-image-preview">
                        <div style="margin-top: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--blog-admin-text-primary);">
                                <input type="checkbox" name="remove_featured_image" value="1"> Remove current image
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" class="blog-admin-form-control" id="featured_image" name="featured_image" accept="image/*">
                <div class="blog-admin-form-text">Supported formats: JPEG, PNG, GIF, WebP. Leave empty to keep the current image.</div>
                <div id="image-preview" class="blog-admin-image-preview"></div>
            </div>

            <div class="blog-admin-form-group">
                <label for="meta_keywords" class="blog-admin-form-label">Meta Keywords</label>
                <input type="text" class="blog-admin-form-control" id="meta_keywords" name="meta_keywords" value="<?= htmlspecialchars($post['meta_keywords'] ?? '') ?>" placeholder="keyword1, keyword2, keyword3">
                <div class="blog-admin-form-text">Auto-filled from title and content until you edit this field manually.</div>
            </div>

            <div class="blog-admin-form-group">
                <label for="meta_description" class="blog-admin-form-label">Meta Description</label>
                <textarea class="blog-admin-form-control" id="meta_description" name="meta_description" rows="3" placeholder="A custom meta description for search engines..."><?= htmlspecialchars($post['meta_description'] ?? '') ?></textarea>
                <div class="blog-admin-form-text">Auto-filled as you write until you edit this field manually.</div>
            </div>

            <div class="blog-admin-form-group">
                <label for="status" class="blog-admin-form-label">Status</label>
                <select class="blog-admin-form-control" id="status" name="status" required>
                    <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                </select>
            </div>

            <div class="blog-admin-form-group">
                <label class="blog-admin-form-label">Preview</label>
                <div class="blog-admin-preview" id="preview"><?= ui_sanitize_html($post['content']) ?></div>
            </div>

            <div class="blog-admin-btn-group">
                <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Update</button>
                <a href="<?=  FULL_BASE_PATH ?>admin/blog" class="blog-admin-btn blog-admin-btn-secondary">Back to Posts</a>
            </div>
        </form>
    </div>

<?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?> 
