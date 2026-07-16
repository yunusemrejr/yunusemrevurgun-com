<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

if (file_exists('../../../global.php')) {
    require_once '../../../global.php';
    restrictDirectAccess();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page = $page ?? '';
$pageTitle = $pageTitle ?? 'Dashboard';

$adminNavItems = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => FULL_BASE_PATH . 'admin/dashboard'],
    ['id' => 'blog', 'label' => 'Blog', 'href' => FULL_BASE_PATH . 'admin/blog'],
    ['id' => 'portfolio', 'label' => 'Portfolio', 'href' => FULL_BASE_PATH . 'admin/portfolio'],
    ['id' => 'gallery', 'label' => 'Gallery', 'href' => FULL_BASE_PATH . 'admin/gallery'],
    ['id' => 'updates', 'label' => 'Updates', 'href' => FULL_BASE_PATH . 'admin/updates'],
    ['id' => 'settings', 'label' => 'Settings', 'href' => FULL_BASE_PATH . 'admin/settings'],
    ['id' => 'tracker-codes', 'label' => 'Tracker', 'href' => FULL_BASE_PATH . 'admin/tracker-codes'],
    ['id' => 'music', 'label' => 'Music', 'href' => FULL_BASE_PATH . 'admin/music'],
    ['id' => 'travel', 'label' => 'Travel', 'href' => FULL_BASE_PATH . 'admin/travel'],
];

$faviconUrl = FULL_BASE_PATH . 'assets/images/favicon.svg';
$faviconMime = 'image/svg+xml';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#e3e2de">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <meta name="description" content="Admin Panel - <?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin - <?php echo htmlspecialchars($pageTitle); ?> | Yunus Emre Vurgun</title>

    <link id="appFavicon32" rel="icon" type="<?php echo htmlspecialchars($faviconMime); ?>" sizes="32x32" href="<?php echo $faviconUrl; ?>">
    <link id="appFavicon16" rel="icon" type="<?php echo htmlspecialchars($faviconMime); ?>" sizes="16x16" href="<?php echo $faviconUrl; ?>">
    <link id="appFaviconShortcut" rel="shortcut icon" href="<?php echo $faviconUrl; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $faviconUrl; ?>">
    <meta name="msapplication-TileImage" content="<?php echo $faviconUrl; ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&display=swap">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">

    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/variables.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/variables.css') ?: '1'; ?>">
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/base.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/base.css') ?: '1'; ?>">
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin.css') ?: '1'; ?>">
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-search.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-search.css') ?: '1'; ?>">
    <?php if (in_array($page, ['blog', 'blog-create', 'blog-edit'], true)): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-blog.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-blog.css') ?: '1'; ?>">
    <?php endif; ?>
    <?php if ($page === 'gallery' || $page === 'music'): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-gallery.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-gallery.css') ?: '1'; ?>">
    <?php endif; ?>
    <?php if (in_array($page, ['portfolio', 'portfolio-create', 'portfolio-edit'], true)): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-portfolio.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-portfolio.css') ?: '1'; ?>">
    <?php endif; ?>
    <?php if ($page === 'settings'): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-settings.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-settings.css') ?: '1'; ?>">
    <?php endif; ?>
    <?php if (in_array($page, ['updates', 'updates-create', 'updates-edit'], true)): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-updates.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-updates.css') ?: '1'; ?>">
    <?php endif; ?>
    <?php if (in_array($page, ['tracker-codes', 'tracker-codes-create', 'tracker-codes-edit'], true)): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-tracker-codes.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-tracker-codes.css') ?: '1'; ?>">
    <?php endif; ?>
    <?php if ($page === 'travel'): ?>
    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-travel.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-travel.css') ?: '1'; ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?php echo FULL_BASE_PATH; ?>assets/css/admin-space.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/assets/css/admin-space.css') ?: '1'; ?>">

    <script>
        window.FULL_BASE_PATH = <?php echo json_encode(FULL_BASE_PATH); ?>;
    </script>
</head>
<body class="admin-container">
    <header class="admin-ui-topbar">
        <div class="admin-ui-topbar-row">
            <div class="admin-ui-brand">
                <a href="<?php echo FULL_BASE_PATH; ?>admin/dashboard">YUNUSEMRE VURGUN</a>
                <span>ADMIN</span>
            </div>

            <button class="admin-ui-menu-toggle" type="button" data-admin-menu-open aria-label="Open admin menu">Menu</button>

            <nav class="admin-ui-nav" aria-label="Admin navigation">
                <?php foreach ($adminNavItems as $item):
                    $isActive = $page === $item['id'] || str_starts_with($page, $item['id'] . '-');
                    ?>
                    <a class="admin-ui-nav-link<?php echo $isActive ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-ui-right">
                <?php require_once __DIR__ . '/header-search.php'; ?>
                <a class="admin-ui-link" href="<?php echo FULL_BASE_PATH; ?>" target="_blank" rel="noopener noreferrer">View Site</a>
                <a class="admin-ui-link admin-ui-link-danger" href="<?php echo FULL_BASE_PATH; ?>admin/logout">Logout</a>
            </div>
        </div>
    </header>

    <div class="admin-ui-mobile-menu" id="adminUiMobileMenu" aria-hidden="true">
        <div class="admin-ui-mobile-backdrop" data-admin-menu-close></div>
        <div class="admin-ui-mobile-panel">
            <button type="button" class="admin-ui-mobile-close" data-admin-menu-close aria-label="Close">×</button>
            <nav class="admin-ui-mobile-links" aria-label="Admin mobile navigation">
                <?php foreach ($adminNavItems as $item): ?>
                    <a href="<?php echo htmlspecialchars($item['href']); ?>" data-admin-menu-close><?php echo htmlspecialchars($item['label']); ?></a>
                <?php endforeach; ?>
                <a href="<?php echo FULL_BASE_PATH; ?>" target="_blank" rel="noopener noreferrer" data-admin-menu-close>View Site</a>
                <a href="<?php echo FULL_BASE_PATH; ?>admin/logout" data-admin-menu-close>Logout</a>
            </nav>
        </div>
    </div>

    <main class="admin-main">
        <div class="admin-content">
            <div class="admin-page-header">
                <h1 class="admin-page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <?php if (isset($actionButton)): ?>
                    <div class="admin-page-actions">
                        <?php echo $actionButton; ?>
                    </div>
                <?php endif; ?>
            </div>
