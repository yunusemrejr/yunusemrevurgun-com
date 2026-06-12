<?php
// Gallery editing is handled via the main gallery page.
// Redirect to the gallery index.
require_once dirname(__DIR__, 3) . '/config/setPath.php';
require_once dirname(__DIR__, 3) . '/global.php';
header('Location: ' . FULL_BASE_PATH . 'admin/gallery');
exit;
