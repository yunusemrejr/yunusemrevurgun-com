<?php
// Get the project root directory using __DIR__

require_once dirname(__DIR__, 3) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

/**
 * Admin Scripts Management
 * Determines which JavaScript modules to load based on the current admin page
 */

// Function to determine current page context
function getCurrentAdminPageContext() {
    global $page;
    
    $path = $_SERVER['REQUEST_URI'];
    
    // Use $page variable if set (preferred method)
    if (isset($page) && !empty($page)) {
        if (strpos($path, '/create') !== false) {
            return $page . '-create';
        } elseif (strpos($path, '/edit') !== false) {
            return $page . '-edit';
        } elseif (strpos($path, '/delete') !== false) {
            return $page . '-delete';
        }
        return $page;
    }
    
    // Fallback: analyze path
    if (strpos($path, '/admin/dashboard') !== false) return 'dashboard';
    if (strpos($path, '/admin/blog') !== false) {
        if (strpos($path, '/create') !== false) return 'blog-create';
        if (strpos($path, '/edit') !== false) return 'blog-edit';
        return 'blog';
    }
    if (strpos($path, '/admin/portfolio') !== false) {
        if (strpos($path, '/create') !== false) return 'portfolio-create';
        if (strpos($path, '/edit') !== false) return 'portfolio-edit';
        return 'portfolio';
    }
    if (strpos($path, '/admin/gallery') !== false) {
        if (strpos($path, '/create') !== false) return 'gallery-create';
        if (strpos($path, '/edit') !== false) return 'gallery-edit';
        return 'gallery';
    }
    if (strpos($path, '/admin/updates') !== false) {
        if (strpos($path, '/create') !== false) return 'updates-create';
        if (strpos($path, '/edit') !== false) return 'updates-edit';
        return 'updates';
    }
    if (strpos($path, '/admin/settings') !== false) return 'settings';
    if (strpos($path, '/admin/tracker-codes') !== false) {
        if (strpos($path, '/create') !== false) return 'tracker-codes-create';
        if (strpos($path, '/edit') !== false) return 'tracker-codes-edit';
        return 'tracker-codes';
    }
    if (strpos($path, '/admin/music') !== false) return 'music';
    if (strpos($path, '/admin/downloads') !== false) return 'downloads';
    if (strpos($path, '/admin/travel') !== false) return 'travel';
    if (strpos($path, '/admin/search') !== false) return 'search';
    
    return 'dashboard';
}

// Function to get required modules for current page
function getRequiredAdminModules() {
    $pageContext = getCurrentAdminPageContext();
    $requiredModules = [];
    
    $adminModules = [
        'core' => ['admin-core.js', 'admin-notifications.js'],
        'dashboard' => ['admin-system.js'],
        'blog' => ['admin-forms.js', 'admin-tables.js', 'admin-content.js'],
        'blog-create' => ['admin-forms.js', 'admin-editor.js', 'admin-media.js'],
        'blog-edit' => ['admin-forms.js', 'admin-editor.js', 'admin-media.js'],
        'portfolio' => ['admin-forms.js', 'admin-tables.js'],
        'portfolio-create' => ['admin-forms.js', 'admin-media.js'],
        'portfolio-edit' => ['admin-forms.js', 'admin-media.js'],
        'gallery' => ['admin-forms.js', 'admin-tables.js', 'admin-gallery.js'],
        'gallery-create' => ['admin-forms.js', 'admin-media.js', 'admin-gallery.js'],
        'gallery-edit' => ['admin-forms.js', 'admin-media.js', 'admin-gallery.js'],
        'updates' => ['admin-forms.js', 'admin-tables.js'],
        'updates-create' => ['admin-forms.js', 'admin-editor.js'],
        'updates-edit' => ['admin-forms.js', 'admin-editor.js'],
        'settings' => ['admin-forms.js'],
        'tracker-codes' => ['admin-forms.js', 'admin-tables.js'],
        'tracker-codes-create' => ['admin-forms.js'],
        'tracker-codes-edit' => ['admin-forms.js'],
        'search' => [],
        'music' => ['admin-forms.js', 'admin-tables.js'],
        'videos' => ['admin-forms.js', 'admin-tables.js'],
        'downloads' => ['admin-forms.js', 'admin-tables.js'],
        'travel' => ['admin-forms.js', 'admin-tables.js', 'admin-travel.js'],
    ];
    
    // Always load core modules
    if (isset($adminModules['core'])) {
        $requiredModules = $adminModules['core'];
    }
    
    // Add page-specific modules
    if (isset($adminModules[$pageContext])) {
        $requiredModules = array_merge($requiredModules, $adminModules[$pageContext]);
    }
    
    return array_unique($requiredModules);
}

// Function to output script tags for required modules
function outputAdminScripts() {
    $requiredModules = getRequiredAdminModules();
    $basePath = FULL_BASE_PATH . 'assets/js/admin/';
    $baseDir = dirname(__DIR__, 3) . '/assets/js/admin/';

    foreach ($requiredModules as $module) {
        $version = @filemtime($baseDir . $module) ?: '1';
        echo '<script src="' . $basePath . $module . '?v=' . $version . '"></script>' . "\n    ";
    }
}
?>
