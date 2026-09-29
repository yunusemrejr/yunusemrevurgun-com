<?php
/**
 * Shared rendering for the two knowledge archives (/post-code, /science-corner):
 * cover-art cards that fold open, related entries, tag search, and a
 * page-specific atlas. Content stays in each page's own $modules array.
 */
require_once __DIR__ . '/visuals.php';

if (!function_exists('kn_assets')) {
    /** Extra <head> links, echoed right after ui_render_head (same as before). */
    function kn_assets(): void
    {
        $css = static fn(string $f): string => FULL_BASE_PATH . 'assets/css/' . $f . '?v=' . filemtime(__DIR__ . '/../../assets/css/' . $f);
        $js = FULL_BASE_PATH . 'assets/js/knowledge.js?v=' . filemtime(__DIR__ . '/../../assets/js/knowledge.js');
        echo '<link rel="stylesheet" href="' . $css('post-code.css') . '">' . "\n";
        echo '<link rel="stylesheet" href="' . $css('visuals.css') . '">' . "\n";
        echo '<link rel="stylesheet" href="' . $css('knowledge.css') . '">' . "\n";
        echo '<script src="' . $js . '" defer></script>' . "\n";
    }

    /** Top-of-page reading progress bar. */
    function kn_progress(): void
    {
        echo '<div class="kn-progress" aria-hidden="true"><span></span></div>';
    }

    /** Words in an entry's prose. */
    function kn_minutes(array $m): int
    {
        return vis_reading_minutes($m['text']);
    }

    /**
     * Two entries most related to $i: shared tags weigh 3, same category 1.
     *
     * @return list<int> indexes into $modules
     */
    function kn_related(array $modules, int $i, int $limit = 2): array
    {
        $scores = [];
        foreach ($modules as $j => $other) {
            if ($j === $i) continue;
            $score = count(array_intersect($modules[$i]['tags'], $other['tags'])) * 3
                + ($modules[$i]['category'] === $other['category'] ? 1 : 0);
            if ($score > 0) $scores[$j] = $score;
        }
        arsort($scores);
        return array_slice(array_keys($scores), 0, $limit);
    }

    /**
     * One entry card. $motif picks the generated cover; $eyebrow is the small
     * line above the title (module number, or life span).
     */
    function kn_card(array $modules, int $i, string $motif, string $eyebrow, string $kindLabel): void
    {
        $m = $modules[$i];
        $id = ui_entry_id($m['title']);
        $long = mb_strlen($m['text']) > 320 || !empty($m['hasMath']);
        $related = kn_related($modules, $i);
        ?>
<article class="ui-feed-card kn-card" id="<?= $id ?>" data-collection-item data-category="<?= htmlspecialchars($m['category']) ?>">
    <div class="kn-cover"><?= vis_cover($motif, $m['title']) ?><span class="kn-cover-pill"><?= htmlspecialchars($kindLabel) ?></span></div>
    <div class="kn-card-body">
        <p class="kn-eyebrow"><?= htmlspecialchars($eyebrow) ?><span aria-hidden="true">·</span><?= kn_minutes($m) ?> min read</p>
        <h2 class="ui-feed-title"><a href="#<?= $id ?>"><?= htmlspecialchars($m['title']) ?></a></h2>
        <?php if (!empty($m['tags'])): ?>
            <div class="ui-postcode-tags">
                <?php foreach ($m['tags'] as $tag): ?>
                    <button type="button" class="ui-postcode-tag kn-tag" data-kn-tag="<?= htmlspecialchars($tag) ?>" title="Find more about <?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="kn-prose<?= $long ? ' is-clamped' : '' ?>" id="<?= $id ?>-text">
            <p class="ui-feed-text"><?= htmlspecialchars($m['text']) ?></p>
            <?php if (!empty($m['hasMath']) && !empty($m['math'])): ?>
                <div class="ui-code-block kn-math">
                    <?php foreach ($m['math'] as $formula): ?>
                        \[<?= $formula ?>\]
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($long): ?>
            <button type="button" class="kn-toggle" data-kn-toggle aria-expanded="false" aria-controls="<?= $id ?>-text"><span data-open>Read more</span><span data-close>Show less</span></button>
        <?php endif; ?>
        <div class="kn-foot">
            <?php if ($related): ?>
                <p class="kn-related"><span>Related</span>
                    <?php foreach ($related as $j): ?>
                        <a href="#<?= ui_entry_id($modules[$j]['title']) ?>"><?= htmlspecialchars(preg_replace('/\s+[—–-]\s+.*/u', '', $modules[$j]['title'])) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <button type="button" class="kn-copy" data-kn-copy="<?= $id ?>">Copy link</button>
        </div>
    </div>
</article>
<?php
    }

    /** Search box + random button + index; wraps ui_collection_tools. */
    function kn_extra_tools(): void
    {
        echo '<div class="kn-quick"><button type="button" class="ui-btn ui-btn-secondary" data-kn-random>Surprise me</button><span class="kn-hint">Press <kbd>/</kbd> to search</span></div>';
    }
}
