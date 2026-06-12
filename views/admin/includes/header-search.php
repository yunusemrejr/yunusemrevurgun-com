<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>
<div class="admin-search-container">
    <div class="admin-search-input-group">
        <input 
            class="admin-search-input" 
            type="text" 
            id="adminSearchInput" 
            placeholder="Search..." 
            aria-label="Search" 
            aria-describedby="adminSearchButton"
        >
        <button 
            class="admin-search-button" 
            id="adminSearchButton" 
            type="button"
            aria-label="Search"
        >
            <i class="fas fa-search" aria-hidden="true"></i>
        </button>
    </div>
    <div id="adminSearchResults" class="admin-search-results"></div>
</div>
