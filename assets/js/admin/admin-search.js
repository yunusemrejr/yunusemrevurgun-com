/**
 * Admin Search Functionality
 * Mobile-first search implementation matching about page design
 */

(function() {
    'use strict';

    // Wait for DOM and jQuery
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is required for admin search');
        return;
    }

    $(document).ready(function() {
        const $searchInput = $('#adminSearchInput');
        const $searchButton = $('#adminSearchButton');
        const $searchResults = $('#adminSearchResults');
        let searchTimeout;

        if ($searchInput.length === 0) {
            return;
        }

        // Input handler with debounce
        $searchInput.on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(function() {
                    performSearch(query);
                }, 300);
            } else {
                hideResults();
            }
        });

        // Button click handler
        $searchButton.on('click', function() {
            const query = $searchInput.val().trim();
            if (query.length >= 2) {
                performSearch(query);
            }
        });

        // Enter key handler
        $searchInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const query = $(this).val().trim();
                if (query.length >= 2) {
                    performSearch(query);
                }
            }
        });

        // Close results on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.admin-search-container').length) {
                hideResults();
            }
        });

        // Escape key to close results
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $searchResults.hasClass('show')) {
                hideResults();
                $searchInput.blur();
            }
        });

        function performSearch(query) {
            if (!$searchResults.length) {
                return;
            }

            // Show loading state
            showLoading();

            // Build search URL
            const basePath = window.FULL_BASE_PATH || '/';
            const searchUrl = basePath + 'admin/api/search';

            // Make AJAX request
            $.ajax({
                url: searchUrl,
                method: 'GET',
                data: { q: query },
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    displayResults(response, query);
                },
                error: function(xhr, status, error) {
                    showError(error, xhr.responseText);
                }
            });
        }

        function showLoading() {
            $searchResults.html('<div class="admin-search-loading">Searching...</div>').addClass('show');
        }

        function showError(error, responseText) {
            const errorMsg = error || 'Search failed';
            const responsePreview = responseText ? responseText.substring(0, 100) : '';
            $searchResults.html(
                '<div class="admin-search-error">' +
                '<i class="fas fa-exclamation-circle me-2"></i>' +
                errorMsg +
                (responsePreview ? '<br><small>' + responsePreview + '...</small>' : '') +
                '</div>'
            ).addClass('show');
        }

        function displayResults(response, query) {
            let html = '<div class="admin-search-results-content">';
            let hasResults = false;

            if (response && response.results) {
                // Blog results
                if (response.results.blog && response.results.blog.length > 0) {
                    hasResults = true;
                    html += '<div class="search-section">';
                    html += '<h4>Blog Posts <span class="search-count">(' + response.results.blog.length + ')</span></h4>';
                    html += '<ul>';
                    response.results.blog.forEach(function(item) {
                        const isPublished = item.is_published !== false && (item.status === 'published' || item.is_published === true);
                        const statusClass = isPublished ? 'search-item-published' : 'search-item-draft';
                        const statusBadge = isPublished 
                            ? '<span class="admin-badge admin-badge-success">Published</span>' 
                            : '<span class="admin-badge admin-badge-warning">Draft</span>';
                        html += '<li class="' + statusClass + '"><a href="' + item.url + '"><span>' + escapeHtml(item.title) + '</span> ' + statusBadge + '</a></li>';
                    });
                    html += '</ul></div>';
                }

                // Updates results
                if (response.results.updates && response.results.updates.length > 0) {
                    hasResults = true;
                    html += '<div class="search-section">';
                    html += '<h4>Updates <span class="search-count">(' + response.results.updates.length + ')</span></h4>';
                    html += '<ul>';
                    response.results.updates.forEach(function(item) {
                        const isPublished = item.is_published !== false;
                        const statusClass = isPublished ? 'search-item-published' : 'search-item-draft';
                        const statusBadge = isPublished 
                            ? '<span class="admin-badge admin-badge-success">Published</span>' 
                            : '<span class="admin-badge admin-badge-warning">Draft</span>';
                        html += '<li class="' + statusClass + '"><a href="' + item.url + '"><span>' + escapeHtml(item.title) + '</span> ' + statusBadge + '</a></li>';
                    });
                    html += '</ul></div>';
                }

                // Portfolio results
                if (response.results.portfolio && response.results.portfolio.length > 0) {
                    hasResults = true;
                    html += '<div class="search-section">';
                    html += '<h4>Portfolio <span class="search-count">(' + response.results.portfolio.length + ')</span></h4>';
                    html += '<ul>';
                    response.results.portfolio.forEach(function(item) {
                        const isVisible = item.is_visible !== false;
                        const statusClass = isVisible ? 'search-item-published' : 'search-item-draft';
                        const statusBadge = isVisible 
                            ? '<span class="admin-badge admin-badge-success">Visible</span>' 
                            : '<span class="admin-badge admin-badge-warning">Hidden</span>';
                        html += '<li class="' + statusClass + '"><a href="' + item.url + '"><span>' + escapeHtml(item.title) + '</span> ' + statusBadge + '</a></li>';
                    });
                    html += '</ul></div>';
                }
            }

            if (!hasResults) {
                html += '<div class="admin-search-no-results">';
                html += '<i class="bi bi-search me-2"></i>';
                html += 'No results found for "' + escapeHtml(query) + '"';
                html += '</div>';
            }

            html += '</div>';
            $searchResults.html(html).addClass('show');
        }

        function hideResults() {
            $searchResults.removeClass('show');
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });
})();
