<?php
/**
 * CSRF Protection System
 * Generates and validates CSRF tokens for form submissions
 */

class CSRFProtection {
    private static $tokenName = 'csrf_token';
    
    /**
     * Generate or get existing CSRF token
     */
    public static function generateToken() {
        if (empty($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$tokenName];
    }

    /**
     * Force-rotate the CSRF token (always generates a new one)
     * Use after successful form validation to prevent replay attacks
     */
    public static function rotateToken() {
        $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * Validate a CSRF token
     */
    public static function validateToken($token) {
        ensureSessionStarted();
        
        if (empty($token) || empty($_SESSION[self::$tokenName])) {
            return false;
        }
        
        return hash_equals($_SESSION[self::$tokenName], $token);
    }
    
    /**
     * Get token name for form fields
     */
    public static function getTokenName() {
        return self::$tokenName;
    }
    
    /**
     * Get hidden input field HTML
     */
    public static function getHiddenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token) . '">';
    }
}
?>
