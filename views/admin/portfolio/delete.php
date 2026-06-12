<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

if (file_exists('../../../global.php')) {
    require_once '../../../global.php';
    restrictDirectAccess();
}

require_once __DIR__ . '/../../../global.php';
verifyAdminAction();
require_once __DIR__ . '/../../../models/Auth.php';
require_once __DIR__ . '/../../../models/Portfolio.php';
Auth::checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio?error=csrf');
    exit;
}

$portfolio = new Portfolio();
$project = $portfolio->getProjectById($id);

if (!$project) {
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio');
    exit;
}

try {
    $portfolio->deleteProject($id);
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio?success=deleted');
    exit;
} catch (Exception $e) {
    header('Location: ' . FULL_BASE_PATH . 'admin/portfolio?error=delete_failed');
    exit;
}
