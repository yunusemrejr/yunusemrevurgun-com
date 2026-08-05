/**
 * Admin Updates Editor Module
 * Markdown toolbar + live preview for the Updates create/edit forms.
 *
 * The preview renderer is a JS mirror of models/RichText.php (same subset:
 * paragraphs, soft breaks, #/##/### headings, "-"/"*"/"1." lists, "> " quotes,
 * "---" rule, **bold**, *italic*, `code`, [label](url) links, bare URL
 * auto-link). Keep the two implementations in sync when changing syntax.
 *
 * Requires: jQuery (loaded in admin footer), admin-forms.js (autosave).
 */
(function($) {
    'use strict';

    $.extend(AdminPanel, {

        // Escape HTML the same way PHP htmlspecialchars(ENT_QUOTES) does.
        mdEscapeHtml: function(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        mdSanitizeLink: function(url) {
            url = String(url || '').trim();
            if (!url) return '';
            if (/^(https?|mailto):/i.test(url)) return url;
            // Same-site relative path; reject protocol-relative "//host".
            if (/^\/[^\/]/.test(url)) return url;
            return '';
        },

        // Inline transforms (code spans, links, bare URLs, emphasis, soft break).
        mdInline: function(text) {
            var tokens = {};
            var i = 0;
            var self = this;

            text = text.replace(/`([^`\n]+)`/g, function(_, content) {
                var key = '\u001A' + (i++) + '\u001A';
                tokens[key] = '<code>' + content + '</code>';
                return key;
            });

            text = text.replace(/\[([^\]\n]+)\]\x28([^\s\x29]+)\x29/g, function(_, label, url) {
                url = self.mdSanitizeLink(url);
                if (!url) return _; // leave literal text untouched
                var key = '\u001A' + (i++) + '\u001A';
                tokens[key] = '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
                return key;
            });

            text = text.replace(/(?<!["'])(https?:\/\/[^\s<>&"'\x29]+)/g, function(full) {
                var suffix = (full.match(/[.,;:!?]+$/) || [''])[0];
                var url = full.slice(0, full.length - suffix.length);
                if (!url) return full;
                var key = '\u001A' + (i++) + '\u001A';
                tokens[key] = '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
                return key + suffix;
            });

            text = text.replace(/\*\*\*([^*\n]+)\*\*\*/g, '<strong><em>$1</em></strong>');
            text = text.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>');

            // Soft break: single newline inside a block is a line break.
            text = text.replace(/\n/g, '<br>');

            Object.keys(tokens).forEach(function(key) {
                text = text.split(key).join(tokens[key]);
            });
            return text;
        },

        mdAllLinesMatch: function(lines, pattern) {
            for (var k = 0; k < lines.length; k++) {
                if (lines[k].trim() === '') continue;
                if (!pattern.test(lines[k])) return false;
            }
            return true;
        },

        mdIsListBlock: function(lines) {
            var anyMarker = false;
            for (var k = 0; k < lines.length; k++) {
                var t = lines[k].trim();
                if (t === '') continue;
                if (/^[-*][ \t]+/.test(t) || /^\d+[.][ \t]+/.test(t)) { anyMarker = true; continue; }
                if (!anyMarker) return false;
            }
            return anyMarker;
        },

        mdRenderList: function(lines) {
            var items = [];
            var ordered = null;
            for (var k = 0; k < lines.length; k++) {
                var t = lines[k].trim();
                if (t === '') continue;
                var m = t.match(/^[-*][ \t]+(.*)$/);
                if (m) { ordered = false; items.push(m[1]); continue; }
                m = t.match(/^\d+[.][ \t]+(.*)$/);
                if (m) { ordered = true; items.push(m[1]); continue; }
                if (items.length) { items[items.length - 1] += '<br>' + lines[k].trim(); }
            }
            var tag = ordered ? 'ol' : 'ul';
            var lis = items.map(function(item) { return '<li>' + AdminPanel.mdInline(item) + '</li>'; }).join('');
            return '<' + tag + '>' + lis + '</' + tag + '>';
        },

        // Render markdown source to HTML (mirrors RichText::markdown).
        mdToHtml: function(src) {
            src = String(src || '').replace(/\r\n?/g, '\n');
            var text = this.mdEscapeHtml(src);
            var blocks = text.split(/\n[ \t]*\n/);
            var out = [];
            var self = this;

            blocks.forEach(function(rawBlock) {
                var block = rawBlock.trim();
                if (!block) return;
                var lines = block.split('\n');

                if (/^(#{1,3})[ \t]+/.test(lines[0])) {
                    lines.forEach(function(line) {
                        var m = line.match(/^(#{1,3})[ \t]+(.*)$/);
                        if (m) {
                            var level = Math.min(m[1].length + 1, 4);
                            out.push('<h' + level + '>' + self.mdInline(m[2]) + '</h' + level + '>');
                        } else {
                            out.push('<p>' + self.mdInline(line.trim()) + '</p>');
                        }
                    });
                } else if (self.mdAllLinesMatch(lines, /^&gt;[ \t]?(.*)$/)) {
                    var quote = lines.map(function(line) {
                        var m = line.match(/^&gt;[ \t]?(.*)$/);
                        return m ? (m[1] === '' ? '<br>' : m[1]) : line.trim();
                    });
                    out.push('<blockquote>' + self.mdInline(quote.join('\n')) + '</blockquote>');
                } else if (/^(-{3,}|\*{3,}|_{3,})[ \t]*$/.test(block)) {
                    out.push('<hr>');
                } else if (self.mdIsListBlock(lines)) {
                    out.push(self.mdRenderList(lines));
                } else {
                    out.push('<p>' + self.mdInline(block) + '</p>');
                }
            });

            return out.join('\n');
        },

        // ---- Toolbar commands ----

        mdWrapSelection: function(ta, before, after) {
            var start = ta.selectionStart, end = ta.selectionEnd;
            var val = ta.value;
            var sel = val.substring(start, end);
            var replacement = before + sel + after;
            ta.value = val.substring(0, start) + replacement + val.substring(end);
            ta.selectionStart = start + before.length;
            ta.selectionEnd = start + before.length + sel.length;
            ta.focus();
        },

        mdLinePrefix: function(ta, prefix) {
            var start = ta.selectionStart;
            var val = ta.value;
            var lineStart = val.lastIndexOf('\n', start - 1) + 1;
            var lineEnd = val.indexOf('\n', start);
            if (lineEnd === -1) lineEnd = val.length;
            var line = val.substring(lineStart, lineEnd);
            if (line.indexOf(prefix) !== 0) {
                ta.value = val.substring(0, lineStart) + prefix + line + val.substring(lineEnd);
                ta.selectionStart = ta.selectionEnd = lineStart + prefix.length + (start - lineStart);
            }
            ta.focus();
        },

        mdInsertBlock: function(ta, text) {
            var start = ta.selectionStart, end = ta.selectionEnd;
            var val = ta.value;
            ta.value = val.substring(0, start) + text + val.substring(end);
            ta.selectionStart = ta.selectionEnd = start + text.length;
            ta.focus();
        },

        mdApplyCommand: function(ta, cmd) {
            if (!ta) return;
            switch (cmd) {
                case 'bold': this.mdWrapSelection(ta, '**', '**'); break;
                case 'italic': this.mdWrapSelection(ta, '*', '*'); break;
                case 'code': this.mdWrapSelection(ta, '`', '`'); break;
                case 'link': {
                    var start = ta.selectionStart, end = ta.selectionEnd;
                    var sel = ta.value.substring(start, end);
                    var url = window.prompt('Enter URL (https://…):', 'https://');
                    if (url === null) return;
                    url = this.mdSanitizeLink(url);
                    if (!url) { window.alert('Only http/https/mailto links are allowed.'); return; }
                    var label = sel !== '' ? sel : url;
                    this.mdInsertBlock(ta, '[' + label + '](' + url + ')');
                    break;
                }
                case 'heading': this.mdLinePrefix(ta, '## '); break;
                case 'bullet': this.mdLinePrefix(ta, '- '); break;
                case 'quote': this.mdLinePrefix(ta, '> '); break;
                case 'hr': this.mdInsertBlock(ta, '\n\n---\n\n'); break;
            }
        },

        // ---- Editor init ----

        initUpdatesMarkdownEditor: function() {
            var self = this;
            $('.updates-md-editor').each(function() {
                var $wrap = $(this);
                var $ta = $wrap.find('textarea');
                var $preview = $wrap.find('.updates-md-preview');
                if (!$ta.length) return;

                var refresh = function() {
                    if ($preview.length) {
                        $preview.html(self.mdToHtml($ta.val()));
                    }
                };

                $wrap.find('.updates-md-toolbar button[data-md]').on('click', function(e) {
                    e.preventDefault();
                    self.mdApplyCommand($ta[0], $(this).data('md'));
                    refresh();
                    $ta.trigger('input'); // keep admin-forms autosave in sync
                });

                var timer = null;
                $ta.on('input', function() {
                    clearTimeout(timer);
                    timer = setTimeout(refresh, 150);
                });

                refresh();
            });
        }
    });

    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initUpdatesMarkdownEditor();
        }
    });

})(jQuery);
