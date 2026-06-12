<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__) . '/config/setPath.php';
 
// Check if Database class is already defined before requiring it
if (!class_exists('Database')) {
    require_once __DIR__ . '/Database.php';
}

class Tracker { 
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAllTrackerCodes() {
        $query = "SELECT * FROM tracker_codes ORDER BY name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTrackerCodeById($id) {
        $query = "SELECT * FROM tracker_codes WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function sanitizeCode($code) {
        $code = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $code);
        $code = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $code);
        $code = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $code);
        $code = preg_replace('/javascript\s*:/i', '', $code);
        $code = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $code);
        $code = preg_replace('#<object\b[^>]*>.*?</object>#is', '', $code);
        $code = preg_replace('#<embed\b[^>]*>.*?</embed>#is', '', $code);
        $code = preg_replace('#<base\b[^>]*>#i', '', $code);
        $code = preg_replace('#<meta\b[^>]*http-equiv\s*=\s*["\']?refresh["\']?[^>]*>#i', '', $code);
        return $code;
    }

    public function createTrackerCode($data) {
        requireAdminSession(false);
        $data['code'] = self::sanitizeCode($data['code']);
        $query = "INSERT INTO tracker_codes (name, code, is_active) 
                  VALUES (:name, :code, :is_active)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':code', $data['code'], PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function updateTrackerCode($id, $data) {
        requireAdminSession(false);
        $data['code'] = self::sanitizeCode($data['code']);
        $query = "UPDATE tracker_codes 
                  SET name = :name, code = :code, is_active = :is_active 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':code', $data['code'], PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function deleteTrackerCode($id) {
        requireAdminSession(false);
        $query = "DELETE FROM tracker_codes WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    public function getActiveTrackerCodes() {
        $query = "SELECT * FROM tracker_codes WHERE is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 

// If global.php is not included, include it and restrict direct access
if (file_exists('../global.php')) {
    require_once '../global.php';
    restrictDirectAccess();
}  