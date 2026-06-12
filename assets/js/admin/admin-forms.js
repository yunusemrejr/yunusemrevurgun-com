/**
 * Admin Forms Module
 * Handles form validation, CSRF tokens, auto-save, and slug generation
 * Requires: admin-core.js, admin-notifications.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with form functionality
    $.extend(AdminPanel, {
        
        // Auto-save timeout reference
        autoSaveTimeout: null,

        // Initialize forms
        initForms: function() {
            // CSRF token handling
            this.setupCSRFTokens();

            // Form validation
            $('.admin-form, .blog-admin-form').on('submit', function(e) {
                if (!AdminPanel.validateForm($(this))) {
                    e.preventDefault();
                }
            });

            // Auto-save functionality for content forms
            this.initAutoSave();
            this.restoreLocalDraft();
            
            // Initialize slug generation
            this.initSlugGeneration();
            
            // Handle form submission to ensure content is synced
            $('.admin-form, .blog-admin-form').on('submit', function() {
                if (typeof AdminPanel.syncEditorContent === 'function') {
                    AdminPanel.syncEditorContent();
                }
            });
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
            const $contentFields = $('.admin-form #content, .blog-admin-form #content, .admin-form textarea[name="content"], .blog-admin-form textarea[name="content"]');
            
            if ($contentFields.length) {
                let autoSaveTimeout;
                
                $contentFields.on('input', function() {
                    AdminPanel.saveLocalDraft();
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(function() {
                        AdminPanel.autoSave();
                    }, 8000);
                });
            }
        },

        // Auto-save functionality
        autoSave: function() {
            const $form = $('.admin-form, .blog-admin-form').first();
            if (!$form.length) return;
            
            const formData = new FormData($form[0]);
            formData.append('auto_save', '1');
            this.saveLocalDraft();

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

        // Schedule auto-save with debouncing
        scheduleAutoSave: function() {
            if (this.autoSaveTimeout) {
                clearTimeout(this.autoSaveTimeout);
            }
            
            this.autoSaveTimeout = setTimeout(function() {
                AdminPanel.performAutoSave();
            }, 6500);
        },

        // Perform auto-save
        performAutoSave: function() {
            const $form = $('.admin-form, .blog-admin-form').first();
            if (!$form.length) return;
            if ($form.data('autosaveActive')) return;
            
            const formData = new FormData($form[0]);
            formData.append('auto_save', '1');
            $form.data('autosaveActive', true);
            this.saveLocalDraft();
            
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
                },
                complete: function() {
                    $form.data('autosaveActive', false);
                }
            });
        },

        // Initialize slug generation
        initSlugGeneration: function() {
            const $titleField = $('#title');
            const $slugField = $('#slug');
            
            if ($titleField.length && $slugField.length) {
                $slugField.on('input', function() {
                    $(this).data('manualEdit', true);
                });

                $titleField.on('input', function() {
                    if ($slugField.data('manualEdit')) return;
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

        getDraftKey: function() {
            return 'admin-draft:' + window.location.pathname + window.location.search;
        },

        saveLocalDraft: function() {
            const $form = $('.admin-form, .blog-admin-form').first();
            if (!$form.length || !window.localStorage) return;

            const draft = {};
            $form.find('input[type="text"], input[type="date"], textarea, select').each(function() {
                if (!this.name || this.type === 'password') return;
                draft[this.name] = $(this).val();
            });

            try {
                localStorage.setItem(this.getDraftKey(), JSON.stringify({
                    savedAt: Date.now(),
                    fields: draft
                }));
            } catch (e) {}
        },

        restoreLocalDraft: function() {
            const $form = $('.admin-form, .blog-admin-form').first();
            if (!$form.length || !window.localStorage) return;

            let saved;
            try {
                saved = JSON.parse(localStorage.getItem(this.getDraftKey()) || 'null');
            } catch (e) {
                return;
            }

            if (!saved || !saved.fields || Date.now() - saved.savedAt > 24 * 60 * 60 * 1000) return;

            Object.keys(saved.fields).forEach(function(name) {
                const $field = $form.find('[name="' + name.replace(/"/g, '\\"') + '"]');
                if (!$field.length || $field.val()) return;
                $field.val(saved.fields[name]);
            });

            if (typeof AdminPanel.syncEditorContent === 'function') {
                AdminPanel.syncEditorContent();
            }
        }
    });

    // Initialize forms when document is ready
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initForms();
        }
    });

})(jQuery);
