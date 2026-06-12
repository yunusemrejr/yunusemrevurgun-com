/**
 * Admin Media Module
 * Handles image previews and media file handling
 * Requires: admin-core.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with media functionality
    $.extend(AdminPanel, {
        
        // Initialize media functionality
        initMedia: function() {
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
        }
    });

    // Initialize media when document is ready
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initMedia();
        }
    });

})(jQuery);
