<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 3) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>  <?php
// If global.php is not included, include it and restrict direct access
if (file_exists('../../../global.php')) {
    require_once '../../../global.php';
    restrictDirectAccess();
}

// Check if user is logged in
require_once __DIR__ . '/../../../models/Auth.php';
Auth::checkLogin();

// Include header
include __DIR__ . '/header.php';

// Include the content file
if (isset($contentFile) && file_exists($contentFile)) {
    include $contentFile;
} else {
    echo '<div class="alert alert-danger">Content file not found.</div>';
}

// Include footer
include __DIR__ . '/footer.php';
?> 