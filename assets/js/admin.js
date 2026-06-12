/**
 * Admin Panel JavaScript
 * Handles admin-specific functionality following JavaScript separation rules
 * Uses jQuery as permitted in the technology stack
 */

(function($) {
    'use strict';

    // Admin namespace
    window.AdminPanel = {
        // Configuration
        config: {
            searchDelay: 300,
            animationDuration: 300,
            sidebarBreakpoint: 768
        },

        // Initialize admin panel
        init: function() {
            // Sidebar removed - using minimal navbar component instead
            this.initSearch();
            this.initForms();
            this.initModals();
            this.initTables();
            this.initResponsive();
            this.initThemeToggle();
            this.initContentEditor();
            this.initSitemapRegeneration();
            this.bindEvents();
        },

        // Initialize search functionality
        initSearch: function() {
            const $searchInput = $('#adminSearchInput');
            let searchTimeout;

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

            // Make AJAX request
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/admin/api/search',
                method: 'GET',
                data: { q: query },
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
            $('.admin-navbar .d-flex .position-relative').append($searchContainer);
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

            // Blog results
            if (results.blog && results.blog.length > 0) {
                hasResults = true;
                html += '<div class="search-section"><h4>Blog Posts</h4><ul>';
                results.blog.forEach(function(item) {
                    html += `<li><a href="${item.url}">${item.title}</a></li>`;
                });
                html += '</ul></div>';
            }

            // Updates results
            if (results.updates && results.updates.length > 0) {
                hasResults = true;
                html += '<div class="search-section"><h4>Updates</h4><ul>';
                results.updates.forEach(function(item) {
                    html += `<li><a href="${item.url}">${item.title}</a></li>`;
                });
                html += '</ul></div>';
            }

            // Portfolio results
            if (results.portfolio && results.portfolio.length > 0) {
                hasResults = true;
                html += '<div class="search-section"><h4>Portfolio</h4><ul>';
                results.portfolio.forEach(function(item) {
                    html += `<li><a href="${item.url}">${item.title}</a></li>`;
                });
                html += '</ul></div>';
            }

            if (!hasResults) {
                html += '<div class="admin-search-no-results">No results found</div>';
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

        // Initialize forms
        initForms: function() {
            // CSRF token handling
            this.setupCSRFTokens();

            // Form validation
            $('.admin-form').on('submit', function(e) {
                if (!AdminPanel.validateForm($(this))) {
                    e.preventDefault();
                }
            });

            // Auto-save functionality for content forms
            this.initAutoSave();
            
            // Initialize slug generation
            this.initSlugGeneration();
        },

        // Setup CSRF tokens
        setupCSRFTokens: function() {
            const token = $('meta[name="csrf-token"]').attr('content');
            if (token) {
                $.ajaxSetup({
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-CSRF-Token', token);
                    }
                });
            }
        },

        // Validate form
        validateForm: function($form) {
            let isValid = true;
            const $requiredFields = $form.find('[required]');

            $requiredFields.each(function() {
                const $field = $(this);
                const value = $field.val().trim();

                if (!value) {
                    $field.addClass('is-invalid');
                    isValid = false;
                } else {
                    $field.removeClass('is-invalid');
                }
            });

            return isValid;
        },

        // Initialize auto-save
        initAutoSave: function() {
            const $contentFields = $('.admin-form #content');
            
            if ($contentFields.length) {
                let autoSaveTimeout;
                
                $contentFields.on('input', function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(function() {
                        AdminPanel.autoSave();
                    }, 10000); // Auto-save after 10 seconds of inactivity
                });
            }
        },

        // Auto-save functionality
        autoSave: function() {
            const $form = $('.admin-form');
            const formData = new FormData($form[0]);
            formData.append('auto_save', '1');

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    AdminPanel.showAutoSaveIndicator('Draft saved');
                },
                error: function() {
                    AdminPanel.showAutoSaveIndicator('Save failed', 'error');
                }
            });
        },

        // Show auto-save indicator
        showAutoSaveIndicator: function(message, type = 'success') {
            const $indicator = $('<div class="admin-auto-save-indicator"></div>')
                .addClass(`admin-alert-${type}`)
                .text(message)
                .appendTo('body');

            setTimeout(function() {
                $indicator.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        // Initialize modals
        initModals: function() {
            // Handle delete confirmations
            $('.admin-btn-danger').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });

            // Handle image previews
            $('input[type="file"]').on('change', function() {
                AdminPanel.previewImage(this);
            });
        },

        // Preview uploaded image
        previewImage: function(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const $preview = $(input).siblings('.image-preview');
                
                reader.onload = function(e) {
                    $preview.html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px;">`);
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        },

        // Initialize tables
        initTables: function() {
            // Add sorting functionality
            $('.admin-table th[data-sort]').on('click', function() {
                AdminPanel.sortTable($(this));
            });

            // Add row selection
            $('.admin-table input[type="checkbox"]').on('change', function() {
                AdminPanel.updateRowSelection();
            });
        },

        // Sort table
        sortTable: function($header) {
            const $table = $header.closest('table');
            const column = $header.data('sort');
            const $rows = $table.find('tbody tr').toArray();
            
            $rows.sort(function(a, b) {
                const aVal = $(a).find(`td[data-sort="${column}"]`).text();
                const bVal = $(b).find(`td[data-sort="${column}"]`).text();
                return aVal.localeCompare(bVal);
            });
            
            $table.find('tbody').empty().append($rows);
        },

        // Update row selection
        updateRowSelection: function() {
            const $selectedRows = $('.admin-table input[type="checkbox"]:checked');
            const $bulkActions = $('.bulk-actions');
            
            if ($selectedRows.length > 0) {
                $bulkActions.show();
            } else {
                $bulkActions.hide();
            }
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

        // Initialize theme toggle
        initThemeToggle: function() {
            const $themeToggle = $('.theme-toggle');
            
            if ($themeToggle.length) {
                $themeToggle.on('click', function() {
                    AdminPanel.toggleTheme();
                });
            }
        },

        // Toggle theme
        toggleTheme: function() {
            const currentTheme = $('html').attr('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            $('html').attr('data-theme', newTheme);
            localStorage.setItem('admin-theme', newTheme);
        },

        // Initialize content editor
        initContentEditor: function() {
            const $contentEditor = $('#content[contenteditable="true"]');
            const $hiddenTextarea = $('#hidden-content');
            const $preview = $('#preview');
            
            if ($contentEditor.length && $hiddenTextarea.length) {
                this.setupEditorToolbar();
                this.setupContentSync($contentEditor, $hiddenTextarea, $preview);
                this.setupEditorKeyboardShortcuts($contentEditor);
                
                // Initialize content from textarea
                if ($hiddenTextarea.val()) {
                    $contentEditor.html($hiddenTextarea.val());
                }
                
                // Sync preview initially
                if ($preview.length) {
                    $preview.html($contentEditor.html());
                }
            }
        },

        // Setup editor toolbar functionality
        setupEditorToolbar: function() {
            $('.editor-toolbar button[data-command], .blog-admin-editor-toolbar button[data-command]').on('click', function(e) {
                e.preventDefault();
                const command = $(this).data('command');
                AdminPanel.executeEditorCommand(command);
            });
        },

        // Execute editor commands
        executeEditorCommand: function(command) {
            const $contentEditor = $('#content[contenteditable="true"]');
            
            if (!$contentEditor.length) return;
            
            $contentEditor.focus();
            
            switch(command) {
                case 'createLink':
                    const url = prompt('Enter URL:');
                    if (url) {
                        document.execCommand(command, false, url);
                    }
                    break;
                case 'insertImage':
                    const imageUrl = prompt('Enter image URL:');
                    if (imageUrl) {
                        document.execCommand(command, false, imageUrl);
                    }
                    break;
                default:
                    document.execCommand(command, false, null);
            }
            
            // Trigger content sync after command
            this.syncEditorContent();
        },

        // Setup content synchronization
        setupContentSync: function($editor, $textarea, $preview) {
            let syncTimeout;
            
            // Sync on input
            $editor.on('input blur keyup paste', function() {
                clearTimeout(syncTimeout);
                syncTimeout = setTimeout(function() {
                    AdminPanel.syncEditorContent();
                }, 100);
            });
            
            // Handle paste events to clean up formatting
            $editor.on('paste', function(e) {
                setTimeout(function() {
                    AdminPanel.cleanEditorContent();
                    AdminPanel.syncEditorContent();
                }, 10);
            });
        },

        // Sync editor content to hidden textarea and preview
        syncEditorContent: function() {
            const $contentEditor = $('#content[contenteditable="true"]');
            const $hiddenTextarea = $('#hidden-content');
            const $preview = $('#preview');
            
            if ($contentEditor.length && $hiddenTextarea.length) {
                const content = $contentEditor.html();
                $hiddenTextarea.val(content);
                
                if ($preview.length) {
                    $preview.html(content);
                }
                
                // Trigger auto-save
                this.scheduleAutoSave();
            }
        },

        // Clean editor content (remove unwanted formatting from paste)
        cleanEditorContent: function() {
            const $contentEditor = $('#content[contenteditable="true"]');
            
            if (!$contentEditor.length) return;
            
            let content = $contentEditor.html();
            
            // Remove unwanted tags and attributes
            content = content.replace(/<span[^>]*>/gi, '');
            content = content.replace(/<\/span>/gi, '');
            content = content.replace(/style="[^"]*"/gi, '');
            content = content.replace(/class="[^"]*"/gi, '');
            content = content.replace(/<font[^>]*>/gi, '');
            content = content.replace(/<\/font>/gi, '');
            
            // Clean up empty paragraphs
            content = content.replace(/<p>\s*<\/p>/gi, '');
            content = content.replace(/<p>&nbsp;<\/p>/gi, '');
            
            $contentEditor.html(content);
        },

        // Setup keyboard shortcuts for editor
        setupEditorKeyboardShortcuts: function($editor) {
            $editor.on('keydown', function(e) {
                // Ctrl/Cmd + B for bold
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 66) {
                    e.preventDefault();
                    document.execCommand('bold', false, null);
                    AdminPanel.syncEditorContent();
                }
                
                // Ctrl/Cmd + I for italic
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 73) {
                    e.preventDefault();
                    document.execCommand('italic', false, null);
                    AdminPanel.syncEditorContent();
                }
                
                // Ctrl/Cmd + U for underline
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 85) {
                    e.preventDefault();
                    document.execCommand('underline', false, null);
                    AdminPanel.syncEditorContent();
                }
                
                // Enter key handling for better paragraph formatting
                if (e.keyCode === 13) {
                    // Let the browser handle it naturally, then clean up
                    setTimeout(function() {
                        AdminPanel.syncEditorContent();
                    }, 10);
                }
            });
        },

        // Schedule auto-save with debouncing
        scheduleAutoSave: function() {
            if (this.autoSaveTimeout) {
                clearTimeout(this.autoSaveTimeout);
            }
            
            this.autoSaveTimeout = setTimeout(function() {
                AdminPanel.performAutoSave();
            }, 3000); // Auto-save after 3 seconds of inactivity
        },

        // Perform auto-save
        performAutoSave: function() {
            const $form = $('.admin-form');
            if (!$form.length) return;
            
            const formData = new FormData($form[0]);
            formData.append('auto_save', '1');
            
            // Show auto-save indicator
            this.showAutoSaveIndicator('Saving...', 'info');
            
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    AdminPanel.showAutoSaveIndicator('Draft saved', 'success');
                },
                error: function() {
                    AdminPanel.showAutoSaveIndicator('Save failed', 'error');
                }
            });
        },

        // Initialize slug generation
        initSlugGeneration: function() {
            const $titleField = $('#title');
            const $slugField = $('#slug');
            
            if ($titleField.length && $slugField.length) {
                $titleField.on('input', function() {
                    const title = $(this).val();
                    const slug = AdminPanel.generateSlug(title);
                    $slugField.val(slug);
                });
            }
        },

        // Generate URL-friendly slug from title
        generateSlug: function(text) {
            return text
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '') // Remove special characters
                .replace(/[\s_-]+/g, '-') // Replace spaces and underscores with hyphens
                .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
        },

        // Initialize sitemap regeneration functionality
        initSitemapRegeneration: function() {
            const $btn = $('#regenerate-sitemap-btn');
            
            if ($btn.length > 0) {
                $btn.on('click', function() {
                    AdminPanel.regenerateSitemap();
                });
            }
        },

        // Regenerate sitemap
        regenerateSitemap: function() {
            const $btn = $('#regenerate-sitemap-btn');
            const originalText = $btn.html();
            const loadingText = '<i class="bi bi-arrow-clockwise me-1 spin"></i>Regenerating...';
            
            // Disable button and show loading state
            $btn.prop('disabled', true).html(loadingText);
            
            // Add spinning animation for the icon
            if (!$('.spin').length) {
                $('<style>.spin { animation: spin 1s linear infinite; } @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');
            }
            
            // Get CSRF token
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || 
                             $('input[name="csrf_token"]').val() || '';
            
            $.ajax({
                url: window.location.origin + '/api/admin/regenerate-sitemap.php',
                type: 'POST',
                data: {
                    csrf_token: csrfToken
                },
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Sitemap regenerated successfully!', 'success');
                        console.log('Sitemap regeneration details:', response.details);
                    } else {
                        AdminPanel.showNotification(response.message || 'Failed to regenerate sitemap', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Failed to regenerate sitemap';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (xhr.status === 401) {
                        errorMessage = 'Unauthorized. Please log in again.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'CSRF token validation failed. Please refresh the page.';
                    }
                    
                    AdminPanel.showNotification(errorMessage, 'error');
                    console.error('Sitemap regeneration error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                },
                complete: function() {
                    // Re-enable button and restore original text
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        // Bind global events
        bindEvents: function() {
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
                if (document.hidden) {
                    AdminPanel.performAutoSave();
                }
            });
            
            // Handle form submission to ensure content is synced
            $('.admin-form').on('submit', function() {
                AdminPanel.syncEditorContent();
            });
            
            // Handle blog post deletion
            $(document).on('click', '.delete-blog-post', function(e) {
                e.preventDefault();
                const postId = $(this).data('post-id');
                const postTitle = $(this).data('post-title') || 'this post';
                AdminPanel.deleteBlogPost(postId, postTitle);
            });
        },
        
        // Delete blog post with confirmation
        deleteBlogPost: function(postId, postTitle) {
            if (!postId) return;
            
            const message = `Are you sure you want to delete "${postTitle}"? This action cannot be undone.`;
            
            if (confirm(message)) {
                // Show loading state
                const $deleteBtn = $(`.delete-blog-post[data-post-id="${postId}"]`);
                const originalText = $deleteBtn.text();
                $deleteBtn.text('Deleting...').prop('disabled', true);
                
                // Make AJAX request to delete
                $.ajax({
                    url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/blog/delete',
                    method: 'POST',
                    data: {
                        id: postId,
                        csrf_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the row with animation
                            $deleteBtn.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                                AdminPanel.showNotification('Post deleted successfully', 'success');
                            });
                        } else {
                            AdminPanel.showNotification('Failed to delete post: ' + (response.message || 'Unknown error'), 'error');
                            $deleteBtn.text(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        AdminPanel.showNotification('Failed to delete post: ' + error, 'error');
                        $deleteBtn.text(originalText).prop('disabled', false);
                    }
                });
            }
        },
        
        // Show notification
        showNotification: function(message, type = 'info') {
            const $notification = $('<div class="admin-notification"></div>')
                .addClass(`admin-alert-${type}`)
                .text(message)
                .appendTo('body');
            
            // Position and show
            $notification.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: 10000,
                padding: 'var(--space-sm)',
                borderRadius: '4px',
                maxWidth: '300px',
                opacity: 0,
                transform: 'translateX(100%)'
            }).animate({
                opacity: 1,
                transform: 'translateX(0)'
            }, 300);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.animate({
                    opacity: 0,
                    transform: 'translateX(100%)'
                }, 300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AdminPanel.init();
    });

})(jQuery);
