<?php
/**
 * Ebook promotion — single owner for every "Remain Valuable" placement.
 *
 * All copy here is limited to what the Gumroad page supports: a 224-page
 * practical book about the scarce human, economic, and strategic advantages
 * that stay valuable when AI/AGI can do most cognitive work, sold as
 * PDF + EPUB by The Knowledge Project. No price, no reviews, no urgency.
 */

if (!defined('EBOOK_URL')) {
    define('EBOOK_URL', 'https://theknowledgeproject.gumroad.com/l/remainvaluable');
}
if (!defined('EBOOK_TITLE')) {
    define('EBOOK_TITLE', 'How to Remain Valuable When Intelligence Becomes Cheap');
}

if (!function_exists('ebook_cover_url')) {
    // Local cover (mirrored from the Gumroad product page) with a CDN
    // fallback so a missing file never breaks a page.
    function ebook_cover_url(): string
    {
        $path = __DIR__ . '/../../assets/images/ebook-remain-valuable.png';
        if (is_file($path)) {
            return FULL_BASE_PATH . 'assets/images/ebook-remain-valuable.png?v=' . filemtime($path);
        }
        return 'https://public-files.gumroad.com/tsfgci8o5y0po0259dlzn7fhzzra';
    }
}

if (!function_exists('ebook_link_attrs')) {
    // External-link convention matches the rest of the site (footer Books
    // link, socials): new tab, opener protection.
    function ebook_link_attrs(): string
    {
        return 'href="' . EBOOK_URL . '" target="_blank" rel="noopener noreferrer"';
    }
}

if (!function_exists('ui_render_ebook_notice')) {
    /**
     * Slim site-wide announcement strip. Rendered inside the fixed navbar
     * wrap (see ebook.css for the height compensation); dismissal persists
     * in localStorage. Server-rendered, so there is no layout shift on
     * first paint — the inline script below only hides an already-dismissed
     * strip before paint.
     */
    function ui_render_ebook_notice(): void
    {
        ?>
<aside class="ebook-notice" id="ebookNotice" aria-label="New ebook announcement">
    <div class="ebook-notice-inner">
        <span class="ebook-notice-marker" aria-hidden="true">New</span>
        <p class="ebook-notice-text">
            <span class="ebook-notice-full"><strong><?= htmlspecialchars(EBOOK_TITLE) ?></strong> — my 224-page ebook on staying valuable as AI advances (PDF + EPUB).</span>
            <span class="ebook-notice-short"><strong>New ebook:</strong> Remain Valuable.</span>
        </p>
        <a class="ebook-notice-cta" <?= ebook_link_attrs() ?>>Get the ebook →</a>
        <button class="ebook-notice-dismiss" type="button" id="ebookNoticeDismiss" aria-label="Dismiss announcement">×</button>
    </div>
</aside>
<script>
(function () {
    var key = 'yev-ebook-notice-v1';
    var notice = document.getElementById('ebookNotice');
    var dismiss = document.getElementById('ebookNoticeDismiss');
    if (!notice) return;
    try {
        if (window.localStorage && localStorage.getItem(key)) {
            notice.hidden = true;
            return;
        }
    } catch (e) { /* private mode: leave the notice up */ }
    if (dismiss) {
        dismiss.addEventListener('click', function () {
            notice.hidden = true;
            try { if (window.localStorage) localStorage.setItem(key, '1'); } catch (e) {}
        });
    }
})();
</script>
<noscript><style>.ebook-notice-dismiss { display: none; }</style></noscript>
<?php
    }
}

if (!function_exists('ui_render_ebook_feature')) {
    // Homepage feature: the one full-width treatment on the site, reusing
    // the landing brief's editorial two-column rhythm (text + framed figure).
    function ui_render_ebook_feature(): void
    {
        ?>
<section class="ui-section" aria-labelledby="ebookFeatureTitle">
    <p class="ui-eyebrow">The book</p>
    <h2 class="ui-brief-title" id="ebookFeatureTitle"><?= htmlspecialchars(EBOOK_TITLE) ?></h2>
    <div class="ebook-feature-grid">
        <div>
            <p class="ebook-feature-lede">A 224-page practical guide to what stays scarce — and worth paying for — when intelligence itself becomes cheap. It is the longer argument behind much of this site's writing on AI, automation, and post-code engineering.</p>
            <ul class="ebook-feature-points">
                <li>The human advantages automation can't replicate</li>
                <li>The economic logic of scarcity in an age of cheap cognition</li>
                <li>Strategic positioning for a career that survives capable AI</li>
            </ul>
            <div class="ebook-feature-cta">
                <a class="ui-btn ui-btn-primary" <?= ebook_link_attrs() ?>>Get the ebook →</a>
                <span class="ebook-feature-meta">224 pages · PDF + EPUB · via Gumroad</span>
            </div>
        </div>
        <figure class="ebook-feature-figure">
            <img src="<?= htmlspecialchars(ebook_cover_url()) ?>" width="810" height="1080" loading="lazy" decoding="async" alt="Cover of the ebook <?= htmlspecialchars(EBOOK_TITLE) ?>.">
            <figcaption>The ebook, available as PDF and EPUB.</figcaption>
        </figure>
    </div>
</section>
<?php
    }
}

if (!function_exists('ui_render_ebook_aside')) {
    /**
     * Ruled editorial aside for article-adjacent contexts.
     * $variant: 'article' (end of a journal post), 'post-code', 'downloads'.
     * No mid-article injection: the journal's posts are one-minute reads,
     * where an interruption would overpower the content it sits in.
     */
    function ui_render_ebook_aside(string $variant = 'article'): void
    {
        $copy = [
            'article' => [
                'eyebrow' => 'From the author',
                'text' => 'If this post got you thinking about where technical work is headed, the book develops the argument in full: the scarce human, economic, and strategic advantages that stay valuable even when AI can do most cognitive work.',
                'cta' => 'Get the 224-page ebook (PDF + EPUB) →',
            ],
            'post-code' => [
                'eyebrow' => 'Going deeper',
                'text' => 'These modules are the free starting point. The book extends the same question — what stays valuable when intelligence is cheap — into a 224-page practical guide on the human, economic, and strategic advantages that remain scarce when AI can do most cognitive work.',
                'cta' => 'Get the ebook (PDF + EPUB) →',
            ],
            'downloads' => [
                'eyebrow' => 'Prefer reading to installing',
                'text' => 'Alongside the free software here, there is a 224-page ebook: a practical guide to the scarce human, economic, and strategic advantages that stay valuable even when AI can do most cognitive work.',
                'cta' => 'Get the ebook (PDF + EPUB) →',
            ],
        ];
        $c = $copy[$variant] ?? $copy['article'];
        ?>
<aside class="ebook-aside" aria-label="About the author's ebook">
    <p class="ui-eyebrow"><?= htmlspecialchars($c['eyebrow']) ?></p>
    <h2 class="ebook-aside-title"><?= htmlspecialchars(EBOOK_TITLE) ?></h2>
    <p class="ebook-aside-text"><?= htmlspecialchars($c['text']) ?></p>
    <a class="ebook-aside-cta" <?= ebook_link_attrs() ?>><?= htmlspecialchars($c['cta']) ?></a>
</aside>
<?php
    }
}

if (!function_exists('ui_render_ebook_line')) {
    /**
     * Quiet single-sentence placements for indexes, guides, and states.
     * $variant: 'blog-index', 'yunobot', 'contact', 'missing', 'search'.
     * $plain: omit the top hairline when the surrounding panel already
     * provides a boundary (e.g. contact success box).
     */
    function ui_render_ebook_line(string $variant, bool $plain = false): void
    {
        $copy = [
            'blog-index' => '<strong>Longer than a post</strong> — ' . EBOOK_TITLE . ', the 224-page ebook behind much of this journal’s AI writing.',
            'yunobot' => '<strong>Want the full argument?</strong> YunoBot answers in brief from this site; the 224-page ebook ' . EBOOK_TITLE . ' develops it completely.',
            'contact' => '<strong>While you wait</strong> — the 224-page ebook ' . EBOOK_TITLE . ' covers staying valuable as AI advances.',
            'missing' => '<strong>Or start with the book</strong> — ' . EBOOK_TITLE . ' (PDF + EPUB).',
            'search' => '<strong>Nothing on the site matches</strong> — but the 224-page ebook ' . EBOOK_TITLE . ' covers staying valuable as AI advances.',
        ];
        $text = $copy[$variant] ?? $copy['blog-index'];
        ?>
<p class="ebook-line<?= $plain ? ' ebook-line--plain' : '' ?>"><?= $text ?> <a <?= ebook_link_attrs() ?>>Get it on Gumroad →</a></p>
<?php
    }
}
