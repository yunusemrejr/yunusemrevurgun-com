<?php
// Get the project root directory using __DIR__

require_once dirname(__DIR__, 3) . '/config/setPath.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) && strpos($_SERVER['REQUEST_URI'], '/admin/login') === false) {
    // Store the intended URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . FULL_BASE_PATH . 'admin/login');
    exit;
}

require_once __DIR__ . '/../../../global.php';

// Handle JavaScript verification
if (isset($_GET['js_time'])) {
    // Update JS check time and redirect back without the parameter
    $_SESSION['js_check_time'] = time();
    $redirectUrl = preg_replace('/[?&]js_time=[^&]*/', '', $_SERVER['REQUEST_URI']);
    if (substr($redirectUrl, -1) === '?') {
        $redirectUrl = rtrim($redirectUrl, '?');
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Skip JS check for POST requests or if recently verified
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_SESSION['js_check_time']) && (time() - $_SESSION['js_check_time']) <= 5)) {
    // Continue with the request
} else {
    // More lenient JavaScript detection - only redirect if explicitly disabled
    // Check for explicit JavaScript disabled indicator
    if (isset($_GET['js_disabled']) && $_GET['js_disabled'] === '1') {
        header('Location: ' .  FULL_BASE_PATH . '403.php?error=javascript_required');
        exit;
    }

    // Update JS check time
    $_SESSION['js_check_time'] = time();
}

