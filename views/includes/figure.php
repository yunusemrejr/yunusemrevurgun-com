<?php
/**
 * An anatomy figure in its plate: the cream card with a drawing border, "Fig. n" top left, a short
 * title top right, a hint bottom left and a note bottom right, as in the Anatomy kit's own plates
 * (dev/anatomy/), and a true caption under it. The figure itself is a static SVG in assets/anatomy/
 * (built by dev/anatomy/*.mjs), embedded with <img>: it caches like any image, loads lazily and costs
 * no DOM. Its only motion is a few LED blinks inside the file, off under prefers-reduced-motion.
 *
 * ui_render_figure([
 *   'file'    => 'rack.svg',                   file under assets/anatomy/
 *   'width'   => 760, 'height' => 380,         the SVG's own viewBox size (reserves the box: no layout shift)
 *   'alt'     => '...',                        what the picture shows
 *   'fig'     => 'Fig. 1', 'title' => 'Control rack', 'hint' => 'Illustration', 'note' => 'RUN lamp lit',
 *   'caption' => '...',                        a true statement about the figure
 *   'class'   => 'fig--wide',                    optional modifier on the <figure>
 *   'eager'   => false,                        true above the fold
 * ]);
 */
if (!function_exists('ui_anatomy_url')) {
    function ui_anatomy_url(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/assets/anatomy/' . $file;
        return FULL_BASE_PATH . 'assets/anatomy/' . $file . (is_file($path) ? '?v=' . filemtime($path) : '');
    }
}

if (!function_exists('ui_render_figure')) {
    function ui_render_figure(array $o): void
    {
        $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $corner = static fn(string $name, ?string $text) => $text !== null && $text !== ''
            ? '<span class="fig-corner" data-corner="' . $name . '">' . htmlspecialchars($text, ENT_QUOTES) . '</span>'
            : '';
        ?>
<figure class="fig<?= !empty($o['class']) ? ' ' . $h($o['class']) : '' ?>">
    <div class="fig-plate">
        <div class="fig-stage">
            <img src="<?= $h(ui_anatomy_url($o['file'])) ?>" width="<?= (int)($o['width'] ?? 760) ?>" height="<?= (int)($o['height'] ?? 380) ?>"
                 alt="<?= $h($o['alt'] ?? '') ?>" decoding="async"<?= empty($o['eager']) ? ' loading="lazy"' : '' ?>>
        </div>
        <?= $corner('fig', $o['fig'] ?? null) ?>
        <?= $corner('title', $o['title'] ?? null) ?>
        <?= $corner('hint', $o['hint'] ?? null) ?>
        <?= $corner('note', $o['note'] ?? null) ?>
    </div>
    <?php if (!empty($o['caption'])): ?>
    <figcaption class="fig-caption"><?= $h($o['caption']) ?></figcaption>
    <?php endif; ?>
</figure>
<?php
    }
}
