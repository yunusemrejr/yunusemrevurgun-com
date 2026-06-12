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
            this.initSidebar();
            this.initSearch();
            this.initSearchClickOutside();
            this.initResponsive();
            this.bindGlobalEvents();
        },

        // Initialize sidebar functionality
        initSidebar: function() {
            const $sidebar = $('.admin-sidebar');
            const $main = $('.admin-main');
            const $toggle = $('.sidebar-toggle');
            const $body = $('body');

            // Create backdrop if it doesn't exist
            if (!$('.admin-sidebar-backdrop').length) {
                $('<div class="admin-sidebar-backdrop"></div>').appendTo('body');
            }
            const $backdrop = $('.admin-sidebar-backdrop');

            // Toggle sidebar on mobile
            $toggle.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if ($(window).width() <= AdminPanel.config.sidebarBreakpoint) {
                    $sidebar.toggleClass('show');
                    $body.toggleClass('sidebar-open');
                    $backdrop.toggleClass('show');
                }
            });

            // Close sidebar when clicking backdrop
            $backdrop.on('click', function() {
                AdminPanel.closeMobileSidebar();
            });

            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(e) {
                if ($(window).width() <= AdminPanel.config.sidebarBreakpoint) {
                    if (!$sidebar.is(e.target) && 
                        $sidebar.has(e.target).length === 0 && 
                        !$toggle.is(e.target) && 
                        $toggle.has(e.target).length === 0) {
                        AdminPanel.closeMobileSidebar();
                    }
                }
            });

            // Handle window resize
            $(window).on('resize', function() {
                if ($(window).width() > AdminPanel.config.sidebarBreakpoint) {
                    AdminPanel.closeMobileSidebar();
                }
            });

            // Handle escape key
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $(window).width() <= AdminPanel.config.sidebarBreakpoint) {
                    AdminPanel.closeMobileSidebar();
                }
            });
        },

        // Close mobile sidebar
        closeMobileSidebar: function() {
            const $sidebar = $('.admin-sidebar');
            const $body = $('body');
            const $backdrop = $('.admin-sidebar-backdrop');
            
            $sidebar.removeClass('show');
            $body.removeClass('sidebar-open');
            $backdrop.removeClass('show');
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
            
            $.ajax({
                url: searchUrl,
                method: 'GET',
                data: { 
                    q: query,
                    context: context
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        AdminPanel.showSearchError(response.error);
                    } else {
                        AdminPanel.displaySearchResults(response);
                    }
                },
                error: function(xhr, status, error) {
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
            $('.admin-navbar .position-relative').append($searchContainer);
        },

        // Display search results
        displaySearchResults: function(response) {
            const $container = $('#adminSearchResults');
            
            // Handle both old and new response formats
            let results = response;
            if (response.results) {
                results = response.results;
            }
            
            let html = '<div class="admin-search-results-content">';
            let hasResults = false;

            // Show context information if available
            if (response.context) {
                html += `<div class="search-context">Searching in: <strong>${response.context.charAt(0).toUpperCase() + response.context.slice(1)}</strong></div>`;
            }

            // Blog results
            if (results.blog && results.blog.length > 0) {
                hasResults = true;
                html += '<div class="search-section"><h4><i class="bi bi-file-earmark-text me-2"></i>Blog Posts</h4><ul>';
                results.blog.forEach(function(item) {
                    const status = item.is_published ? '<span class="admin-badge admin-badge-success">Published</span>' : '<span class="admin-badge admin-badge-warning">Draft</span>';
                    html += `<li><a href="${item.url}">${item.title}</a> ${status}</li>`;
                });
                html += '</ul></div>';
            }

            // Updates results
            if (results.updates && results.updates.length > 0) {
                hasResults = true;
                html += '<div class="search-section"><h4><i class="bi bi-clock-history me-2"></i>Updates</h4><ul>';
                results.updates.forEach(function(item) {
                    const status = item.is_published ? '<span class="admin-badge admin-badge-success">Published</span>' : '<span class="admin-badge admin-badge-warning">Draft</span>';
                    html += `<li><a href="${item.url}">${item.title}</a> ${status}</li>`;
                });
                html += '</ul></div>';
            }

            // Portfolio results
            if (results.portfolio && results.portfolio.length > 0) {
                hasResults = true;
                html += '<div class="search-section"><h4><i class="bi bi-briefcase me-2"></i>Portfolio</h4><ul>';
                results.portfolio.forEach(function(item) {
                    const status = item.is_visible ? '<span class="admin-badge admin-badge-success">Visible</span>' : '<span class="admin-badge admin-badge-warning">Hidden</span>';
                    html += `<li><a href="${item.url}">${item.title}</a> ${status}</li>`;
                });
                html += '</ul></div>';
            }

            if (!hasResults) {
                html += '<div class="admin-search-no-results"><i class="bi bi-search me-2"></i>No results found for "' + response.query + '"</div>';
            }

            // Add "View All Results" link if context is set
            if (response.context && hasResults) {
                html += '<div class="search-footer"><a href="' + window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/admin/search?q=' + encodeURIComponent(response.query) + '&type=' + response.context + '" class="btn btn-sm btn-outline-primary">View All Results</a></div>';
            }

            html += '</div>';
            $container.html(html).addClass('show');
        },

        // Show search loading state
        showSearchLoading: function() {
            const $container = $('#adminSearchResults');
            $container.html('<div class="admin-search-loading">Searching...</div>').addClass('show');
        },

        // Show search error
        showSearchError: function(message) {
            const $container = $('#adminSearchResults');
            $container.html(`<div class="admin-search-error">${message}</div>`).addClass('show');
        },

        // Clear search results
        clearSearchResults: function() {
            $('#adminSearchResults').removeClass('show').empty();
        },

        // Close search results when clicking outside
        initSearchClickOutside: function() {
            $(document).on('click', function(e) {
                const $searchContainer = $('.admin-navbar .position-relative');
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
                if ($table[0].scrollWidth > $table.parent().width()) {
                    $table.wrap('<div class="table-responsive"></div>');
                }
            });
        },

        // Initialize responsive forms
        initResponsiveForms: function() {
            // Adjust form layout on mobile
            if ($(window).width() <= 768) {
                $('.admin-form .row').removeClass('row').addClass('form-mobile');
            }
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
