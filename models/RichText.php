<?php
/**
 * RichText — a tiny, dependency-free markdown-subset renderer for the
 * site's short "Updates" entries.
 *
 * Supported syntax (this file is the single source of truth; the admin live
 * preview in assets/js/admin/updates-editor.js mirrors it — keep in sync):
 *
 *   Paragraphs      blank line separates blocks
 *   Soft break      a single newline inside a block becomes <br>
 *   Headings        # / ## / ###  (rendered as h2/h3/h4; h1 is the update title)
 *   Unordered list  consecutive "- " or "* " lines
 *   Ordered list    consecutive "1. " lines
 *   Blockquote      consecutive "> " lines
 *   Horizontal rule a lone "---"
 *   Bold            **text**
 *   Italic          *text*
 *   Code span       `text`
 *   Link            [label](https://...)  (http/https/mailto or same-site /path)
 *   Bare URL        https://example.com is auto-linked
 *
 * Security: input is HTML-escaped before any processing, so only tags this
 * renderer itself emits can appear in the output. Link targets are restricted
 * to http/https/mailto (javascript:, data: etc. are dropped); external links
 * get target="_blank" rel="noopener noreferrer".
 */
final class RichText
{
    public static function markdown(?string $source): string
    {
        $source = (string) $source;
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $text = htmlspecialchars($source, ENT_QUOTES, 'UTF-8');

        // Split into blocks on blank lines (a line containing only whitespace).
        $rawBlocks = preg_split('/\n[ \t]*\n/', $text) ?: [];
        $html = [];

        foreach ($rawBlocks as $rawBlock) {
            $block = trim($rawBlock);
            if ($block === '') {
                continue;
            }

            $lines = preg_split('/\n/', $block) ?: [];

            if (preg_match('/^(#{1,3})[ \t]+/', $lines[0])) {
                // h1 is the update title on the single page, so # maps to h2.
                // ATX headings need no blank line between them or following text.
                foreach ($lines as $line) {
                    if (preg_match('/^(#{1,3})[ \t]+(.*)$/', $line, $m)) {
                        $level = min(strlen($m[1]) + 1, 4);
                        $html[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                    } else {
                        $html[] = '<p>' . self::inline(trim($line)) . '</p>';
                    }
                }
                continue;
            } elseif (self::allLinesMatch($lines, '/^&gt;[ \t]?(.*)$/')) {
                $quote = [];
                foreach ($lines as $line) {
                    if (preg_match('/^&gt;[ \t]?(.*)$/', $line, $m)) {
                        $quote[] = $m[1] === '' ? '<br>' : $m[1];
                    } else {
                        $quote[] = trim($line);
                    }
                }
                $html[] = '<blockquote>' . self::inline(implode("\n", $quote)) . '</blockquote>';
            } elseif (preg_match('/^(-{3,}|\*{3,}|_{3,})[ \t]*$/', $block)) {
                $html[] = '<hr>';
            } elseif (self::isListBlock($lines)) {
                $html[] = self::renderList($lines);
            } else {
                $html[] = '<p>' . self::inline($block) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    /**
     * Convert markdown source to plain text (used for excerpts, meta
     * descriptions and search snippets). Approximate by design.
     */
    public static function plainText(?string $source): string
    {
        $text = (string) $source;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Links keep their label; code spans keep their content.
        $text = preg_replace('/\[([^\]\n]*)\]\([^)\s]+\)/', '$1', $text);
        $text = preg_replace('/`([^`\n]+)`/', '$1', $text);
        $text = str_replace(['**', '`'], '', $text);
        $text = str_replace('*', '', $text);

        // Block markers.
        $text = preg_replace('/^#{1,6}[ \t]+/m', '', $text);
        $text = preg_replace('/^>[ \t]?/m', '', $text);
        $text = preg_replace('/^[ \t]*[-*][ \t]+/m', '', $text);
        $text = preg_replace('/^[ \t]*\d+[.][ \t]+/m', '', $text);
        $text = preg_replace('/^[ \t]*(-{3,}|\*{3,}|_{3,})[ \t]*$/m', '', $text);

        // Collapse whitespace to single spaces.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        $text = str_replace("\n", ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** Inline transforms: code spans, links, bare URLs, bold/italic. */
    private static function inline(string $text): string
    {
        $tokens = [];
        $i = 0;

        // Code spans first so their contents are not processed further.
        $text = preg_replace_callback('/`([^`\n]+)`/', function ($m) use (&$tokens, &$i) {
            $key = "\x1A" . ($i++) . "\x1A";
            $tokens[$key] = '<code>' . $m[1] . '</code>';
            return $key;
        }, $text);

        // Explicit links: [label](url). Invalid schemes are left as literal text.
        $text = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', function ($m) use (&$tokens, &$i) {
            $url = self::sanitizeLink($m[2]);
            if ($url === '') {
                return $m[0];
            }
            $key = "\x1A" . ($i++) . "\x1A";
            $tokens[$key] = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
            return $key;
        }, $text);

        // Bare http(s) URLs. The lookbehind keeps URLs that already sit inside
        // an href value (they were replaced with tokens above, so this is a
        // belt-and-braces guard). Trailing sentence punctuation is trimmed.
        $text = preg_replace_callback('/(?<!["\'])(https?:\/\/[^\s<>&"\'\)]+)/', function ($m) use (&$tokens, &$i) {
            // Keep trailing sentence punctuation outside the link.
            $suffix = '';
            if (preg_match('/[.,;:!?]+$/', $m[1], $sm)) {
                $suffix = $sm[0];
            }
            $url = rtrim($m[1], '.,;:!?');
            if ($url === '') {
                return $m[0];
            }
            $key = "\x1A" . ($i++) . "\x1A";
            $tokens[$key] = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
            return $key . $suffix;
        }, $text);

        // Emphasis: ***bold italic***, **bold**, *italic* (no underscore
        // variants — they mangle snake_case inside normal prose).
        $text = preg_replace('/\*\*\*([^*\n]+)\*\*\*/', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text);

        // Soft break: a single newline inside a block is a line break (this
        // content type is short timestamped notes, where Enter = new line).
        $text = str_replace("\n", '<br>', $text);

        return strtr($text, $tokens);
    }

    /** Render a block of consecutive list-marker lines. */
    private static function renderList(array $lines): string
    {
        $items = [];
        $ordered = null;
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^[-*][ \t]+(.*)$/', $t, $m)) {
                $ordered = false;
                $items[] = $m[1];
            } elseif (preg_match('/^\d+[.][ \t]+(.*)$/', $t, $m)) {
                $ordered = true;
                $items[] = $m[1];
            } else {
                // Continuation line: append to the previous item with a break.
                if ($items) {
                    $items[count($items) - 1] .= '<br>' . trim($line);
                }
            }
        }

        $tag = $ordered ? 'ol' : 'ul';
        $lis = '';
        foreach ($items as $item) {
            $lis .= '<li>' . self::inline($item) . '</li>';
        }
        return '<' . $tag . '>' . $lis . '</' . $tag . '>';
    }

    /** True when every non-empty line matches the given pattern. */
    private static function allLinesMatch(array $lines, string $pattern): bool
    {
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (!preg_match($pattern, $line)) {
                return false;
            }
        }
        return true;
    }

    /** True when every non-empty line is a list marker or a continuation. */
    private static function isListBlock(array $lines): bool
    {
        $anyMarker = false;
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^[-*][ \t]+/', $t) || preg_match('/^\d+[.][ \t]+/', $t)) {
                $anyMarker = true;
                continue;
            }
            // A non-marker line is only allowed as a continuation of the
            // previous item; a block that starts mid-paragraph is not a list.
            if (!$anyMarker) {
                return false;
            }
        }
        return $anyMarker;
    }

    /** Restrict link targets to safe schemes or same-site relative paths. */
    private static function sanitizeLink(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^(https?|mailto):/i', $url)) {
            return $url;
        }
        // Same-site relative path; reject protocol-relative "//host" URLs.
        if (preg_match('#^/[^/]#', $url)) {
            return $url;
        }
        return '';
    }
}
