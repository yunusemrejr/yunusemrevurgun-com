/**
 * Admin Core Module
 * Provides core admin panel functionality including namespace, sidebar, search, and responsive features
 * This module must be loaded first as it establishes the AdminPanel namespace
 */

(function($) {
    'use strict';

    // Create AdminPanel namespace
    window.AdminPanel = {
        // Configuration
        config: {
            searchDelay: 300,
            animationDuration: 300,
            sidebarBreakpoint: 768
        },

        // Initialize core admin functionality
        init: function() {
            this.initSearch();
            this.initSearchClickOutside();
            this.initResponsive();
            this.bindGlobalEvents();
        },

        // Initialize search functionality
        initSearch: function() {
            const $searchInput = $('#adminSearchInput');
            let searchTimeout;
            let hideTimeout;

            if ($searchInput.length) {
                $searchInput.on('input', function() {
                    const query = $(this).val().trim();
                    
                    clearTimeout(searchTimeout);
                    
                    if (query.length >= 2) {
                        searchTimeout = setTimeout(function() {
                            AdminPanel.performSearch(query);
                        }, AdminPanel.config.searchDelay);
                    } else {
                        AdminPanel.clearSearchResults();
                    }
                });

                // Handle search button click
                $('#adminSearchButton').on('click', function() {
                    const query = $searchInput.val().trim();
                    if (query.length >= 2) {
                        AdminPanel.performSearch(query);
                    }
                });

                // Handle Enter key
                $searchInput.on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        const query = $(this).val().trim();
                        if (query.length >= 2) {
                            AdminPanel.performSearch(query);
                        }
                    }
                });

                // Handle keyboard navigation in search results
                $searchInput.on('keydown', function(e) {
                    const $results = $('#adminSearchResults');
                    if (!$results.hasClass('show')) return;

                    const $links = $results.find('a');
                    const $focused = $results.find('a:focus');
                    let index = $links.index($focused);

                    switch(e.which) {
                        case 38: // Up arrow
                            e.preventDefault();
                            index = index > 0 ? index - 1 : $links.length - 1;
                            $links.eq(index).focus();
                            break;
                        case 40: // Down arrow
                            e.preventDefault();
                            index = index < $links.length - 1 ? index + 1 : 0;
                            $links.eq(index).focus();
                            break;
                        case 27: // Escape
                            e.preventDefault();
                            AdminPanel.clearSearchResults();
                            $searchInput.focus();
                            break;
                    }
                });

                // Handle focus events
                $searchInput.on('focus', function() {
                    clearTimeout(hideTimeout);
                    const query = $(this).val().trim();
                    if (query.length >= 2 && !$('#adminSearchResults').hasClass('show')) {
                        AdminPanel.performSearch(query);
                    }
                });

                $searchInput.on('blur', function() {
                    // Delay hiding results to allow clicking on them
                    hideTimeout = setTimeout(function() {
                        AdminPanel.clearSearchResults();
                    }, 200);
                });
            }
        },

        // Perform search
        performSearch: function(query) {
            const $searchResults = $('#adminSearchResults');
            
            if (!$searchResults.length) {
                this.createSearchResultsContainer();
            }

            // Show loading state
            this.showSearchLoading();

            // Determine current context from URL
            const currentPath = window.location.pathname;
            let context = '';
            
            if (currentPath.includes('/admin/blog')) {
                context = 'blog';
            } else if (currentPath.includes('/admin/updates')) {
                context = 'updates';
            } else if (currentPath.includes('/admin/portfolio')) {
                context = 'portfolio';
            } else if (currentPath.includes('/admin/gallery')) {
                context = 'gallery';
            }

            // Make AJAX request
            const searchUrl = window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/admin/api/search';
            
            if (this.searchRequest) this.searchRequest.abort();
            this.searchQuery = query;
            this.searchRequest = $.ajax({
                url: searchUrl,
                method: 'GET',
                data: { 
                    q: query,
                    context: context
                },
                dataType: 'json',
                success: function(response) {
                    if (AdminPanel.searchQuery !== query) return;
                    if (response.error) {
                        AdminPanel.showSearchError(response.error);
                    } else {
                        AdminPanel.displaySearchResults(response);
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'abort') return;
                    let errorMessage = 'Search failed';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    } else if (error) {
                        errorMessage += ': ' + error;
                    }
                    AdminPanel.showSearchError(errorMessage);
                }
            });
        },

        // Create search results container
        createSearchResultsContainer: function() {
            const $searchContainer = $('<div id="adminSearchResults" class="admin-search-results"></div>');
            $('.admin-search-container').append($searchContainer);
        },

        // Use text nodes for stored titles and queries; accept only local links.
        displaySearchResults: function(response) {
            const $container = $('#adminSearchResults').empty();
            const $content = $('<div class="admin-search-results-content"></div>');
            const results = response.results || response;
            let count = 0;
            for (const [type, label] of [['blog', 'Blog posts'], ['updates', 'Updates'], ['portfolio', 'Portfolio']]) {
                if (!Array.isArray(results[type]) || !results[type].length) continue;
                const $section = $('<div class="search-section"></div>');
                $('<h4></h4>').text(label).appendTo($section);
                const $list = $('<ul></ul>').appendTo($section);
                results[type].forEach(item => {
                    let url;
                    try { url = new URL(item.url, location.origin); } catch (_) { return; }
                    if (url.origin !== location.origin) return;
                    const $row = $('<li></li>');
                    $('<a></a>').attr('href', url.href).text(item.title || 'Untitled').appendTo($row);
                    $row.appendTo($list);
                    count++;
                });
                $section.appendTo($content);
            }
            if (!count) $('<p class="admin-search-no-results"></p>').text('No results for “' + (response.query || '') + '”.').appendTo($content);
            else $('<a class="admin-btn admin-btn-secondary"></a>')
                .attr('href', (window.FULL_BASE_PATH || '/') + 'admin/search?q=' + encodeURIComponent(response.query || ''))
                .text('View all results').appendTo($content);
            $container.append($content).addClass('show');
        },

        // Show search loading state
        showSearchLoading: function() {
            const $container = $('#adminSearchResults');
            $container.html('<div class="admin-search-loading">Searching...</div>').addClass('show');
        },

        // Show search error
        showSearchError: function(message) {
            const $container = $('#adminSearchResults');
            $container.empty().append($('<div class="admin-search-error" role="status"></div>').text(message)).addClass('show');
        },

        // Clear search results
        clearSearchResults: function() {
            this.searchQuery = '';
            if (this.searchRequest) this.searchRequest.abort();
            $('#adminSearchResults').removeClass('show').empty();
        },

        // Close search results when clicking outside
        initSearchClickOutside: function() {
            $(document).on('click', function(e) {
                const $searchContainer = $('.admin-search-container');
                const $searchResults = $('#adminSearchResults');
                
                if (!$searchContainer.is(e.target) && 
                    $searchContainer.has(e.target).length === 0 &&
                    $searchResults.hasClass('show')) {
                    AdminPanel.clearSearchResults();
                }
            });

            // Prevent search results from closing when clicking on them
            $(document).on('click', '#adminSearchResults', function(e) {
                e.stopPropagation();
            });

            // Handle clicks on search result links
            $(document).on('click', '#adminSearchResults a', function(e) {
                // Allow the link to work normally
                // The results will be cleared when the page navigates
            });
        },

        // Initialize responsive functionality
        initResponsive: function() {
            // Handle responsive tables
            this.initResponsiveTables();
            
            // Handle responsive forms
            this.initResponsiveForms();
        },

        // Initialize responsive tables
        initResponsiveTables: function() {
            $('.admin-table').each(function() {
                const $table = $(this);
                if (!$table.parent().is('.admin-table-scroll, .admin-table-container, .table-responsive')) {
                    $table.wrap('<div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable content table"></div>');
                }
            });
        },

        // Initialize responsive forms
        initResponsiveForms: function() {
            // CSS owns responsive form layout so resizing stays reversible.
        },

        // Bind global events
        bindGlobalEvents: function() {
            // Handle keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + S for save
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                    e.preventDefault();
                    $('.admin-form').submit();
                }
                
                // Escape to close modals/search
                if (e.keyCode === 27) {
                    AdminPanel.clearSearchResults();
                    $('.modal').modal('hide');
                }
            });

            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                if (document.hidden && typeof AdminPanel.performAutoSave === 'function') {
                    AdminPanel.performAutoSave();
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AdminPanel.init();
    });

})(jQuery);
