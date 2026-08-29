<?php
// Socials model backs the site footer's social list (and /contact, /more,
// home JSON-LD). Loaded once; the class definition itself is inert until used.
require_once dirname(__DIR__) . '/../models/Socials.php';

if (!function_exists('ui_render_head')) {
    function ui_render_head(string $title, string $description, array $extraMeta = []): void
    {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#e3e2de">
    <?php
    // Pages may override the index directive via extraMeta['robots'] (e.g. the
    // search results page, which is thin and has an unbounded ?q= URL space).
    // Overridden here rather than emitted as a second tag: two robots metas
    // make crawlers guess.
    $robots = is_string($extraMeta['robots'] ?? null) && $extraMeta['robots'] !== ''
        ? $extraMeta['robots']
        : 'index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1';
    ?>
    <meta name="robots" content="<?= htmlspecialchars($robots) ?>">
    <meta name="googlebot" content="<?= htmlspecialchars($robots) ?>">
    <meta name="bingbot" content="<?= htmlspecialchars($robots) ?>">
    <meta name="x-robots-tag" content="index,follow,noarchive">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="title" content="<?= htmlspecialchars($title) ?>">
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <?php
    $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
    $requestPath = (string)parse_url($requestPath, PHP_URL_PATH);
    // Normalize: /home is duplicate content of /
    $normalizedPath = $requestPath;
    if ($normalizedPath === '/home' || $normalizedPath === '/home/') {
        $normalizedPath = '/';
    }
    $canonical = rtrim(FULL_BASE_PATH, '/') . ($normalizedPath === '' ? '/' : $normalizedPath);
    ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <?php
    // Default Open Graph / social-share image: the home hero rendered to a
    // 1200x630 PNG (social platforms don't render SVG). Regenerate with
    // dev/scripts/build-og-image.sh when assets/images/desktop.svg changes.
    // Pages may override the social-share image via extraMeta['og_image']
    // (emitted BEFORE any page-specific og:image tag so it wins for crawlers
    // that use the first tag). Default: the home hero rendered to 1200x630 PNG.
    $defaultOgImage = (is_string($extraMeta['og_image'] ?? null) && $extraMeta['og_image'] !== '')
        ? $extraMeta['og_image']
        : FULL_BASE_PATH . 'assets/images/og-image.png';
    $defaultOgTitle = htmlspecialchars($title, ENT_QUOTES);
    $defaultOgDesc = htmlspecialchars($description, ENT_QUOTES);
    // Declaring the raster's real size lets platforms reserve preview space in one
    // pass. Measured, not assumed: omitted when the file is remote or unreadable.
    $ogImageSize = '';
    $ogImagePath = __DIR__ . '/../../' . ltrim((string)parse_url($defaultOgImage, PHP_URL_PATH), '/');
    if (is_file($ogImagePath)) {
        $ogImageDims = @getimagesize($ogImagePath);
        if (is_array($ogImageDims)) {
            $ogImageSize = sprintf(
                '<meta property="og:image:width" content="%d">' . "\n    "
                . '<meta property="og:image:height" content="%d">' . "\n    "
                . '<meta property="og:image:type" content="%s">',
                $ogImageDims[0],
                $ogImageDims[1],
                $ogImageDims['mime']
            );
        }
    }
    ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_US">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:title" content="<?= $defaultOgTitle ?>">
    <meta property="og:description" content="<?= $defaultOgDesc ?>">
    <meta property="og:image" content="<?= htmlspecialchars($defaultOgImage) ?>">
    <?= $ogImageSize . "\n" ?>
    <meta property="og:image:alt" content="<?= $defaultOgTitle ?>">
    <meta property="og:site_name" content="Yunus Emre Vurgun">
    <meta name="author" content="Yunus Emre Vurgun">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta name="twitter:title" content="<?= $defaultOgTitle ?>">
    <meta name="twitter:description" content="<?= $defaultOgDesc ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($defaultOgImage) ?>">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?= FULL_BASE_PATH ?>sitemap.xml">
    <link rel="alternate" type="text/plain" title="LLMs" href="<?= FULL_BASE_PATH ?>llms.txt">
    <link rel="alternate" type="application/rss+xml" title="Journal (RSS)" href="<?= FULL_BASE_PATH ?>blog.xml">
    <link rel="alternate" type="application/rss+xml" title="Updates (RSS)" href="<?= FULL_BASE_PATH ?>updates.xml">
    <link rel="alternate" type="application/rss+xml" title="Random Memories (RSS)" href="<?= FULL_BASE_PATH ?>rmrp.xml">
    <link rel="manifest" href="<?= FULL_BASE_PATH ?>manifest.json?v=<?= filemtime(__DIR__ . '/../../manifest.json') ?>">
    <?php $faviconUrl = FULL_BASE_PATH . 'assets/images/favicon.svg?v=' . filemtime(__DIR__ . '/../../assets/images/favicon.svg'); ?>
    <link rel="icon" type="image/svg+xml" href="<?= $faviconUrl ?>">
    <link rel="shortcut icon" href="<?= $faviconUrl ?>">
    <link rel="apple-touch-icon" href="<?= $faviconUrl ?>">
    <meta name="msapplication-TileImage" content="<?= $faviconUrl ?>">
    <link rel="mask-icon" href="<?= FULL_BASE_PATH ?>assets/images/favicon.svg" color="#566178">
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/ui-rebuild.css?v=<?= filemtime(__DIR__ . '/../../assets/css/ui-rebuild.css') ?>">
    <?php if (isset($extraMeta['yunobot']) && $extraMeta['yunobot']): ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/yunobot.css?v=<?= filemtime(__DIR__ . '/../../assets/css/yunobot.css') ?>">
    <?php endif; ?>
    <?php if (isset($extraMeta['music']) && $extraMeta['music']): ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/music.css?v=<?= filemtime(__DIR__ . '/../../assets/css/music.css') ?>">
    <?php endif; ?>
    <?php if (isset($extraMeta['downloads']) && $extraMeta['downloads']): ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/downloads.css?v=<?= filemtime(__DIR__ . '/../../assets/css/downloads.css') ?>">
    <?php endif; ?>
    <?php foreach ($extraMeta as $key => $value):
        if (!is_int($key) && !is_string($key)) continue;
        if (is_string($key) && in_array($key, ['yunobot', 'music', 'downloads', 'og_image', 'robots'], true)) continue;
        if (!empty($value) && is_string($value)) echo $value . "\n";
    endforeach; ?>
</head>
<?php
    }
}

if (!function_exists('ui_nav_items')) {
    function ui_nav_items(): array
    {
        return [
            ['label' => 'Home', 'href' => FULL_BASE_PATH . 'home'],
            ['label' => 'About', 'href' => FULL_BASE_PATH . 'about'],
            ['label' => 'Portfolio', 'href' => FULL_BASE_PATH . 'portfolio'],
            ['label' => 'Gallery', 'href' => FULL_BASE_PATH . 'gallery'],
            ['label' => 'Blog', 'href' => FULL_BASE_PATH . 'blog'],
            ['label' => 'Travel', 'href' => FULL_BASE_PATH . 'travel'],
            ['label' => 'Updates', 'href' => FULL_BASE_PATH . 'updates'],
            ['label' => 'Contact', 'href' => FULL_BASE_PATH . 'contact'],
        ];
    }
}

if (!function_exists('ui_page_fx')) {
    // Per-page ambient WebGPU background. One canvas + one shared bundle
    // (assets/js/vgpu-pages.min.js); pages without an entry render nothing.
    // Effect shaders live in dev/scripts/vgpu-pages/pages-entry.js.
    function ui_page_fx(): ?string
    {
        static $fxMap = [
            'about' => 'wind',          // gusty paper wisps from top-left
            'rmrp' => 'memories',       // fading dust specks
            'music' => 'wave',          // thin undulating waveform ribbon
            'comedy' => 'bubbles',      // sparse rising rings
            'science-corner' => 'lattice', // nodes slowly lighting up
            'post-code' => 'grid',      // blueprint grid + scan pulses
        ];
        $fxPage = strtok(trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/'), '/');
        return is_string($fxPage) ? ($fxMap[$fxPage] ?? null) : null;
    }
}

if (!function_exists('ui_render_navbar')) {
    function ui_render_navbar(string $active = '', bool $isHome = false): void
    {
        $activeAliases = [
            'work' => 'portfolio',
            'studio' => 'about',
            'experiments' => 'travel',
            'journal' => 'blog',
        ];
        $normalizedActive = strtolower(trim($active));
        if (isset($activeAliases[$normalizedActive])) {
            $normalizedActive = $activeAliases[$normalizedActive];
        }
        $requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
        $requestPath = strtolower($requestPath);
        if ($requestPath === '') {
            $requestPath = 'home';
        }
        $pageFx = ui_page_fx();
        ?>
<header class="ui-navbar-wrap">
    <nav class="ui-navbar" aria-label="Main">
        <div class="ui-nav-left">
            <a class="ui-wordmark" href="<?= FULL_BASE_PATH ?>">Yemre</a>
            <ul class="ui-nav-links" role="list">
                <?php foreach (ui_nav_items() as $item):
                    $itemLabel = strtolower($item['label']);
                    $itemPath = trim(parse_url($item['href'], PHP_URL_PATH) ?? '', '/');
                    $itemPath = strtolower($itemPath);
                    $isActive = ($normalizedActive !== '' && $normalizedActive === $itemLabel)
                        || ($itemPath !== '' && ($requestPath === $itemPath || str_starts_with($requestPath, $itemPath . '/')));
                    ?>
                <li>
                    <a class="ui-nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                <li>
                    <a class="ui-nav-link ui-nav-link-more<?= $requestPath === 'more' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>more" aria-label="More pages">+</a>
                </li>
            </ul>
            <button class="ui-mobile-toggle" type="button" aria-label="Open menu" data-mobile-menu-open aria-expanded="false" aria-controls="uiMobileMenu"><span></span><span></span><span></span></button>
        </div>
    </nav>
</header>
<!-- Page fx canvas: mounted behind content; vgpu-pages.min.js removes it
     entirely when WebGPU is unavailable (plain paper bg is the fallback). -->
<?php if ($pageFx !== null): ?>
<canvas class="ui-page-fx" data-fx="<?= htmlspecialchars($pageFx) ?>" aria-hidden="true"></canvas>
<?php endif; ?>
<div class="ui-mobile-menu" id="uiMobileMenu" aria-hidden="true">
    <div class="ui-mobile-backdrop" data-mobile-menu-close></div>
    <div class="ui-mobile-panel">
        <button class="ui-mobile-close" type="button" aria-label="Close menu" data-mobile-menu-close>×</button>
        <ul class="ui-mobile-links" role="list">
            <?php foreach (ui_nav_items() as $item): ?>
                <li><a href="<?= htmlspecialchars($item['href']) ?>" data-mobile-menu-close><?= htmlspecialchars($item['label']) ?></a></li>
            <?php endforeach; ?>
            <li><a href="<?= FULL_BASE_PATH ?>more" data-mobile-menu-close aria-label="More pages">+</a></li>
        </ul>
    </div>
</div>
<?php
    }
}



if (!function_exists('ui_render_footer')) {
    function ui_render_footer(): void
    {
        $socialsModel = new Socials();
        ?>
<footer class="ui-footer">
    <div class="ui-footer-meta">
        <p>© <?= date('Y') ?> Yunus Emre Vurgun. All rights reserved.</p>
        <?php
        $footerSocials = $socialsModel->getActiveByLocation('footer');
        $hasPopup = false;
        foreach ($footerSocials as $s) {
            if ($s['kind'] === Socials::KIND_POPUP) { $hasPopup = true; break; }
        }
        ?>
        <?php if (!empty($footerSocials)): ?>
        <ul class="ui-footer-socials" role="list">
            <?php foreach ($footerSocials as $s):
                $sLabel = htmlspecialchars($s['name'] ?? '', ENT_QUOTES);
                $sIcon = Socials::iconSvg($s['icon'] ?? 'link', 'ui-footer-social-icon');
            ?>
            <?php if ($s['kind'] === Socials::KIND_POPUP): ?>
            <li><button type="button" class="ui-footer-social-btn" data-x-popup-trigger aria-haspopup="dialog" aria-label="<?= $sLabel ?> (not available)">
                <?= $sIcon ?>
                <span class="ui-footer-social-label"><?= $sLabel ?></span>
            </button></li>
            <?php else: ?>
            <li><a href="<?= htmlspecialchars($s['url'] ?? '#', ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer">
                <?= $sIcon ?>
                <span class="ui-footer-social-label"><?= $sLabel ?></span>
            </a></li>
            <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

    </div>
</footer>
<?php if ($hasPopup): ?>
<div class="ui-x-popup" id="uiXPopup" aria-hidden="true">
    <div class="ui-x-popup-backdrop" data-x-popup-close></div>
    <div class="ui-x-popup-panel" role="dialog" aria-modal="true" aria-labelledby="uiXPopupTitle">
        <button type="button" class="ui-x-popup-close" data-x-popup-close aria-label="Close">×</button>
        <svg class="ui-x-popup-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        <h2 class="ui-x-popup-title" id="uiXPopupTitle">oops! no twitter/x!</h2>
        <p class="ui-x-popup-text">why? elon's boys randomly banned me without any reason.</p>
        <p class="ui-x-popup-gg">gg....</p>
    </div>
</div>
<?php endif; ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ui-interactions.js') ?>"></script>
<?php if (ui_page_fx() !== null): ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/vgpu-pages.min.js?v=<?= filemtime(__DIR__ . '/../../assets/js/vgpu-pages.min.js') ?>"></script>
<script>
(function () {
    var fxCanvas = document.querySelector('.ui-page-fx');
    if (!fxCanvas) {
        return;
    }
    // WHY: the ambient background is pure decoration (aria-hidden, pointer-events:
    // none) and a full-screen WebGPU loop is the one thing on these pages that can
    // cost a phone real memory and GPU time. Every resize it handles (iOS collapses
    // its toolbar while scrolling, and the /about accordions reflow the page height)
    // reallocates the surface. Phones take the same plain-paper fallback the bundle
    // already uses when WebGPU is missing, instead of a new state.
    if (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches) {
        fxCanvas.remove();
        return;
    }
    if (window.VgpuPages && typeof window.VgpuPages.mountPageFx === 'function') {
        window.VgpuPages.mountPageFx(fxCanvas).catch(function () { /* plain bg fallback stays */ });
    }
})();
</script>
<?php endif; ?>
<?php
    }
}

if (!function_exists('ui_sanitize_html')) {
    function ui_sanitize_html(string $html): string
    {
        if (empty($html)) return '';
        
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div', 'img', 'figure', 'figcaption'];
        $allowed_attrs = ['href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel'];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<div>' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//@*|//script|//style|//comment()');
        foreach ($nodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE && ($node->nodeName === 'script' || $node->nodeName === 'style')) {
                $node->parentNode->removeChild($node);
            } elseif ($node->nodeType === XML_ATTRIBUTE_NODE) {
                if (!in_array($node->nodeName, $allowed_attrs, true)) {
                    $node->ownerElement->removeAttribute($node->nodeName);
                } elseif ($node->nodeName === 'href' || $node->nodeName === 'src') {
                    $value = $node->nodeValue;
                    if (preg_match('/^javascript:/i', trim($value)) || preg_match('/\bon\w+\s*=/i', $value)) {
                        $node->ownerElement->removeAttribute($node->nodeName);
                    }
                }
            } elseif ($node->nodeType === XML_COMMENT_NODE) {
                $node->parentNode->removeChild($node);
            }
        }
        
        $container = $doc->getElementsByTagName('div')->item(0);
        $innerHtml = '';
        if ($container) {
            foreach ($container->childNodes as $child) {
                $innerHtml .= $doc->saveHTML($child);
            }
        }
        return $innerHtml;
    }
}

if (!function_exists('ui_render_gumroad_widget')) {
    // Gumroad Products Promoter Widget (floating books button + panel).
    // Markup lives in gumroad-widget.php (pure HTML, no PHP tags) so standalone
    // pages that don't load ui.php (403/500/admin) can readfile() it directly.
    function ui_render_gumroad_widget(): void
    {
        $widgetFile = __DIR__ . '/gumroad-widget.php';
        if (file_exists($widgetFile)) {
            readfile($widgetFile);
        }
    }
}

if (!function_exists('ui_render_tracker_codes')) {
    function ui_render_tracker_codes(string $baseDir): void
    {
        $phpSelf = $_SERVER['PHP_SELF'] ?? '';
        if (!strpos($phpSelf, 'admin.php')) {
            $trackerFile = $baseDir . '/models/Tracker.php';
            if (file_exists($trackerFile)) {
                require_once $trackerFile;
                $tracker = new Tracker();
                $trackerCodes = $tracker->getActiveTrackerCodes();
                if (is_array($trackerCodes)) {
                    foreach ($trackerCodes as $code) {
                        if (isset($code['code']) && !empty(trim($code['code']))) {
                            $trackerHtml = trim($code['code']);
                            echo $trackerHtml . "\n";
                        }
                    }
                }
            }
        }
    }
}
