/**
 * Admin Editor Module
 * Handles rich text content editor functionality
 * Requires: admin-core.js, admin-forms.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with editor functionality
    $.extend(AdminPanel, {
        
        // Initialize content editor
        initContentEditor: function() {
            const $contentEditor = $('#content[contenteditable="true"]');
            const $hiddenTextarea = $('#hidden-content');
            const $preview = $('#preview');
            
            if ($contentEditor.length && $hiddenTextarea.length) {
                this.setupEditorToolbar();
                this.setupContentSync($contentEditor, $hiddenTextarea, $preview);
                this.setupEditorKeyboardShortcuts($contentEditor);
                this.setupSmartWritingAssist($contentEditor);
                
                // Initialize content from textarea
                if ($hiddenTextarea.val()) {
                    $contentEditor.html($hiddenTextarea.val());
                }
                
                // Sync preview initially
                this.syncEditorContent();
            }

            this.setupSmartWritingAssist($('#content'));
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
                    if (url && /^(https?:\/\/|mailto:|\/)/i.test(url.trim())) {
                        document.execCommand(command, false, url);
                    }
                    break;
                case 'insertImage':
                    const imageUrl = prompt('Enter image URL:');
                    if (imageUrl && /^(https?:\/\/|\/)/i.test(imageUrl.trim())) {
                        document.execCommand(command, false, imageUrl);
                        this.normalizeEditorImages();
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

                this.updateSmartSeoFields();
                
                // Trigger auto-save
                if (typeof this.scheduleAutoSave === 'function') {
                    this.scheduleAutoSave();
                }
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
            content = content.replace(/\s(on\w+)="[^"]*"/gi, '');
            content = content.replace(/\s(on\w+)='[^']*'/gi, '');
            content = content.replace(/javascript:/gi, '');
            content = content.replace(/<font[^>]*>/gi, '');
            content = content.replace(/<\/font>/gi, '');
            
            // Clean up empty paragraphs
            content = content.replace(/<p>\s*<\/p>/gi, '');
            content = content.replace(/<p>&nbsp;<\/p>/gi, '');
            
            $contentEditor.html(content);
            this.normalizeEditorImages();
        },

        normalizeEditorImages: function() {
            const $contentEditor = $('#content[contenteditable="true"]');
            $contentEditor.find('img').each(function() {
                $(this)
                    .removeAttr('width height style onload onerror')
                    .attr('loading', 'lazy')
                    .css({
                        maxWidth: '100%',
                        height: 'auto',
                        borderRadius: '10px'
                    });
            });
        },

        setupSmartWritingAssist: function($field) {
            if (!$field || !$field.length || $field.data('smartAssistReady')) return;
            $field.data('smartAssistReady', true);

            let smartTimeout;
            $field.on('input keyup paste blur', function() {
                clearTimeout(smartTimeout);
                smartTimeout = setTimeout(function() {
                    AdminPanel.updateSmartSeoFields();
                    if (typeof AdminPanel.scheduleAutoSave === 'function') {
                        AdminPanel.scheduleAutoSave();
                    }
                }, 1200);
            });

            $('#excerpt, #meta_description, #meta_keywords').each(function() {
                if ($(this).val().trim()) {
                    $(this).data('manualEdit', true);
                }
            }).on('input', function() {
                $(this).data('manualEdit', true);
            });

            this.updateSmartSeoFields();
        },

        getPlainEditorText: function() {
            const $richEditor = $('#content[contenteditable="true"]');
            if ($richEditor.length) {
                return $richEditor.text().replace(/\s+/g, ' ').trim();
            }

            const $textarea = $('#content');
            return ($textarea.val() || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        },

        summarizeText: function(text, maxLength) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            const cut = text.slice(0, maxLength + 1);
            return cut.slice(0, Math.max(cut.lastIndexOf('.'), cut.lastIndexOf(' '))).trim().replace(/[.,;:!?-]+$/, '') + '...';
        },

        generateKeywords: function(title, text) {
            const stopWords = new Set(['about', 'after', 'again', 'also', 'and', 'because', 'been', 'before', 'being', 'between', 'from', 'have', 'into', 'just', 'more', 'over', 'that', 'the', 'their', 'there', 'this', 'through', 'with', 'your']);
            const source = (title + ' ' + text).toLowerCase().replace(/[^a-z0-9\s-]/g, ' ');
            const counts = {};

            source.split(/\s+/).forEach(function(word) {
                if (word.length < 4 || stopWords.has(word)) return;
                counts[word] = (counts[word] || 0) + 1;
            });

            return Object.keys(counts)
                .sort(function(a, b) { return counts[b] - counts[a] || a.localeCompare(b); })
                .slice(0, 8)
                .join(', ');
        },

        updateSmartSeoFields: function() {
            const text = this.getPlainEditorText();
            const title = ($('#title').val() || '').trim();
            const excerpt = this.summarizeText(text, 155);
            const metaDescription = this.summarizeText((title ? title + '. ' : '') + text, 158);
            const keywords = this.generateKeywords(title, text);

            const $excerpt = $('#excerpt');
            const $metaDescription = $('#meta_description');
            const $metaKeywords = $('#meta_keywords');

            if ($excerpt.length && !$excerpt.data('manualEdit')) {
                $excerpt.val(excerpt);
            }
            if ($metaDescription.length && !$metaDescription.data('manualEdit')) {
                $metaDescription.val(metaDescription);
            }
            if ($metaKeywords.length && !$metaKeywords.data('manualEdit')) {
                $metaKeywords.val(keywords);
            }
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
        }
    });

    // Initialize editor when document is ready
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initContentEditor();
        }
    });

})(jQuery);
