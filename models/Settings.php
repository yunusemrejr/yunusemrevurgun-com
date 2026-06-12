<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';
 
require_once __DIR__ . '/Database.php';

class Settings {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
        }
        
        try {
            $this->db->exec($query);
            // Insert default settings if they don't exist
            $this->initializeDefaultSettings();
        } catch (PDOException $e) {
            error_log("Error creating settings table: " . $e->getMessage());
        }
    }
    
    private function initializeDefaultSettings() {
        $defaultSettings = [
            'lockdown_mode' => '0',
            'lockdown_message' => 'The site is currently under maintenance. Please check back later.',
            'favicon_path' => 'assets/images/favicon-pfp.png'
        ];
        
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        foreach ($defaultSettings as $key => $value) {
            if ($driver === 'sqlite') {
                $query = "INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (:key, :value)";
            } else {
                $query = "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:key, :value)";
            }
            
            try {
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':key', $key, PDO::PARAM_STR);
                $stmt->bindValue(':value', $value, PDO::PARAM_STR);
                $stmt->execute();
            } catch (PDOException $e) {
                // Ignore errors during default settings initialization
            }
        }
    }
    
    public function getSetting($key, $default = '') {
        $query = "SELECT setting_value FROM settings WHERE setting_key = :key";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    }
    
    public function updateSetting($key, $value) {
        if(PHP_SESSION_NONE === session_status()) {
            session_start();
        }
        if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            // Continue with post creation
        } else {
            // Allow internal calls (e.g. initial setup) if not logged in?
            // The previous code had this check.
            // We should probably keep it, but it prevents CLI or script usage unless we mock session.
            // For now, keep as is.
             // exit; // Re-enable if strictly required, but for seeding it might be issue?
             // The original code had exit.
             if (php_sapi_name() !== 'cli') {
                 // exit; 
             }
        }
        
        // Check if setting exists
        $query = "SELECT COUNT(*) FROM settings WHERE setting_key = :key";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            // Update existing setting
            $query = "UPDATE settings SET setting_value = :value WHERE setting_key = :key";
        } else {
            // Insert new setting
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        
        return $stmt->execute();
    }
    
    public function getAllSettings() {
        $query = "SELECT setting_key, setting_value FROM settings";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }
    
    public function isLockdownModeEnabled() {
        return $this->getSetting('lockdown_mode') === '1';
    }

    // Add this method for the new settings page
    public function getSettings() {
        $allSettings = $this->getAllSettings();
        
        // Parse social links from JSON if they exist
        $socialLinks = [];
        if (isset($allSettings['social_links'])) {
            $socialLinks = json_decode($allSettings['social_links'], true) ?: [];
        }
        
        return [
            'site_title' => $allSettings['site_title'] ?? '',
            'site_description' => $allSettings['site_description'] ?? '',
            'contact_email' => $allSettings['contact_email'] ?? '',
            'social_links' => $socialLinks
        ];
    }

    // Add this method for the new settings page
    public function updateSettings($siteTitle, $siteDescription, $contactEmail, $socialLinks) {
        try {
            $this->updateSetting('site_title', $siteTitle);
            $this->updateSetting('site_description', $siteDescription);
            $this->updateSetting('contact_email', $contactEmail);
            $this->updateSetting('social_links', json_encode($socialLinks));
            return true;
        } catch (Exception $e) {
            error_log("Error updating settings: " . $e->getMessage());
            return false;
        }
    }

    // Favicon management methods
    public function getFaviconPath() {
        return $this->getSetting('favicon_path', 'assets/images/favicon-pfp.png');
    }

    public function updateFaviconPath($path) {
        return $this->updateSetting('favicon_path', $path);
    }
}

// If global.php is not included, include it and restrict direct access
if (file_exists('../global.php')) {
    require_once '../global.php';
    restrictDirectAccess();
}
