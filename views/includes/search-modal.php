<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__, 2) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="searchModalLabel">Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="searchForm" action="<?php echo FULL_BASE_PATH; ?>search" method="get" onsubmit="return validateSearchForm()">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="searchQuery" name="q" 
                               placeholder="Search for blog posts, updates, portfolio items..." 
                               aria-label="Search query" 
                               maxlength="60" 
                               pattern="[A-Za-z0-9 ]+" 
                               title="Please use only letters, numbers, and spaces">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                    <div id="searchValidationFeedback" class="invalid-feedback search-validation-feedback">
                        Please enter a valid search term (2-60 characters, letters, numbers, and spaces only).
                    </div>
                </form>
             </div>
        </div>
    </div>
</div>
 
<?php
//if session not initialized, set it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If global.php is not included, include it and restrict direct access
if (file_exists('../../global.php')) {
    require_once '../../global.php';
    restrictDirectAccess();
}  
?>