<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(dirname(dirname(__DIR__)));

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';

// Include required files
require_once $projectRoot . '/models/Auth.php';
require_once $projectRoot . '/models/Favicon.php';
require_once $projectRoot . '/models/Settings.php';
require_once $projectRoot . '/includes/csrf.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify admin authentication
Auth::checkLogin();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$page = 'favicon';
$pageTitle = 'Favicon Management';
$actionButton = '';

// Initialize favicon model
try {
    $faviconModel = new Favicon();
    $settingsModel = new Settings();
} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Favicon panel error: " . $e->getMessage());
    }
    $errorMessage = "Failed to initialize favicon system. Please check the error log.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $postedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($postedToken) || empty($sessionToken) || !hash_equals($sessionToken, $postedToken)) {
        $errorMessage = 'Invalid CSRF token. Please try again.';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['error'] === UPLOAD_ERR_OK) {
                    $result = $faviconModel->uploadFavicon($_FILES['favicon_file']);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                } else {
                    $errorMessage = 'Please select a valid favicon file.';
                }
                break;
                
            case 'remove':
                $result = $faviconModel->removeFavicon();
                if ($result['success']) {
                    $successMessage = $result['message'];
                } else {
                    $errorMessage = $result['message'];
                }
                break;
                
            case 'set_default':
                $result = $faviconModel->setDefaultCup();
                if ($result['success']) {
                    $successMessage = $result['message'];
                } else {
                    $errorMessage = $result['message'];
                }
                break;
        }
    }
}

// Get current favicon info
try {
    $faviconInfo = $faviconModel->getFaviconInfo();
    $currentFaviconPath = $settingsModel->getFaviconPath();
} catch (Exception $e) {
    if (getenv('MODE') === 'development') {
        error_log("Favicon info error: " . $e->getMessage());
    }
    $faviconInfo = [
        'path' => 'assets/images/favicon.png',
        'exists' => false,
        'size' => 0,
        'modified' => 0
    ];
    $currentFaviconPath = 'assets/images/favicon.png';
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Favicon Page Specific Styles - Dark Theme Matching Admin Panel */
.favicon-page {
    max-width: 800px;
    margin: 0 auto;
}

.favicon-alert {
    padding: 1rem 1.25rem;
    border-radius: var(--admin-radius-md, 4px);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-family: var(--admin-font-body, 'DM Sans', system-ui, sans-serif);
    font-size: 0.9rem;
    border: 1px solid;
}

.favicon-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.favicon-alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.25);
    color: #ef4444;
}

.favicon-current-section {
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-md, 4px);
    padding: 2rem;
    margin-bottom: 2rem;
    text-align: center;
    transition: border-color 0.2s ease;
}

.favicon-current-section:hover {
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-preview-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 96px;
    height: 96px;
    margin: 0 auto 1.5rem;
    background: var(--admin-bg-elevated, #252830);
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-md, 4px);
    padding: 1rem;
    transition: border-color 0.2s ease;
}

.favicon-preview-container:hover {
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-preview-container img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.favicon-info-text {
    font-family: var(--admin-font-body, 'DM Sans', system-ui, sans-serif);
    font-size: 0.9rem;
    color: var(--admin-text-secondary, rgba(255, 255, 255, 0.7));
    margin: 0.5rem 0;
    line-height: 1.6;
}

.favicon-info-text strong {
    color: var(--admin-text-primary, #ffffff);
    font-weight: 600;
}

.favicon-upload-section {
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-md, 4px);
    padding: 2rem;
    margin-bottom: 2rem;
    transition: border-color 0.2s ease;
}

.favicon-upload-section:hover {
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-upload-title {
    font-family: var(--admin-font-display, 'JetBrains Mono', monospace);
    font-size: 1.25rem;
    font-weight: 400;
    color: var(--admin-text-primary, #ffffff);
    margin: 0 0 1.5rem 0;
    letter-spacing: -0.01em;
}

.favicon-form-group {
    margin-bottom: 1.5rem;
}

.favicon-form-label {
    display: block;
    font-family: var(--admin-font-body, 'DM Sans', system-ui, sans-serif);
    font-weight: 400;
    font-size: 0.875rem;
    color: var(--admin-text-secondary, rgba(255, 255, 255, 0.7));
    margin-bottom: 0.5rem;
}

.favicon-file-input-wrapper {
    position: relative;
    display: block;
}

.favicon-file-input {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-sm, 0px);
    color: var(--admin-text-primary, #ffffff);
    font-family: var(--admin-font-body, 'DM Sans', system-ui, sans-serif);
    font-size: 1rem;
    font-weight: 400;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    cursor: pointer;
}

.favicon-file-input:hover {
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-file-input:focus {
    outline: none;
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

.favicon-form-help {
    font-family: var(--admin-font-body, 'DM Sans', system-ui, sans-serif);
    font-size: 0.8rem;
    color: var(--admin-text-tertiary, rgba(255, 255, 255, 0.5));
    margin-top: 0.375rem;
    line-height: 1.5;
}

.favicon-preview-box {
    display: none;
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-md, 4px);
}

.favicon-preview-box.active {
    display: block;
}

.favicon-preview-label {
    font-family: var(--admin-font-display, 'JetBrains Mono', monospace);
    font-size: 0.75rem;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--admin-text-tertiary, rgba(255, 255, 255, 0.5));
    margin-bottom: 1rem;
    display: block;
}

.favicon-preview-image {
    width: 64px;
    height: 64px;
    object-fit: contain;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-sm, 0px);
    padding: 0.5rem;
    background: var(--admin-bg-elevated, #252830);
}

.favicon-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

.favicon-btn {
    flex: 1;
    min-width: 140px;
    padding: 0.5rem 1rem;
    border: 1px solid;
    border-radius: var(--admin-radius-sm, 0px);
    font-family: var(--admin-font-display, 'JetBrains Mono', monospace);
    font-size: 0.75rem;
    font-weight: 400;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.favicon-btn-primary {
background: var(--admin-text-primary, #F6F1E8);
    color: var(--admin-bg-primary, #1B1714);

    color: var(--admin-bg-primary, #1B1714);
}

.favicon-btn-secondary {
    background: transparent;
    color: var(--admin-text-primary, #ffffff);
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-btn-secondary:hover {
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-actions-section {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-top: 2rem;
}

.favicon-action-card {
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--admin-radius-md, 4px);
    padding: 1.5rem;
    text-align: center;
    transition: border-color 0.2s ease;
}

.favicon-action-card:hover {
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-action-icon {
    font-size: 1.5rem;
    color: var(--admin-text-tertiary, rgba(255, 255, 255, 0.5));
    margin-bottom: 1rem;
}

.favicon-action-title {
    font-family: var(--admin-font-display, 'JetBrains Mono', monospace);
    font-size: 0.875rem;
    font-weight: 400;
    color: var(--admin-text-primary, #ffffff);
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.01em;
}

.favicon-action-text {
    font-family: var(--admin-font-body, 'DM Sans', system-ui, sans-serif);
    font-size: 0.875rem;
    color: var(--admin-text-secondary, rgba(255, 255, 255, 0.7));
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.favicon-btn-outline {
    background: transparent;
    color: var(--admin-text-primary, #ffffff);
    border-color: var(--admin-border-strong, rgba(255, 255, 255, 0.2));
}

.favicon-btn-outline:hover {
    background: var(--admin-bg-subtle, rgba(255, 255, 255, 0.03));
    border-color: var(--admin-text-primary, #ffffff);
    color: var(--admin-text-primary, #ffffff);
}

.favicon-btn-warning {
    background: transparent;
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}

.favicon-btn-warning:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
    color: #ef4444;
}

/* Mobile First Responsive */
@media (min-width: 640px) {
    .favicon-actions-section {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .favicon-actions {
        flex-wrap: nowrap;
    }
}

@media (min-width: 1024px) {
    .favicon-actions-section {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 768px) {
    .favicon-current-section {
        padding: 2.5rem;
    }
    
    .favicon-upload-section {
        padding: 2.5rem;
    }
    
    .favicon-preview-container {
        width: 120px;
        height: 120px;
    }
}
</style>

<div class="favicon-page">
    <?php if (isset($successMessage)): ?>
        <div class="favicon-alert favicon-alert-success">
            <i class="bi bi-check-circle"></i>
            <span><?php echo htmlspecialchars($successMessage); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="favicon-alert favicon-alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <!-- Current Favicon -->
    <div class="favicon-current-section">
        <div class="favicon-preview-container">
            <img src="<?php echo FULL_BASE_PATH . $currentFaviconPath; ?>" 
                 alt="Current Favicon" 
                 onerror="this.src='<?php echo FULL_BASE_PATH; ?>assets/images/favicon.png'">
        </div>
        <p class="favicon-info-text">
            <strong>Path:</strong> <?php echo htmlspecialchars($currentFaviconPath); ?>
        </p>
        <?php if ($faviconInfo['exists']): ?>
            <p class="favicon-info-text">
                <strong>Size:</strong> <?php echo number_format($faviconInfo['size'] / 1024, 2); ?> KB
            </p>
            <p class="favicon-info-text">
                <strong>Modified:</strong> <?php echo date('Y-m-d H:i:s', $faviconInfo['modified']); ?>
            </p>
        <?php else: ?>
            <p class="favicon-info-text" style="color: var(--color-accent-warm, #C47D42);">
                <i class="bi bi-exclamation-triangle"></i> File not found
            </p>
        <?php endif; ?>
    </div>

    <!-- Upload Form -->
    <div class="favicon-upload-section">
        <h3 class="favicon-upload-title">Upload New Favicon</h3>
        <form method="POST" enctype="multipart/form-data" id="faviconUploadForm">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="favicon-form-group">
                <label for="favicon_file" class="favicon-form-label">
                    Select File
                </label>
                <div class="favicon-file-input-wrapper">
                    <input type="file" 
                           class="favicon-file-input" 
                           id="favicon_file" 
                           name="favicon_file" 
                           accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml,image/x-icon,image/vnd.microsoft.icon"
                           required>
                </div>
                <p class="favicon-form-help">
                    Supported formats: PNG, JPG, GIF, SVG, ICO. Maximum size: 2MB. Square images work best.
                </p>
            </div>
            
            <div class="favicon-preview-box" id="faviconPreview">
                <span class="favicon-preview-label">Preview:</span>
                <img id="previewImage" src="" alt="Preview" class="favicon-preview-image">
            </div>
            
            <div class="favicon-actions">
                <button type="submit" class="favicon-btn favicon-btn-primary">
                    <i class="bi bi-upload"></i>
                    <span>Upload</span>
                </button>
                <button type="button" class="favicon-btn favicon-btn-secondary" id="clearBtn">
                    <i class="bi bi-x-circle"></i>
                    <span>Clear</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Actions -->
    <div class="favicon-actions-section">
        <div class="favicon-action-card">
            <i class="bi bi-eye favicon-action-icon"></i>
            <h4 class="favicon-action-title">View Favicon</h4>
            <p class="favicon-action-text">Open the current favicon in a new tab</p>
            <a href="<?php echo FULL_BASE_PATH . $currentFaviconPath; ?>" 
               target="_blank" 
               class="favicon-btn favicon-btn-outline">
                <i class="bi bi-eye"></i>
                <span>View</span>
            </a>
        </div>
        
        <div class="favicon-action-card">
            <i class="bi bi-cup-hot favicon-action-icon" style="color: var(--color-accent, #4A7C59);"></i>
            <h4 class="favicon-action-title">Set to Default (Cup)</h4>
            <p class="favicon-action-text">Set favicon to the default cup image</p>
            <form method="POST" style="display: inline;" id="setDefaultForm">
                <input type="hidden" name="action" value="set_default">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="favicon-btn favicon-btn-primary">
                    <i class="bi bi-cup-hot"></i>
                    <span>Set Default</span>
                </button>
            </form>
        </div>
        
        <div class="favicon-action-card">
            <i class="bi bi-arrow-clockwise favicon-action-icon" style="color: var(--color-accent-warm, #C47D42);"></i>
            <h4 class="favicon-action-title">Reset to Original</h4>
            <p class="favicon-action-text">Remove current favicon and restore original default</p>
            <form method="POST" style="display: inline;" id="resetForm">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="favicon-btn favicon-btn-warning">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Reset</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('favicon_file');
    const preview = document.getElementById('faviconPreview');
    const previewImage = document.getElementById('previewImage');
    const clearBtn = document.getElementById('clearBtn');
    const resetForm = document.getElementById('resetForm');
    
    // File input preview
    if (fileInput && preview && previewImage) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    preview.classList.add('active');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.remove('active');
            }
        });
    }
    
    // Clear button
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (fileInput) fileInput.value = '';
            if (preview) preview.classList.remove('active');
        });
    }
    
    // Set default form confirmation
    const setDefaultForm = document.getElementById('setDefaultForm');
    if (setDefaultForm) {
        setDefaultForm.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to set the favicon to the default cup image?')) {
                e.preventDefault();
            }
        });
    }
    
    // Reset form confirmation
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to reset the favicon to original default? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.favicon-alert');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                alert.remove();
            }, 300);
        });
    }, 5000);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
