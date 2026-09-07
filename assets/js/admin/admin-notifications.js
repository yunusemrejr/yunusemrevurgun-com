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
            // Visual styling lives in .admin-notification + .admin-alert-* in
            // admin.css (the old inline styles + missing .admin-alert-error
            // rule made error toasts transparent/invisible on the light theme).
            const $notification = $('<div class="admin-notification admin-alert-' + type + '"></div>')
                .attr('role', type === 'error' ? 'alert' : 'status')
                .text(message)
                .appendTo('body')
                .css('opacity', 0)
                .animate({ opacity: 1 }, 200);

            // Auto-hide after 6 seconds (5s was too short to read on mobile)
            setTimeout(function() {
                $notification.animate({ opacity: 0 }, 300, function() {
                    $(this).remove();
                });
            }, 6000);
        },

        // Show auto-save indicator
        showAutoSaveIndicator: function(message, type = 'success') {
            const $indicator = $('<div class="admin-auto-save-indicator"></div>')
                .addClass(`admin-alert-${type}`)
                .attr('role', type === 'error' ? 'alert' : 'status')
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
