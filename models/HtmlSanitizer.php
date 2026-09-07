<?php
/** Allowlisted article HTML, shared by persistence and public rendering. */
final class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        if (trim($html) === '') return '';
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div', 'img', 'figure', 'figcaption', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'sup', 'sub'];
        $drop = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'template'];
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) return '';
        $walk = function (DOMNode $parent) use (&$walk, $allowed, $drop): void {
            foreach (iterator_to_array($parent->childNodes) as $node) {
                if ($node instanceof DOMComment || $node instanceof DOMProcessingInstruction) { $parent->removeChild($node); continue; }
                if (!$node instanceof DOMElement) continue;
                $tag = strtolower($node->tagName);
                if (in_array($tag, $drop, true)) { $parent->removeChild($node); continue; }
                $walk($node);
                if (!in_array($tag, $allowed, true)) {
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                    continue;
                }
                $attributes = ['title', 'lang'];
                if ($tag === 'a') $attributes = array_merge($attributes, ['href', 'target']);
                if ($tag === 'img') $attributes = array_merge($attributes, ['src', 'alt', 'width', 'height']);
                if (in_array($tag, ['th', 'td'], true)) $attributes = array_merge($attributes, ['colspan', 'rowspan', 'scope']);
                if ($tag === 'code') $attributes[] = 'class';
                foreach (iterator_to_array($node->attributes) as $attr) {
                    if (!in_array($attr->name, $attributes, true)) $node->removeAttribute($attr->name);
                }
                foreach (['href', 'src'] as $attr) {
                    if (!$node->hasAttribute($attr)) continue;
                    $url = trim($node->getAttribute($attr));
                    $probe = preg_replace('/[\x00-\x20\x7f]/', '', $url);
                    $scheme = parse_url($probe, PHP_URL_SCHEME);
                    $safe = !str_starts_with($probe, '//') && !str_contains($probe, '\\')
                        && (!$scheme || in_array(strtolower($scheme), $attr === 'href' ? ['https', 'http', 'mailto', 'tel'] : ['https', 'http'], true));
                    if (!$safe) $node->removeAttribute($attr); else $node->setAttribute($attr, $url);
                }
                if ($tag === 'a' && $node->getAttribute('target') === '_blank') $node->setAttribute('rel', 'noopener noreferrer');
                elseif ($tag === 'a') $node->removeAttribute('target');
                if ($tag === 'img') {
                    $node->setAttribute('loading', 'lazy');
                    $node->setAttribute('decoding', 'async');
                    if (!$node->hasAttribute('alt')) $node->setAttribute('alt', '');
                }
            }
        };
        $walk($body);
        $output = '';
        foreach ($body->childNodes as $node) $output .= $doc->saveHTML($node);
        return $output;
    }
}
