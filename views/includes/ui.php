<?php
// Socials model backs the site footer's social list (and /contact, /more,
// home JSON-LD). Loaded once; the class definition itself is inert until used.
require_once dirname(__DIR__) . '/../models/Socials.php';

if (!function_exists('ui_render_head')) {
    function ui_render_head(string $title, string $description, array $extraMeta = []): void
    {
        // A single metadata owner avoids conflicting crawler and sharing hints.
        $ogType = $extraMeta['og_type'] ?? 'website';
        foreach ($extraMeta as $value) {
            if (is_string($value) && preg_match('/<meta property="og:type" content="([^"]+)"/', $value, $match)) {
                $ogType = $match[1];
            }
        }
        if (http_response_code() >= 400) $extraMeta['robots'] = 'noindex,follow';
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
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <?php
    $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $basePath = rtrim((string)parse_url(FULL_BASE_PATH, PHP_URL_PATH), '/');
    if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
        $requestPath = substr($requestPath, strlen($basePath));
    }
    $normalizedPath = '/' . trim($requestPath, '/');
    if (in_array($normalizedPath, ['/home', '/index', '/index.php'], true)) $normalizedPath = '/';
    $canonical = $extraMeta['canonical'] ?? (rtrim(FULL_BASE_PATH, '/') . $normalizedPath);
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
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES) ?>">
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
    <?php $collectionPage = in_array(trim($requestPath, '/'), ['post-code','science-corner','comedy','music','videos','downloads','more','rmrp'], true); ?>
    <?php if ($collectionPage): ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/collections.css?v=<?= filemtime(__DIR__ . '/../../assets/css/collections.css') ?>">
    <script src="<?= FULL_BASE_PATH ?>assets/js/collections.js?v=<?= filemtime(__DIR__ . '/../../assets/js/collections.js') ?>" defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/variables.css?v=<?= filemtime(__DIR__ . '/../../assets/css/variables.css') ?>">
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
        if (is_string($key) && in_array($key, ['yunobot', 'music', 'downloads', 'og_image', 'og_type', 'robots', 'canonical'], true)) continue;
        if (!empty($value) && is_string($value)) {
            // Defaults above already own these tags; page-specific article data remains.
            if (preg_match('/<meta (?:name|property)="(?:author|description|keywords|og:(?:type|url|title|description|image)|twitter:(?:card|url|title|description|image))"/', $value)) continue;
            echo $value . "\n";
        }
    endforeach; ?>
</head>
<?php
    }
}

if (!function_exists('ui_nav_items')) {
    function ui_nav_items(): array
    {
        return [
            ['label' => 'Home', 'href' => FULL_BASE_PATH],
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
<a class="ui-skip-link" href="#main-content">Skip to content</a>
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
                    <a class="ui-nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                <li>
                    <a class="ui-nav-link ui-nav-link-more<?= $requestPath === 'more' ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>more" aria-label="More pages">More</a>
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
    <div class="ui-mobile-panel" role="dialog" aria-modal="true" aria-label="Site navigation">
        <button class="ui-mobile-close" type="button" aria-label="Close menu" data-mobile-menu-close>×</button>
        <ul class="ui-mobile-links" role="list">
            <?php foreach (ui_nav_items() as $item): ?>
                <li><a href="<?= htmlspecialchars($item['href']) ?>" data-mobile-menu-close><?= htmlspecialchars($item['label']) ?></a></li>
            <?php endforeach; ?>
            <li><a href="<?= FULL_BASE_PATH ?>more" data-mobile-menu-close aria-label="More pages">More</a></li>
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
            <li><a href="<?= htmlspecialchars($s['url'] ?? '#', ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $sLabel ?>">
                <?= $sIcon ?>
                <span class="ui-footer-social-label"><?= $sLabel ?></span>
            </a></li>
            <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

    </div>
    <nav class="ui-footer-links" aria-label="Site resources">
        <a href="<?= FULL_BASE_PATH ?>search">Search</a>
        <a href="<?= FULL_BASE_PATH ?>more">Explore</a>
        <a href="https://theknowledgeproject.gumroad.com/" target="_blank" rel="noopener noreferrer">Books</a>
        <a href="<?= FULL_BASE_PATH ?>blog.xml">RSS</a>
        <a href="<?= FULL_BASE_PATH ?>sitemap">Sitemap</a>
        <a href="<?= FULL_BASE_PATH ?>privacy">Privacy</a>
        <a href="<?= FULL_BASE_PATH ?>contact">Contact</a>
    </nav>
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
<script src="<?= FULL_BASE_PATH ?>assets/js/navigation.js?v=<?= filemtime(__DIR__ . '/../../assets/js/navigation.js') ?>"></script>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ui-interactions.js') ?>"></script>
<?php if (ui_page_fx() !== null): ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/decorative-effects.js?v=<?= filemtime(__DIR__ . '/../../assets/js/decorative-effects.js') ?>" data-effect-src="<?= FULL_BASE_PATH ?>assets/js/vgpu-pages.min.js?v=<?= filemtime(__DIR__ . '/../../assets/js/vgpu-pages.min.js') ?>" data-effect-kind="page"></script>
<?php endif; ?>
<?php
    }
}

if (!function_exists('ui_sanitize_html')) {
    function ui_sanitize_html(string $html): string
    {
        require_once __DIR__ . '/../../models/HtmlSanitizer.php';
        return HtmlSanitizer::clean($html);
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
