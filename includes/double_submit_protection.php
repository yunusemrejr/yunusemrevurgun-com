<?php
/**
 * Double Submit Protection System
 * Prevents rapid double submissions of forms
 */

class DoubleSubmitProtection {
    private static $sessionKey = 'form_submissions';
    private static $minIntervalSeconds = 5; // Minimum time between submissions
    
    /**
     * Check if form can be submitted (not too soon after last submission)
     */
    public static function canSubmit($formId = 'contact') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = [];
        }
        
        $currentTime = time();
        
        // Check if this form was submitted recently
        if (isset($_SESSION[self::$sessionKey][$formId])) {
            $lastSubmission = $_SESSION[self::$sessionKey][$formId];
            $timeDiff = $currentTime - $lastSubmission;
            
            if ($timeDiff < self::$minIntervalSeconds) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Record a form submission
     */
    public static function recordSubmission($formId = 'contact') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = [];
        }
        
        $_SESSION[self::$sessionKey][$formId] = time();
    }
    
    /**
     * Get time remaining until next submission is allowed
     */
    public static function getTimeRemaining($formId = 'contact') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$sessionKey][$formId])) {
            return 0;
        }
        
        $currentTime = time();
        $lastSubmission = $_SESSION[self::$sessionKey][$formId];
        $timeDiff = $currentTime - $lastSubmission;
        
        return max(0, self::$minIntervalSeconds - $timeDiff);
    }
    
    /**
     * Generate a unique submission ID for additional protection
     */
    public static function generateSubmissionId() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Validate submission ID (one-time use)
     */
    public static function validateSubmissionId($submissionId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['used_submission_ids'])) {
            $_SESSION['used_submission_ids'] = [];
        }
        
        // Check if ID was already used
        if (in_array($submissionId, $_SESSION['used_submission_ids'])) {
            return false;
        }
        
        // Mark as used
        $_SESSION['used_submission_ids'][] = $submissionId;
        
        // Clean up old IDs (keep only last 100)
        if (count($_SESSION['used_submission_ids']) > 100) {
            $_SESSION['used_submission_ids'] = array_slice($_SESSION['used_submission_ids'], -100);
        }
        
        return true;
    }
}
?>
