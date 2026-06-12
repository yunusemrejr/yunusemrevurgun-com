/**
 * Admin System Module
 * Handles system-level functionality like sitemap regeneration
 * Requires: admin-core.js, admin-notifications.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with system functionality
    $.extend(AdminPanel, {
        
        // Initialize system functionality
        initSystem: function() {
            this.initSitemapRegeneration();
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
                        // Sitemap regenerated successfully
                        
                        // Update CSRF token in meta tag for next request
                        if (response.new_csrf_token) {
                            $('meta[name="csrf-token"]').attr('content', response.new_csrf_token);
                        }
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
        }
    });

    // Initialize system functionality when document is ready
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initSystem();
        }
    });

})(jQuery);
