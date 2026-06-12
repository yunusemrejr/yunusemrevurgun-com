<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

// This file can be used for additional admin-specific inline styles if needed
// Currently, all styles are handled via external CSS files
?>
 