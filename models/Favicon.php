<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

// Include the setPath file using the project root
require_once $projectRoot . '/config/setPath.php';
 
require_once __DIR__ . '/Database.php';

class Favicon {
    private $db;
    private $uploadPath;
    private $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
    private $maxFileSize = 2 * 1024 * 1024; // 2MB

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadPath = dirname(__DIR__) . '/assets/images/';
    }

    public function uploadFavicon($file) {
        // Validate file
        if (!$this->validateFile($file)) {
            return ['success' => false, 'message' => 'Invalid file type or size'];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'favicon.' . $extension;
        $filepath = $this->uploadPath . $filename;

        // Remove old favicon files
        $this->removeOldFavicons();

        // Upload new file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Update settings with new favicon path
            $this->updateFaviconSetting('assets/images/' . $filename);
            
            return ['success' => true, 'message' => 'Favicon uploaded successfully', 'filename' => $filename];
        } else {
            return ['success' => false, 'message' => 'Failed to upload favicon'];
        }
    }

    public function getCurrentFavicon() {
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'favicon_path'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $faviconPath = $result ? $result['setting_value'] : 'assets/images/favicon-pfp.png';
        
        // Check if file exists
        $fullPath = dirname(__DIR__) . '/' . $faviconPath;
        if (file_exists($fullPath)) {
            return $faviconPath;
        }
        
        // Return default if current doesn't exist
        return 'assets/images/favicon-pfp.png';
    }

    public function removeFavicon() {
        $this->removeOldFavicons();
        
        // Reset to default
        $this->updateFaviconSetting('assets/images/favicon-pfp.png');
        
        return ['success' => true, 'message' => 'Favicon reset to default'];
    }

    public function setDefaultCup() {
        // Keep method name for admin compatibility; now sets pfp default.
        $defaultPath = 'assets/images/favicon-pfp.png';
        $fullPath = dirname(__DIR__) . '/' . $defaultPath;
        
        // Check if cup.png exists
        if (!file_exists($fullPath)) {
            return ['success' => false, 'message' => 'Default blackhole favicon not found at ' . $defaultPath];
        }
        
        // Update settings with pfp favicon path.
        $this->updateFaviconSetting($defaultPath);
        
        return ['success' => true, 'message' => 'Favicon set to default profile icon'];
    }

    private function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return false;
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            return false;
        }

        return true;
    }

    private function removeOldFavicons() {
        $oldFiles = glob($this->uploadPath . 'favicon.*');
        foreach ($oldFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function updateFaviconSetting($path) {
        // Check if setting exists
        $query = "SELECT COUNT(*) FROM settings WHERE setting_key = 'favicon_path'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            // Update existing setting
            $query = "UPDATE settings SET setting_value = :value WHERE setting_key = 'favicon_path'";
        } else {
            // Insert new setting
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES ('favicon_path', :value)";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':value', $path, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    public function getFaviconInfo() {
        $faviconPath = $this->getCurrentFavicon();
        $fullPath = dirname(__DIR__) . '/' . $faviconPath;
        
        $info = [
            'path' => $faviconPath,
            'exists' => file_exists($fullPath),
            'size' => file_exists($fullPath) ? filesize($fullPath) : 0,
            'modified' => file_exists($fullPath) ? filemtime($fullPath) : 0
        ];

        return $info;
    }
}

