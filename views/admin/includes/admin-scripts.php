<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

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

// Determine current admin page context
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$currentPath = $_SERVER['REQUEST_URI'];

// Define module requirements for each admin page/section
$adminModules = [
    // Core modules (loaded on every admin page)
    'core' => [
        'admin-core.js',
        'admin-notifications.js'
    ],
    
    // Dashboard
    'dashboard' => [
        'admin-system.js'
    ],
    
    // Blog pages
    'blog' => [
        'admin-forms.js',
        'admin-tables.js',
        'admin-content.js'
    ],
    'blog-create' => [
        'admin-forms.js',
        'admin-editor.js',
        'admin-media.js'
    ],
    'blog-edit' => [
        'admin-forms.js',
        'admin-editor.js',
        'admin-media.js'
    ],
    
    // Portfolio pages
    'portfolio' => [
        'admin-forms.js',
        'admin-tables.js'
    ],
    'portfolio-create' => [
        'admin-forms.js',
        'admin-media.js'
    ],
    'portfolio-edit' => [
        'admin-forms.js',
        'admin-media.js'
    ],
    
    // Gallery pages
    'gallery' => [
        'admin-forms.js',
        'admin-tables.js',
        'admin-gallery.js'
    ],
    'gallery-create' => [
        'admin-forms.js',
        'admin-media.js',
        'admin-gallery.js'
    ],
    'gallery-edit' => [
        'admin-forms.js',
        'admin-media.js',
        'admin-gallery.js'
    ],
    
    // Updates pages
    'updates' => [
        'admin-forms.js',
        'admin-tables.js'
    ],
    'updates-create' => [
        'admin-forms.js',
        'admin-editor.js'
    ],
    'updates-edit' => [
        'admin-forms.js',
        'admin-editor.js'
    ],
    
    // Settings
    'settings' => [
        'admin-forms.js'
    ],
    
    // Tracker codes
    'tracker-codes' => [
        'admin-forms.js',
        'admin-tables.js'
    ],
    'tracker-codes-create' => [
        'admin-forms.js'
    ],
    'tracker-codes-edit' => [
        'admin-forms.js'
    ],
    
    // Search
    'search' => [],
    
    // Music
    'music' => [
        'admin-forms.js',
        'admin-tables.js'
    ],
];

// Function to determine current page context
function getCurrentAdminPageContext() {
    global $page;
    
    $path = $_SERVER['REQUEST_URI'];
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    
    // Use $page variable if set (preferred method)
    if (isset($page) && !empty($page)) {
        // Check for create/edit actions in path
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
    if (strpos($path, '/admin/dashboard') !== false) {
        return 'dashboard';
    } elseif (strpos($path, '/admin/blog') !== false) {
        if (strpos($path, '/create') !== false) return 'blog-create';
        if (strpos($path, '/edit') !== false) return 'blog-edit';
        return 'blog';
    } elseif (strpos($path, '/admin/portfolio') !== false) {
        if (strpos($path, '/create') !== false) return 'portfolio-create';
        if (strpos($path, '/edit') !== false) return 'portfolio-edit';
        return 'portfolio';
    } elseif (strpos($path, '/admin/gallery') !== false) {
        if (strpos($path, '/create') !== false) return 'gallery-create';
        if (strpos($path, '/edit') !== false) return 'gallery-edit';
        return 'gallery';
    } elseif (strpos($path, '/admin/updates') !== false) {
        if (strpos($path, '/create') !== false) return 'updates-create';
        if (strpos($path, '/edit') !== false) return 'updates-edit';
        return 'updates';
    } elseif (strpos($path, '/admin/settings') !== false) {
        return 'settings';
    } elseif (strpos($path, '/admin/tracker-codes') !== false) {
        if (strpos($path, '/create') !== false) return 'tracker-codes-create';
        if (strpos($path, '/edit') !== false) return 'tracker-codes-edit';
        return 'tracker-codes';
    } elseif (strpos($path, '/admin/music') !== false) {
        return 'music';
    } elseif (strpos($path, '/admin/search') !== false) {
        return 'search';
    }
    
    return 'dashboard'; // Default fallback
}

// Function to get required modules for current page
function getRequiredAdminModules() {
    global $adminModules;
    
    $pageContext = getCurrentAdminPageContext();
    $requiredModules = [];
    
    // Define admin modules if not already defined
    if (!isset($adminModules) || empty($adminModules)) {
        $adminModules = [
            // Core modules (loaded on every admin page)
            'core' => [
                'admin-core.js',
                'admin-notifications.js'
            ],
            
            // Dashboard
            'dashboard' => [
                'admin-system.js'
            ],
            
            // Blog pages
            'blog' => [
                'admin-forms.js',
                'admin-tables.js',
                'admin-content.js'
            ],
            'blog-create' => [
                'admin-forms.js',
                'admin-editor.js',
                'admin-media.js'
            ],
            'blog-edit' => [
                'admin-forms.js',
                'admin-editor.js',
                'admin-media.js'
            ],
            
            // Portfolio pages
            'portfolio' => [
                'admin-forms.js',
                'admin-tables.js'
            ],
            'portfolio-create' => [
                'admin-forms.js',
                'admin-media.js'
            ],
            'portfolio-edit' => [
                'admin-forms.js',
                'admin-media.js'
            ],
            
            // Gallery pages
            'gallery' => [
                'admin-forms.js',
                'admin-tables.js',
                'admin-gallery.js'
            ],
            'gallery-create' => [
                'admin-forms.js',
                'admin-media.js',
                'admin-gallery.js'
            ],
            'gallery-edit' => [
                'admin-forms.js',
                'admin-media.js',
                'admin-gallery.js'
            ],
            
            // Updates pages
            'updates' => [
                'admin-forms.js',
                'admin-tables.js'
            ],
            'updates-create' => [
                'admin-forms.js',
                'admin-editor.js'
            ],
            'updates-edit' => [
                'admin-forms.js',
                'admin-editor.js'
            ],
            
            // Settings
            'settings' => [
                'admin-forms.js'
            ],
            
            // Tracker codes
            'tracker-codes' => [
                'admin-forms.js',
                'admin-tables.js'
            ],
            'tracker-codes-create' => [
                'admin-forms.js'
            ],
            'tracker-codes-edit' => [
                'admin-forms.js'
            ],
            
            // Search
            'search' => [],
            
            // Music
            'music' => [
                'admin-forms.js',
                'admin-tables.js'
            ],
        ];
    }
    
    // Always load core modules
    if (isset($adminModules['core']) && is_array($adminModules['core'])) {
        $requiredModules = array_merge($requiredModules, $adminModules['core']);
    }
    
    // Add page-specific modules
    if (isset($adminModules[$pageContext]) && is_array($adminModules[$pageContext])) {
        $requiredModules = array_merge($requiredModules, $adminModules[$pageContext]);
    }
    
    // Remove duplicates and return
    return array_unique($requiredModules);
}

// Function to output script tags for required modules
function outputAdminScripts() {
    $requiredModules = getRequiredAdminModules();
    $basePath = FULL_BASE_PATH . 'assets/js/admin/';
    
    foreach ($requiredModules as $module) {
        echo '<script src="' . $basePath . $module . '?v=' . time() . '"></script>' . "\n    ";
    }
}
?>
