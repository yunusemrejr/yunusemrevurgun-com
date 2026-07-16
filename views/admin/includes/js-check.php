<?php
// Get the project root directory using __DIR__

require_once dirname(__DIR__, 3) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

// If global.php is not included, include it and restrict direct access
if (file_exists('../../../global.php')) {
    require_once '../../../global.php';
    restrictDirectAccess();
}  
?>
<?php
// This will be included at the start of every admin page
// Only show verification page if JavaScript hasn't been verified recently
if (!isset($_SESSION['js_check_time']) || (time() - $_SESSION['js_check_time']) > 5) {
    ob_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Verifying...</title>
    <noscript>
        <?php 
            $redirectUrl = htmlspecialchars( FULL_BASE_PATH) . '403.php?error=javascript_required';
        ?>
        <meta http-equiv="refresh" content="0; url='<?php echo $redirectUrl; ?>'">
    </noscript> 
</head>
<body>
    <noscript>
        <p>JavaScript is required to access this page.</p>
    </noscript>
    <p>Verifying browser compatibility...</p>
</body>
</html>
HTML;
    exit;
}
?> 