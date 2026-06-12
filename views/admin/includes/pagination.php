<?php
require_once __DIR__ . '/../../../config/setPath.php';

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>  <?php
/**
 * Reusable pagination component for admin panel
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string HTML for pagination controls
 */
function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $queryPrefix = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    
    $html = '<nav aria-label="Page navigation" class="mt-4">
              <ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $baseUrl . $queryPrefix . 'page=' . ($currentPage - 1) . '" aria-label="Previous">
                      <span aria-hidden="true">&laquo;</span>
                    </a>
                  </li>';
    } else {
        $html .= '<li class="page-item disabled">
                    <a class="page-link" href="#" aria-label="Previous">
                      <span aria-hidden="true">&laquo;</span>
                    </a>
                  </li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    // Always show first page
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $queryPrefix . 'page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $queryPrefix . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Always show last page
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $queryPrefix . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $baseUrl . $queryPrefix . 'page=' . ($currentPage + 1) . '" aria-label="Next">
                      <span aria-hidden="true">&raquo;</span>
                    </a>
                  </li>';
    } else {
        $html .= '<li class="page-item disabled">
                    <a class="page-link" href="#" aria-label="Next">
                      <span aria-hidden="true">&raquo;</span>
                    </a>
                  </li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}
?> 