<?php
session_start();

// Function to generate a secure session token
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

// Check if the session token is valid
if (!isset($_SESSION['session_token']) || $_SESSION['session_token'] !== $_SESSION['current_token']) {
    // Invalid session token, destroy session and redirect
    session_unset();  // Unset $_SESSION variables
    session_destroy(); // Destroy session data
    header('Location: admin.php'); // Redirect to login page
    exit;
}

// If the session token is valid, destroy session and redirect
session_unset();  // Unset $_SESSION variables
session_destroy(); // Destroy session data
header('Location: admin.php'); // Redirect to login page
exit;
?>
