/**
 * Admin Content Module
 * Handles content-specific functionality like blog post deletion and modals
 * Requires: admin-core.js, admin-notifications.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with content functionality
    $.extend(AdminPanel, {
        
        // Initialize content functionality
        initContent: function() {
            // Initialize modals
            this.initModals();
            
            // Bind content-specific events
            this.bindContentEvents();
        },

        // Initialize modals
        initModals: function() {
            // Handle delete confirmations
            $('.admin-btn-danger').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        },

        // Bind content-specific events
        bindContentEvents: function() {
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
        }
    });

    // Initialize content functionality when document is ready
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initContent();
        }
    });

})(jQuery);
