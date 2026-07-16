<?php
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
    <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
    <meta name="googlebot" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
    <meta name="bingbot" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
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
    // Default Open Graph tags (can be overridden via $extraMeta)
    $defaultOgImage = FULL_BASE_PATH . 'assets/images/yunus-emre-vurgun-portrait.jpg';
    $defaultOgTitle = htmlspecialchars($title, ENT_QUOTES);
    $defaultOgDesc = htmlspecialchars($description, ENT_QUOTES);
    ?>
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:title" content="<?= $defaultOgTitle ?>">
    <meta property="og:description" content="<?= $defaultOgDesc ?>">
    <meta property="og:image" content="<?= htmlspecialchars($defaultOgImage) ?>">
    <meta property="og:image:alt" content="<?= $defaultOgTitle ?>">
    <meta property="og:site_name" content="Yµn ^…^ ƒ(x) Personal Website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta name="twitter:title" content="<?= $defaultOgTitle ?>">
    <meta name="twitter:description" content="<?= $defaultOgDesc ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($defaultOgImage) ?>">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?= FULL_BASE_PATH ?>sitemap.xml">
    <link rel="alternate" type="text/plain" title="LLMs" href="<?= FULL_BASE_PATH ?>llms.txt">
    <link rel="alternate" type="application/ld+json" title="LLMs" href="<?= FULL_BASE_PATH ?>llms.txt">
    <link rel="icon" type="image/svg+xml" sizes="32x32" href="<?= FULL_BASE_PATH ?>assets/images/favicon.svg">
    <link rel="icon" type="image/svg+xml" sizes="16x16" href="<?= FULL_BASE_PATH ?>assets/images/favicon.svg">
    <link rel="shortcut icon" href="<?= FULL_BASE_PATH ?>assets/images/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= FULL_BASE_PATH ?>assets/images/favicon.svg">
    <meta name="msapplication-TileImage" content="<?= FULL_BASE_PATH ?>assets/images/favicon.svg">
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/ui-rebuild.css?v=<?= filemtime(__DIR__ . '/../../assets/css/ui-rebuild.css') ?>">
    <?php if (isset($extraMeta['yunobot']) && $extraMeta['yunobot']): ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/yunobot.css?v=<?= filemtime(__DIR__ . '/../../assets/css/yunobot.css') ?>">
    <?php endif; ?>
    <?php if (isset($extraMeta['music']) && $extraMeta['music']): ?>
    <link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/music.css?v=<?= filemtime(__DIR__ . '/../../assets/css/music.css') ?>">
    <?php endif; ?>
    <?php foreach ($extraMeta as $key => $value):
        if (!is_int($key) && !is_string($key)) continue;
        if (is_string($key) && in_array($key, ['yunobot', 'music'], true)) continue;
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
        ?>
<footer class="ui-footer">
    <div class="ui-footer-meta">
        <p>© <?= date('Y') ?> Yunus Emre Vurgun. All rights reserved.</p>
        <ul class="ui-footer-socials" role="list">
            <li><a href="https://github.com/yunusemrejr" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.399s2.046.133 3.003.399c2.293-1.552 3.301-1.23 3.301-1.23.652 1.653.241 2.873.117 3.176.77.84 1.236 1.91 1.236 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                <span class="ui-footer-social-label">GitHub</span>
            </a></li>
            <li><a href="https://www.youtube.com/@yunusemrevurgun1" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                <span class="ui-footer-social-label">YouTube</span>
            </a></li>
            <li><a href="https://linkedin.com/in/yunus-emre-vurgun-49ba9a177" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                <span class="ui-footer-social-label">LinkedIn</span>
            </a></li>
            <li><a href="https://mastodon.social/@yunusemrevurgn" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.268 5.313c-.35-2.578-2.617-4.61-5.304-5.004C17.51.242 15.792 0 11.813 0h-.03c-3.98 0-4.835.242-5.288.309C3.882.692 1.496 2.518.917 5.127.64 6.412.61 7.837.661 9.143c.074 1.874.088 3.745.26 5.611.118 1.24.325 2.47.62 3.68.55 2.237 2.777 4.098 4.96 4.857 2.336.792 4.849.923 7.256.38.265-.061.527-.132.786-.213.585-.184 1.27-.39 1.774-.753a.057.057 0 0 0 .023-.043v-1.809a.052.052 0 0 0-.02-.041.053.053 0 0 0-.046-.01 20.282 20.282 0 0 1-4.709.545c-2.73 0-3.463-1.284-3.674-1.818a5.593 5.593 0 0 1-.319-1.433.053.053 0 0 1 .066-.054c1.517.363 3.072.546 4.632.546.376 0 .75 0 1.125-.01 1.57-.044 3.224-.124 4.768-.422.038-.008.077-.015.11-.024 2.435-.464 4.753-1.92 4.989-5.604.008-.145.03-1.52.03-1.67.002-.512.167-3.63-.024-5.545zm-3.748 9.195h-2.561V8.29c0-1.309-.55-1.976-1.67-1.976-1.23 0-1.846.79-1.846 2.35v3.403h-2.546V8.663c0-1.56-.617-2.35-1.848-2.35-1.112 0-1.668.668-1.67 1.977v6.218H4.822V8.102c0-1.31.337-2.35 1.011-3.12.696-.77 1.608-1.164 2.74-1.164 1.311 0 2.302.5 2.962 1.498l.638 1.06.638-1.06c.66-.999 1.65-1.498 2.96-1.498 1.13 0 2.043.395 2.74 1.164.675.77 1.012 1.81 1.012 3.12z"/></svg>
                <span class="ui-footer-social-label">Mastodon</span>
            </a></li>
            <li><a href="https://bsky.app/profile/yunusemrevurgun.bsky.social" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M3.468 1.948C5.303 3.325 7.276 6.118 8 7.616c.725-1.498 2.698-4.29 4.532-5.668C13.855.955 16 .186 16 2.632c0 .489-.28 4.105-.444 4.692-.572 2.04-2.653 2.561-4.504 2.246 3.236.551 4.06 2.375 2.281 4.2-3.376 3.464-4.852-.87-5.23-1.98-.07-.204-.103-.3-.103-.218 0-.081-.033.014-.102.218-.379 1.11-1.855 5.444-5.231 1.98-1.778-1.825-.955-3.65 2.28-4.2-1.85.315-3.932-.205-4.503-2.246C.28 6.737 0 3.12 0 2.632 0 .186 2.145.955 3.468 1.948"/></svg>
                <span class="ui-footer-social-label">Bluesky</span>
            </a></li>
            <li><a href="https://www.threads.com/@yemrevu" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.75-1.757-.513-.586-1.308-.883-2.359-.89h-.029c-.844 0-1.992.232-2.721 1.32L7.734 7.847c.98-1.454 2.568-2.256 4.478-2.256h.044c3.194.02 5.097 1.975 5.287 5.388.108.046.216.094.321.142 1.49.7 2.58 1.761 3.154 3.07.797 1.82.871 4.79-1.548 7.158-1.85 1.81-4.094 2.628-7.277 2.65Zm1.003-11.69c-.242 0-.487.007-.739.021-1.836.103-2.98.946-2.916 2.143.067 1.256 1.452 1.839 2.784 1.767 1.224-.065 2.818-.543 3.086-3.71a10.5 10.5 0 0 0-2.215-.221z"/></svg>
                <span class="ui-footer-social-label">Threads</span>
            </a></li>
            <li><a href="https://instagram.com/yemrevu" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                <span class="ui-footer-social-label">Instagram</span>
            </a></li>
            <li><a href="https://x.com/yemrevu" target="_blank" rel="noopener noreferrer">
                <svg class="ui-footer-social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                <span class="ui-footer-social-label">X / Twitter</span>
            </a></li>
        </ul>
    </div>
</footer>
<script src="<?= FULL_BASE_PATH ?>assets/js/ui-interactions.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ui-interactions.js') ?>"></script>
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
