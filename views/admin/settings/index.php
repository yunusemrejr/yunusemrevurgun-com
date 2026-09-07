<?php
require_once __DIR__ . '/../../../config/setPath.php';

require_once __DIR__ . '/../../../global.php';
restrictDirectAccess();

require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Settings.php';
Auth::checkLogin();

$settings = new Settings();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    // Update lockdown mode
    $lockdownMode = isset($_POST['lockdown_mode']) ? '1' : '0';
    $settings->updateSetting('lockdown_mode', $lockdownMode);

    // Update lockdown message
    if (isset($_POST['lockdown_message']) && !empty($_POST['lockdown_message'])) {
        $settings->updateSetting('lockdown_message', $_POST['lockdown_message']);
    }

    $success = true;
}

// Get current settings
$currentSettings = $settings->getAllSettings();

// Set page title
$page = "settings";
$pageTitle = "Settings";

// Include the layout
include __DIR__ . '/../includes/header.php';
?>


        <div class="settings-container">
            <?php if (isset($success)): ?>
                <div class="settings-alert settings-alert-success" role="alert">
                    <span class="settings-alert-message">Settings updated successfully!</span>
                    <button type="button" class="settings-alert-close" onclick="this.parentElement.style.display='none'" aria-label="Close">×</button>
                </div>
            <?php endif; ?>

            <div class="settings-card">
                <div class="settings-card-header">
                    <h2 class="settings-card-title">Site Access Settings</h2>
                </div>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="settings-form-group">
                        <div class="settings-switch-container">
                            <label class="settings-switch">
                                <input type="checkbox" id="lockdown_mode" name="lockdown_mode"
                                       <?php echo isset($currentSettings['lockdown_mode']) && $currentSettings['lockdown_mode'] === '1' ? 'checked' : ''; ?>>
                                <span class="settings-switch-slider"></span>
                            </label>
                            <div class="settings-switch-label">
                                <span class="settings-switch-label-text">Enable Lockdown Mode</span>
                                <span class="settings-switch-label-desc">When enabled, the site will display a maintenance message to all visitors except admin users.</span>
                            </div>
                        </div>
                    </div>

                    <div class="settings-form-group">
                        <label for="lockdown_message" class="settings-label">Lockdown Message</label>
                        <textarea class="settings-textarea" id="lockdown_message" name="lockdown_message" rows="4" placeholder="Enter the message to display during lockdown mode..."><?php echo htmlspecialchars($currentSettings['lockdown_message'] ?? ''); ?></textarea>
                        <span class="settings-help-text">This message will be displayed to visitors when the site is in lockdown mode.</span>
                    </div>

                    <button type="submit" class="settings-btn">Save Settings</button>
                </form>
            </div>
        </div>


<?php
// Include footer
include __DIR__ . '/../includes/footer.php';
?>
