<?php
/**
 * Documentation pages: /downloads/<app>, /yunobot/<topic>, /gemmaclaim/<topic>,
 * /jelloshop/<topic>. Content lives in views/docs/pages.php; for app pages the
 * facts (language, licence, requirements, release) come from
 * includes/downloads_catalog.php, so they are written down once.
 *
 * Layout, top to bottom: breadcrumb, h1, an answer panel (direct answer, key facts,
 * actions), the detail sections, quick answers, and links that stay inside the
 * page's own topic (its hub and sibling pages).
 */
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/models/Socials.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/seo.php';

$registry = require __DIR__ . '/pages.php';
$base = FULL_BASE_PATH;
$origin = seo_origin();

$isDev = php_sapi_name() === 'cli-server' || getenv('MODE') === 'development';
$path = $isDev
    ? trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')
    : trim((string)($_GET['url'] ?? ''), '/');

if (!isset($registry['pages'][$path])) {
    http_response_code(404);
    require dirname(__DIR__) . '/404.php';
    return;
}

$p = $registry['pages'][$path];
$hub = $registry['hubs'][$p['silo']];
$url = $origin . '/' . $path;
$render = static fn(string $html): string => str_replace('{{base}}', $base, $html);
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// ---- facts ---------------------------------------------------------------------
$facts = $p['facts'] ?? [];
$actions = $p['actions'] ?? [];
$cover = null;
$catalogEntry = null;

if ($p['kind'] === 'software') {
    $slug = substr($path, strlen('downloads/'));
    foreach ((require dirname(__DIR__, 2) . '/includes/downloads_catalog.php') as $entry) {
        if ($entry['slug'] === $slug) { $catalogEntry = $entry; break; }
    }
    if ($catalogEntry === null) {
        http_response_code(404);
        require dirname(__DIR__) . '/404.php';
        return;
    }
    $c = $catalogEntry;
    $repoUrl = 'https://github.com/' . $c['repo'];
    $facts = array_merge([
        ['Platform', $esc(implode(', ', $c['platforms']))],
        ['Language', $esc($c['language'])],
        ['Licence', $c['license'] ? $esc($c['license']) : 'Not stated in the repository'],
        ['Requires', '<ul>' . implode('', array_map(static fn(string $d): string => '<li>' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</li>', $c['requires'])) . '</ul>'],
        ['Run', $c['run'] ? '<code>' . $esc($c['run']) . '</code> from the repository folder' : 'See the README for the install steps'],
        ['Source', '<a href="' . $esc($repoUrl) . '" target="_blank" rel="noopener noreferrer">' . $esc($c['repo']) . ' on GitHub</a>'],
    ], $facts);
    $actions = array_merge([['label' => 'View on GitHub ↗', 'href' => $repoUrl, 'external' => true, 'primary' => true]], $actions);
    if ($c['release']) {
        $actions[] = ['label' => $c['release']['label'] . ' ' . $c['release']['tag'] . ' ↗', 'href' => $c['release']['url'], 'external' => true];
    }
    $coverFile = dirname(__DIR__, 2) . '/assets/images/downloads/' . $c['cover'];
    if (is_file($coverFile)) {
        $cover = [
            'src' => $base . 'assets/images/downloads/' . $c['cover'] . '?v=' . filemtime($coverFile),
            'alt' => $c['cover_alt'], 'width' => 1200, 'height' => 750,
            'caption' => $p['cover_caption'] ?? null,
        ];
    }
}

// ---- reading time and word count ---------------------------------------------
$plain = seo_plain($p['answer'] . ' ' . implode(' ', array_map(static fn(array $s): string => $s[1], $p['sections'])));
$words = str_word_count($plain);
$minutes = max(1, (int)ceil($words / 220));

$toc = [];
foreach ($p['sections'] as $i => [$heading]) {
    $toc[] = ['id' => 's' . ($i + 1) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($heading)), '-'), 'label' => $heading];
}

// ---- structured data -----------------------------------------------------------
$hubUrl = $origin . '/' . $hub['path'];
$graph = [];
if ($p['kind'] === 'software' && $catalogEntry) {
    $c = $catalogEntry;
    $app = [
        '@type' => 'SoftwareApplication',
        '@id' => $url . '#app',
        'name' => $c['title'],
        'url' => $url,
        'description' => $p['description'],
        'applicationCategory' => $p['app']['category'],
        'operatingSystem' => implode(', ', $c['platforms']),
        'softwareRequirements' => implode('; ', $c['requires']),
        'isAccessibleForFree' => true,
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
        'inLanguage' => 'en',
        'author' => seo_person(),
        'sameAs' => ['https://github.com/' . $c['repo']],
        'datePublished' => $p['published'],
        'dateModified' => $p['modified'],
        'about' => seo_things($p['about']),
        'mentions' => seo_things($p['mentions']),
    ];
    if ($cover) $app['image'] = $origin . parse_url($cover['src'], PHP_URL_PATH);
    if ($c['license'] === 'MIT') $app['license'] = 'https://opensource.org/license/mit';
    if ($c['release']) { $app['softwareVersion'] = ltrim($c['release']['tag'], 'v'); $app['downloadUrl'] = $c['release']['url']; }
    $graph[] = $app;
    $graph[] = array_filter([
        '@type' => 'SoftwareSourceCode',
        '@id' => $url . '#source',
        'name' => $c['title'] . ' source code',
        'codeRepository' => 'https://github.com/' . $c['repo'],
        'programmingLanguage' => $c['language'],
        'runtimePlatform' => implode(', ', $c['platforms']),
        'license' => $c['license'] === 'MIT' ? 'https://opensource.org/license/mit' : null,
        'author' => seo_person(),
        'targetProduct' => ['@id' => $url . '#app'],
    ]);
} else {
    $graph[] = [
        '@type' => 'TechArticle',
        '@id' => $url . '#article',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'headline' => $p['h1'],
        'description' => $p['description'],
        'datePublished' => $p['published'],
        'dateModified' => $p['modified'],
        'wordCount' => $words,
        'timeRequired' => 'PT' . $minutes . 'M',
        'inLanguage' => 'en',
        'articleSection' => $hub['name'],
        'proficiencyLevel' => 'Beginner',
        'about' => seo_things($p['about']),
        'mentions' => seo_things($p['mentions']),
        'isPartOf' => ['@id' => $hubUrl . ($hub['node'] ?? '')],
        'author' => seo_person(),
        'publisher' => seo_person(),
    ];
}
$graph[] = seo_breadcrumbs([['Home', $origin . '/'], [$hub['name'], $hubUrl], [$p['crumb'], $url]]);
if (!empty($p['faq'])) $graph[] = seo_faq($p['faq']);

$meta = ['docs' => true, seo_script($graph)];
if ($p['kind'] === 'article') {
    $meta[] = '<meta property="og:type" content="article">';
    $meta[] = '<meta property="article:published_time" content="' . $esc($p['published']) . '">';
    $meta[] = '<meta property="article:modified_time" content="' . $esc($p['modified']) . '">';
    $meta[] = '<meta property="article:section" content="' . $esc($hub['name']) . '">';
    $meta[] = '<meta name="twitter:label1" content="Reading time">';
    $meta[] = '<meta name="twitter:data1" content="' . $minutes . ' min read">';
}
if ($cover) $meta['og_image'] = $origin . parse_url($cover['src'], PHP_URL_PATH) . '?v=' . filemtime(dirname(__DIR__, 2) . '/assets/images/downloads/' . $catalogEntry['cover']);

ui_render_head($p['title'], $p['description'], $meta);

$related = array_values(array_filter(array_map(static fn(string $r) => isset($registry['pages'][$r]) ? [$r, $registry['pages'][$r]] : null, $p['related'] ?? [])));
?>
<body class="ui-docs-page">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main dx-page" id="main-content" tabindex="-1">
        <nav class="dx-crumbs" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= $base ?>">Home</a></li>
                <li><a href="<?= $base . $esc($hub['path']) ?>"><?= $esc($hub['name']) ?></a></li>
                <li aria-current="page"><?= $esc($p['crumb']) ?></li>
            </ol>
        </nav>

        <article>
            <header class="dx-head">
                <h1 class="dx-title"><?= $esc($p['h1']) ?></h1>
                <p class="dx-meta"><?= $p['kind'] === 'software' ? 'Open-source software' : 'Project documentation' ?> · <?= $minutes ?> min read · updated <time datetime="<?= $esc($p['modified']) ?>"><?= date('F j, Y', strtotime($p['modified'])) ?></time></p>
            </header>

            <div class="dx-answer">
                <?= $render($p['answer']) ?>
                <?php if ($facts): ?>
                <dl class="dx-facts">
                    <?php foreach ($facts as [$term, $value]): ?>
                    <div><dt><?= $esc($term) ?></dt><dd><?= $render($value) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php endif; ?>
                <?php if ($actions): ?>
                <div class="dx-actions">
                    <?php foreach ($actions as $a): ?>
                    <a class="ui-btn <?= !empty($a['primary']) ? 'ui-btn-primary' : 'ui-btn-secondary' ?>" href="<?= $esc($render($a['href'])) ?>"<?= !empty($a['external']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= $esc($a['label']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($cover): ?>
            <figure class="dx-figure">
                <img src="<?= $esc($cover['src']) ?>" alt="<?= $esc($cover['alt']) ?>" width="<?= $cover['width'] ?>" height="<?= $cover['height'] ?>" loading="lazy" decoding="async">
                <?php if (!empty($cover['caption'])): ?><figcaption><?= $esc($cover['caption']) ?></figcaption><?php endif; ?>
            </figure>
            <?php endif; ?>

            <?php if (count($toc) > 3): ?>
            <nav class="dx-toc" aria-label="On this page">
                <p class="dx-toc-title">On this page</p>
                <ol>
                    <?php foreach ($toc as $t): ?>
                    <li><a href="#<?= $esc($t['id']) ?>"><?= $esc($t['label']) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (!empty($p['faq'])): ?><li><a href="#faq">Quick answers</a></li><?php endif; ?>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="ui-rich-content dx-body">
                <?php foreach ($p['sections'] as $i => [$heading, $html]): ?>
                <section aria-labelledby="<?= $esc($toc[$i]['id']) ?>">
                    <h2 id="<?= $esc($toc[$i]['id']) ?>"><?= $esc($heading) ?></h2>
                    <?= $render($html) ?>
                </section>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($p['faq'])): ?>
            <section class="dx-section" aria-labelledby="faq">
                <h2 id="faq">Quick answers</h2>
                <dl class="dx-faq">
                    <?php foreach ($p['faq'] as [$q, $a]): ?>
                    <div><dt><?= $esc($q) ?></dt><dd><?= $esc($a) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
            <?php endif; ?>
        </article>

        <section class="dx-section" aria-labelledby="dx-more">
            <h2 id="dx-more">More on <?= $esc($hub['name']) ?></h2>
            <ul class="dx-links" role="list">
                <li>
                    <a href="<?= $base . $esc($hub['path']) ?>">
                        <span class="dx-links-title"><?= $esc($hub['link_title']) ?></span>
                        <span class="dx-links-desc"><?= $esc($hub['link_desc']) ?></span>
                    </a>
                </li>
                <?php foreach ($related as [$rPath, $r]): ?>
                <li>
                    <a href="<?= $base . $esc($rPath) ?>">
                        <span class="dx-links-title"><?= $esc($r['h1']) ?></span>
                        <span class="dx-links-desc"><?= $esc($r['description']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
