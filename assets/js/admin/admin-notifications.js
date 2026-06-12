/**
 * Admin Notifications Module
 * Handles all notification and messaging functionality for the admin panel
 * Requires: admin-core.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with notification functionality
    $.extend(AdminPanel, {
        
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
        }
    });

})(jQuery);
