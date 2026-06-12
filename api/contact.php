<?php
// Contact Form API Endpoint
// Handles AJAX contact form submissions with spam protection

// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Contact.php';
require_once $projectRoot . '/includes/csrf.php';
require_once $projectRoot . '/includes/rate_limiter.php';
require_once $projectRoot . '/includes/double_submit_protection.php';

// Set JSON header
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Start session for protection mechanisms
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize response variables
$success = false;
$error = null;

try {
    // 1. CSRF Protection
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CSRFProtection::validateToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid security token. Please refresh the page and try again.'
        ]);
        exit;
    }
    
    // 2. Rate Limiting Protection
    if (RateLimiter::isRateLimited()) {
        $lockoutTime = RateLimiter::getLockoutTimeRemaining();
        $minutes = ceil($lockoutTime / 60);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Too many attempts. Please wait {$minutes} minute(s) before trying again."
        ]);
        exit;
    }
    
    // 3. Double Submit Protection
    if (!DoubleSubmitProtection::canSubmit('contact')) {
        $timeRemaining = DoubleSubmitProtection::getTimeRemaining('contact');
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Please wait {$timeRemaining} second(s) before submitting again."
        ]);
        exit;
    }
    
    // 4. Submission ID Protection (optional additional layer)
    $submissionId = $_POST['submission_id'] ?? '';
    if (!empty($submissionId) && !DoubleSubmitProtection::validateSubmissionId($submissionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Duplicate submission detected. Please refresh the page and try again.'
        ]);
        exit;
    }

    // 5. Honeypot trap for bots
    if (trim($_POST['website'] ?? '') !== '') {
        echo json_encode([
            'success' => true,
            'error' => null,
            'message' => 'Thank you for your message! I\'ll get back to you as soon as possible.'
        ]);
        exit;
    }

    // Validate and sanitize input data
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? '')
    ];
    
    // Basic validation
    if (empty($data['name']) || empty($data['email']) || empty($data['subject']) || empty($data['message'])) {
        $error = "All fields are required.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 80) {
        $error = "Name must be between 2 and 80 characters.";
    } elseif (mb_strlen($data['subject']) < 3 || mb_strlen($data['subject']) > 140) {
        $error = "Subject must be between 3 and 140 characters.";
    } elseif (mb_strlen($data['message']) < 10 || mb_strlen($data['message']) > 2000) {
        $error = "Message must be between 10 and 2000 characters.";
    } elseif (preg_match('/[\r\n]/', $data['email']) || preg_match('/[\r\n]/', $data['subject'])) {
        $error = "Invalid input detected.";
    } else {
        // Create Contact instance and send email
        $contact = new Contact();
        if ($contact->sendEmail($data)) {
            $success = true;
            
            // Record successful submission for protection mechanisms
            RateLimiter::recordAttempt();
            DoubleSubmitProtection::recordSubmission('contact');
        } else {
            $error = "Failed to send message. Please try again later.";
            
            // Record failed attempt for rate limiting
            RateLimiter::recordAttempt();
        }
    }
    
} catch (Exception $e) {
    error_log("Contact API Error: " . $e->getMessage());
    $error = "An error occurred while processing your message. Please try again later.";
}

// Return JSON response
echo json_encode([
    'success' => $success,
    'error' => $error,
    'message' => $success ? 'Thank you for your message! I\'ll get back to you as soon as possible.' : $error
]);
?>
