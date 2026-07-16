<?php
// Get the document root
$docRoot = $_SERVER['DOCUMENT_ROOT'];

// Dynamically determine the project folder
$scriptPath = $_SERVER['SCRIPT_NAME'];
$projectFolder = '';

// Extract the project folder from the script path, excluding index.php
if (preg_match('~^(/[^/]+)(?=/index\.php|$)~', $scriptPath, $matches)) {
    $projectFolder = $matches[1];
}

// If we're not in a subfolder (direct in web root), use empty string
if ($projectFolder === '/' || $projectFolder === '/index.php') {
    $projectFolder = '';
}

// Include the setPath file using an absolute path
require_once $docRoot . $projectFolder . '/config/setPath.php';



function renderPagination($currentPage, $totalPages, $urlPattern = '?page=%d') {
    if ($totalPages <= 1) return;
    ?>
    <div class="pagination-container">
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm flex-wrap justify-content-center fade-in">
            <?php if ($currentPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= sprintf($urlPattern, $currentPage - 1) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php
            $numLinks = 1;
            $start = max(1, $currentPage - $numLinks);
            $end = min($totalPages, $currentPage + $numLinks);

            if ($start > 1) {
                echo '<li class="page-item d-none d-sm-block"><a class="page-link" href="' . sprintf($urlPattern, 1) . '">1</a></li>';
                if ($start > 2) {
                    echo '<li class="page-item d-none d-sm-block disabled"><span class="page-link">...</span></li>';
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                echo '<li class="page-item ' . ($i === $currentPage ? 'active' : '') . '">';
                echo '<a class="page-link" href="' . sprintf($urlPattern, $i) . '">' . $i . '</a></li>';
            }

            if ($end < $totalPages) {
                if ($end < $totalPages - 1) {
                    echo '<li class="page-item d-none d-sm-block disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item d-none d-sm-block"><a class="page-link" href="' . sprintf($urlPattern, $totalPages) . '">' . $totalPages . '</a></li>';
            }
            ?>

            <?php if ($currentPage < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= sprintf($urlPattern, $currentPage + 1) ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    </div>
    <?php
}
?> 
