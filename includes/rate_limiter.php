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
     * Get client IP address (Cloudflare-aware)
     */
    public static function getClientIP() {
        $peer = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Only Cloudflare peers may supply CF-Connecting-IP. Arbitrary forwarded
        // headers otherwise let a direct client choose a fresh rate-limit identity.
        // Sources: https://www.cloudflare.com/ips-v4 and /ips-v6 (2026-09-09).
        $ranges = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22',
            '141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20',
            '197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13',
            '104.24.0.0/14','172.64.0.0/13','131.0.72.0/22','2400:cb00::/32',
            '2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32',
            '2a06:98c0::/29','2c0f:f248::/32'];
        $forwarded = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if (is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP)) {
            foreach ($ranges as $range) {
                if (self::inNetwork($peer, $range)) return $forwarded;
            }
        }
        return $peer;
    }

    private static function inNetwork(string $ip, string $network): bool {
        [$address, $bits] = explode('/', $network);
        $packed = @inet_pton($ip);
        $base = inet_pton($address);
        if ($packed === false || strlen($packed) !== strlen($base)) return false;
        $whole = intdiv((int)$bits, 8);
        $rest = (int)$bits % 8;
        return substr($packed, 0, $whole) === substr($base, 0, $whole)
            && (!$rest || ((ord($packed[$whole]) ^ ord($base[$whole])) & (255 << (8 - $rest))) === 0);
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
