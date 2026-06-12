<?php
/**
 * Rate Limiting System
 * Prevents spam and abuse by limiting requests per IP/timeframe
 */

class RateLimiter {
    private static $storageFile = 'rate_limit_storage.json';
    private static $maxAttempts = 3; // Max attempts per window
    private static $windowMinutes = 15; // Time window in minutes
    private static $lockoutMinutes = 60; // Lockout period in minutes
    
    /**
     * Check if IP is rate limited
     */
    public static function isRateLimited($ip = null) {
        if ($ip === null) {
            $ip = self::getClientIP();
        }
        
        $data = self::loadData();
        $currentTime = time();
        
        // Check if IP is in lockout period
        if (isset($data['lockouts'][$ip])) {
            if (($currentTime - $data['lockouts'][$ip]) < (self::$lockoutMinutes * 60)) {
                return true;
            } else {
                // Remove expired lockout
                unset($data['lockouts'][$ip]);
            }
        }
        
        // Check current window attempts
        $windowStart = $currentTime - (self::$windowMinutes * 60);
        $attempts = 0;
        
        if (isset($data['attempts'][$ip])) {
            // Count attempts in current window
            foreach ($data['attempts'][$ip] as $timestamp) {
                if ($timestamp > $windowStart) {
                    $attempts++;
                }
            }
        }
        
        return $attempts >= self::$maxAttempts;
    }
    
    /**
     * Record an attempt
     */
    public static function recordAttempt($ip = null) {
        if ($ip === null) {
            $ip = self::getClientIP();
        }
        
        $data = self::loadData();
        $currentTime = time();
        
        if (!isset($data['attempts'][$ip])) {
            $data['attempts'][$ip] = [];
        }
        
        // Add current attempt
        $data['attempts'][$ip][] = $currentTime;
        
        // Clean old attempts
        $windowStart = $currentTime - (self::$windowMinutes * 60);
        $data['attempts'][$ip] = array_filter($data['attempts'][$ip], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Check if should be locked out
        if (count($data['attempts'][$ip]) >= self::$maxAttempts) {
            $data['lockouts'][$ip] = $currentTime;
        }
        
        self::saveData($data);
    }
    
    /**
     * Get remaining attempts for IP
     */
    public static function getRemainingAttempts($ip = null) {
        if ($ip === null) {
            $ip = self::getClientIP();
        }
        
        $data = self::loadData();
        $currentTime = time();
        $windowStart = $currentTime - (self::$windowMinutes * 60);
        
        if (!isset($data['attempts'][$ip])) {
            return self::$maxAttempts;
        }
        
        $attempts = 0;
        foreach ($data['attempts'][$ip] as $timestamp) {
            if ($timestamp > $windowStart) {
                $attempts++;
            }
        }
        
        return max(0, self::$maxAttempts - $attempts);
    }
    
    /**
     * Get lockout time remaining
     */
    public static function getLockoutTimeRemaining($ip = null) {
        if ($ip === null) {
            $ip = self::getClientIP();
        }
        
        $data = self::loadData();
        $currentTime = time();
        
        if (!isset($data['lockouts'][$ip])) {
            return 0;
        }
        
        $lockoutEnd = $data['lockouts'][$ip] + (self::$lockoutMinutes * 60);
        return max(0, $lockoutEnd - $currentTime);
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Load rate limit data
     */
    private static function loadData() {
        $filePath = __DIR__ . '/../' . self::$storageFile;
        
        if (!file_exists($filePath)) {
            return ['attempts' => [], 'lockouts' => []];
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        return $data ?: ['attempts' => [], 'lockouts' => []];
    }
    
    /**
     * Save rate limit data
     */
    private static function saveData($data) {
        $filePath = __DIR__ . '/../' . self::$storageFile;
        
        // Ensure directory exists
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($filePath, json_encode($data), LOCK_EX);
    }
    
    /**
     * Clean up old data
     */
    public static function cleanup() {
        $data = self::loadData();
        $currentTime = time();
        $cleanupTime = $currentTime - (24 * 60 * 60); // 24 hours ago
        
        // Clean old attempts
        foreach ($data['attempts'] as $ip => $attempts) {
            $data['attempts'][$ip] = array_filter($attempts, function($timestamp) use ($cleanupTime) {
                return $timestamp > $cleanupTime;
            });
            
            if (empty($data['attempts'][$ip])) {
                unset($data['attempts'][$ip]);
            }
        }
        
        // Clean old lockouts
        foreach ($data['lockouts'] as $ip => $lockoutTime) {
            if ($lockoutTime < $cleanupTime) {
                unset($data['lockouts'][$ip]);
            }
        }
        
        self::saveData($data);
    }
}
?>
