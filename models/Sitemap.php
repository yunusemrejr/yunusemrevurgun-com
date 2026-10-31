<?php
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

/**
 * Sitemap system.
 *
 * /sitemap.xml is a sitemap *index* naming four children, each rendered live
 * from published content (serving a sitemap never writes files):
 *
 *   sitemap-pages.xml   static pages + project documentation
 *   sitemap-posts.xml   blog posts, updates, memories, slop pages
 *   sitemap-images.xml  image extension entries (gallery, travel, covers)
 *   sitemap-videos.xml  video extension entries for /videos
 *
 * renderXML() keeps returning one combined <urlset> for older callers and
 * the regression suite. Every <lastmod> is a real content timestamp: a
 * listing page carries the newest row it lists, never its template's mtime
 * and never "now". When a publishing hook regenerates the disk snapshots,
 * URLs whose lastmod advanced (plus new and removed URLs) are submitted to
 * IndexNow so participating engines recrawl them promptly.
 */
class Sitemap {
    private PDO $db;
    private string $baseUrl;
    /** @var list<array{path:string,lastmod:?string,images:list<array{loc:string,title:?string,caption:?string}>,videos:list<array<string,?string>>,group:string}>|null */
    private ?array $entries = null;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->baseUrl = rtrim(FULL_BASE_PATH, '/');
    }

    /** Combined <urlset> with every entry (backwards-compatible default). */
    public function renderXML(): string {
        return $this->renderUrlset($this->collect());
    }

    /** <sitemapindex> naming the four children with their freshest lastmod. */
    public function renderIndex(): string {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $index = $xml->createElement('sitemapindex');
        $index->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->appendChild($index);
        foreach (['pages', 'posts', 'images', 'videos'] as $group) {
            $entries = $this->group($group);
            if ($group !== 'pages' && $group !== 'posts' && $entries === []) continue;
            $node = $xml->createElement('sitemap');
            $loc = $xml->createElement('loc');
            $loc->appendChild($xml->createTextNode($this->baseUrl . '/sitemap-' . $group . '.xml'));
            $node->appendChild($loc);
            $freshest = null;
            foreach ($entries as $entry) {
                $stamp = $entry['lastmod'] ? strtotime($entry['lastmod']) : false;
                if ($stamp !== false && ($freshest === null || $stamp > $freshest)) $freshest = $stamp;
            }
            if ($freshest !== null && $freshest <= time()) {
                $date = $xml->createElement('lastmod');
                $date->appendChild($xml->createTextNode(date('c', $freshest)));
                $node->appendChild($date);
            }
            $index->appendChild($node);
        }
        return $xml->saveXML();
    }

    public function renderGroup(string $group): string {
        return $this->renderUrlset($this->group($group));
    }

    /** @return list<string> absolute URL of every sitemap entry */
    public function allUrls(): array {
        $urls = [];
        foreach ($this->collect() as $entry) {
            $urls[] = $this->baseUrl . '/' . ltrim($entry['path'], '/');
        }
        return $urls;
    }

    /** @return array<string, ?int> absolute URL => lastmod epoch (null when unknown) */
    public function urlStamps(): array {
        $stamps = [];
        foreach ($this->collect() as $entry) {
            $stamp = $entry['lastmod'] ? strtotime($entry['lastmod']) : false;
            $stamps[$this->baseUrl . '/' . ltrim($entry['path'], '/')] = $stamp !== false ? $stamp : null;
        }
        return $stamps;
    }

    /**
     * Disk snapshots (production only) + IndexNow change submission.
     *
     * The previous snapshot's URL/lastmod map is read before overwriting, so
     * exactly the URLs that appeared, changed or vanished are submitted —
     * never the whole site on every save.
     */
    public function generateSitemapXML(): bool {
        if (getenv('MODE') === 'development' || PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') return true;
        $previous = $this->snapshotStamps();
        $files = [
            'sitemap.xml' => $this->renderIndex(),
            'sitemap-pages.xml' => $this->renderGroup('pages'),
            'sitemap-posts.xml' => $this->renderGroup('posts'),
            'sitemap-images.xml' => $this->renderGroup('images'),
            'sitemap-videos.xml' => $this->renderGroup('videos'),
        ];
        foreach ([__DIR__ . '/..', __DIR__ . '/../public'] as $dir) {
            foreach ($files as $name => $content) {
                $path = $dir . '/' . $name;
                $temporary = tempnam(dirname($path), '.sitemap-');
                if ($temporary === false) throw new RuntimeException('Unable to prepare sitemap snapshot.');
                try {
                    if (file_put_contents($temporary, $content, LOCK_EX) === false) throw new RuntimeException('Unable to write sitemap snapshot.');
                    chmod($temporary, 0644);
                    if (!rename($temporary, $path)) throw new RuntimeException('Unable to replace sitemap snapshot.');
                } finally {
                    if (is_file($temporary)) unlink($temporary);
                }
            }
        }
        $this->submitChanges($previous, $this->urlStamps());
        return true;
    }

    public function forceRegenerate(): bool {
        try { return $this->generateSitemapXML(); }
        catch (Throwable $error) { error_log('Sitemap snapshot failed: ' . $error->getMessage()); return false; }
    }

    // ------------------------------------------------------------ collection

    /** @return list<array{path:string,lastmod:?string,images:list<array{loc:string,title:?string,caption:?string}>,videos:list<array<string,?string>>,group:string}> */
    private function collect(): array {
        if ($this->entries !== null) return $this->entries;
        $entries = [];
        $seen = [];
        $add = static function (array $entry) use (&$entries, &$seen): void {
            $key = $entry['group'] . '|' . $entry['path'];
            // The images/videos children intentionally repeat a page <loc>;
            // inside one child each <loc> still appears once.
            if (isset($seen[$key])) {
                $entries[$seen[$key]]['images'] = array_merge($entries[$seen[$key]]['images'], $entry['images']);
                $entries[$seen[$key]]['videos'] = array_merge($entries[$seen[$key]]['videos'], $entry['videos']);
                return;
            }
            $seen[$key] = count($entries);
            $entries[] = $entry + ['images' => [], 'videos' => []];
        };
        $blank = ['images' => [], 'videos' => []];

        // Listing pages change when their content does, not when their
        // template does, so their lastmod is the newest row they list.
        $latest = [
            'blog' => $this->latestModified("SELECT MAX(COALESCE(updated_at, created_at)) FROM blog_posts WHERE status = 'published'"),
            'updates' => $this->latestModified('SELECT MAX(COALESCE(updated_at, created_at)) FROM updates'),
            'rmrp' => $this->latestModified('SELECT MAX(COALESCE(updated_at, created_at)) FROM rmrp_memories'),
            'slop' => $this->latestModified('SELECT MAX(COALESCE(updated_at, created_at)) FROM slop_pages'),
            'portfolio' => $this->latestModifiedTolerant('SELECT MAX(COALESCE(updated_at, created_at)) FROM portfolio_projects'),
            'gallery' => $this->latestModifiedTolerant('SELECT MAX(uploaded_at) FROM gallery_images WHERE is_archived = 0'),
            'travel' => $this->latestModifiedTolerant('SELECT MAX(created_at) FROM travel_locations'),
            'music' => $this->latestModifiedTolerant('SELECT MAX(COALESCE(updated_at, uploaded_at)) FROM music_tracks WHERE is_archived = 0'),
            'videos' => $this->latestModifiedTolerant('SELECT MAX(COALESCE(updated_at, uploaded_at)) FROM videos WHERE is_archived = 0'),
        ];
        $musicFilms = require dirname(__DIR__) . '/content/music-videos.php';
        $shortFilms = require dirname(__DIR__) . '/content/films.php';
        foreach (array_merge($shortFilms, $musicFilms) as $film) {
            if ($latest['videos'] === null || strtotime($film['published_at']) > strtotime($latest['videos'])) {
                $latest['videos'] = $film['published_at'];
            }
        }
        $latest['downloads'] = date('c', (int)max(@filemtime(__DIR__ . '/../includes/downloads_catalog.php'), @filemtime(__DIR__ . '/../views/downloads/index.php')));
        $homeStamps = array_filter([
            $latest['blog'] ? strtotime($latest['blog']) : false,
            $latest['updates'] ? strtotime($latest['updates']) : false,
            @filemtime(__DIR__ . '/../views/home.php'),
        ]);
        $latest[''] = $homeStamps ? date('c', max($homeStamps)) : null;

        $staticPages = ['', 'about', 'portfolio', 'blog', 'gallery', 'travel', 'updates', 'rmrp', 'contact', 'music', 'videos', 'downloads', 'slop', 'yunobot', 'gemmaclaim', 'chessko', 'chessko/how-it-works', 'chessko/search-and-evaluation', 'chessko/machine-learning', 'chessko/stockfish-webassembly', 'chessko/difficulty-levels', 'jelloshop', 'post-code', 'science-corner', 'comedy', 'more', 'sitemap', 'privacy', 'terms', 'cookies'];
        foreach ($staticPages as $page) {
            $modified = $latest[$page] ?? $this->staticPageModified($page);
            $entry = ['path' => $page, 'lastmod' => $modified, 'group' => 'pages'] + $blank;
            $entry['images'] = $this->pageImages($page);
            $add($entry);
        }
        // Documentation pages under the project hubs (views/docs/pages.php);
        // each carries its own 'modified' date.
        $catalog = [];
        foreach ((require __DIR__ . '/../includes/downloads_catalog.php') as $item) {
            $catalog[(string)$item['slug']] = $item;
        }
        foreach ((require __DIR__ . '/../views/docs/pages.php')['pages'] as $docPath => $doc) {
            $entry = ['path' => $docPath, 'lastmod' => $doc['modified'] ?? null, 'group' => 'pages'] + $blank;
            $slug = basename($docPath);
            if (isset($catalog[$slug]['cover'])) {
                $entry['images'][] = [
                    'loc' => $this->baseUrl . '/assets/images/downloads/' . $catalog[$slug]['cover'],
                    'title' => (string)($catalog[$slug]['title'] ?? $doc['h1'] ?? $doc['title'] ?? ''),
                    'caption' => isset($catalog[$slug]['cover_alt']) ? (string)$catalog[$slug]['cover_alt'] : null,
                ];
            }
            $add($entry);
        }

        // ---- posts: journal, updates, memories, slop
        foreach ([
            ['blog', "SELECT slug AS path, title, featured_image AS image, COALESCE(updated_at, created_at) AS lastmod FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC"],
            ['updates', 'SELECT id AS path, title, NULL AS image, COALESCE(updated_at, created_at) AS lastmod FROM updates ORDER BY created_at DESC'],
            ['rmrp', 'SELECT id AS path, title, NULL AS image, COALESCE(updated_at, created_at) AS lastmod FROM rmrp_memories ORDER BY created_at DESC'],
            ['slop', 'SELECT slug AS path, title, NULL AS image, COALESCE(updated_at, created_at) AS lastmod FROM slop_pages ORDER BY created_at DESC'],
        ] as [$section, $query]) {
            try {
                $statement = $this->db->query($query);
            } catch (PDOException $error) {
                // Optional modules create their tables on first use, and older
                // installs may lack the blog image/title columns this query
                // reads: retry with just path + lastmod before giving up.
                // Other DB failures must surface.
                $message = $error->getMessage();
                $missingTable = str_contains($message, 'no such table') || $error->getCode() === '42S02';
                $missingColumn = str_contains($message, 'no such column') || $error->getCode() === '42S22';
                if (!$missingTable && !$missingColumn) throw $error;
                if ($missingTable) continue;
                $idColumn = $section === 'blog' || $section === 'slop' ? 'slug' : 'id';
                $table = ['blog' => 'blog_posts', 'updates' => 'updates', 'rmrp' => 'rmrp_memories', 'slop' => 'slop_pages'][$section];
                $statusFilter = $section === 'blog' ? " WHERE status = 'published'" : '';
                try {
                    $statement = $this->db->query("SELECT {$idColumn} AS path, NULL AS title, NULL AS image, COALESCE(updated_at, created_at) AS lastmod FROM {$table}{$statusFilter} ORDER BY created_at DESC");
                } catch (PDOException) {
                    continue;
                }
            }
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                if ((string)$row['path'] === '') continue;
                $entry = ['path' => $section . '/' . rawurlencode((string)$row['path']), 'lastmod' => $row['lastmod'], 'group' => 'posts'] + $blank;
                if (!empty($row['image'])) {
                    $entry['images'][] = ['loc' => $this->absUrl((string)$row['image']), 'title' => (string)($row['title'] ?? ''), 'caption' => null];
                }
                $add($entry);
            }
        }

        // ---- images child: gallery + travel photographs, download covers
        $galleryImages = $this->rowsTolerant('SELECT filename, title, uploaded_at FROM gallery_images WHERE is_archived = 0 ORDER BY uploaded_at DESC');
        if ($galleryImages !== []) {
            $entry = ['path' => 'gallery', 'lastmod' => $latest['gallery'], 'group' => 'images'] + $blank;
            foreach ($galleryImages as $image) {
                if (empty($image['filename'])) continue;
                $entry['images'][] = [
                    'loc' => $this->baseUrl . '/uploads/gallery/' . ltrim((string)$image['filename'], '/'),
                    'title' => (string)($image['title'] ?? ''),
                    'caption' => null,
                ];
            }
            if ($entry['images'] !== []) $add($entry);
        }
        $travelImages = $this->rowsTolerant(
            'SELECT i.filename, i.title, i.source, l.city, l.country FROM travel_location_images i '
            . 'LEFT JOIN travel_locations l ON l.id = i.location_id ORDER BY i.id ASC'
        );
        if ($travelImages !== []) {
            $entry = ['path' => 'travel', 'lastmod' => $latest['travel'], 'group' => 'images'] + $blank;
            $root = dirname(__DIR__);
            foreach ($travelImages as $image) {
                $file = ltrim((string)($image['filename'] ?? ''), '/');
                if ($file === '') continue;
                $relative = null;
                if (($image['source'] ?? '') === 'gallery') {
                    $relative = 'uploads/gallery/' . $file;
                } else {
                    foreach (['uploads/travel/', 'assets/images/travel/'] as $prefix) {
                        if (is_file($root . '/' . $prefix . $file)) { $relative = $prefix . $file; break; }
                    }
                    $relative ??= 'uploads/travel/' . $file;
                }
                $where = trim(implode(', ', array_filter([(string)($image['city'] ?? ''), (string)($image['country'] ?? '')])));
                $entry['images'][] = [
                    'loc' => $this->baseUrl . '/' . $relative,
                    'title' => (string)(($image['title'] ?? '') !== '' ? $image['title'] : $where),
                    'caption' => $where !== '' ? 'Photograph from ' . $where : null,
                ];
            }
            if ($entry['images'] !== []) $add($entry);
        }
        if ($catalog !== []) {
            $entry = ['path' => 'downloads', 'lastmod' => $latest['downloads'], 'group' => 'images'] + $blank;
            foreach ($catalog as $item) {
                if (empty($item['cover'])) continue;
                $entry['images'][] = [
                    'loc' => $this->baseUrl . '/assets/images/downloads/' . $item['cover'],
                    'title' => (string)($item['title'] ?? ''),
                    'caption' => isset($item['cover_alt']) ? (string)$item['cover_alt'] : null,
                ];
            }
            if ($entry['images'] !== []) $add($entry);
        }

        // Curated short films are public alongside music films and database-backed videos.
        foreach ($shortFilms as $film) {
            $media = $this->baseUrl . '/' . $film['media_path'];
            $add(['path' => 'videos', 'lastmod' => $latest['videos'], 'group' => 'videos', 'images' => [], 'videos' => [[
                'thumbnail_loc' => $media . '-poster.jpg',
                'title' => $film['title'],
                'description' => $film['description'],
                'player_loc' => null,
                'content_loc' => $media . '-1080p.mp4',
                'duration' => (string)(int)round($film['duration']),
                'publication_date' => $film['published_at'],
            ]]]);
        }

        // Curated music films are public alongside database-backed videos.
        foreach ($musicFilms as $film) {
            $media = $this->baseUrl . '/' . $film['media_path'];
            $add(['path' => 'videos', 'lastmod' => $latest['videos'], 'group' => 'videos', 'images' => [], 'videos' => [[
                'thumbnail_loc' => $media . '-poster.jpg',
                'title' => $film['title'] . ' — music video',
                'description' => $film['description'],
                'player_loc' => null,
                'content_loc' => $media . '-1080p.mp4',
                'duration' => (string)(int)round($film['duration']),
                'publication_date' => $film['published_at'],
            ]]]);
        }

        // ---- videos child
        $videos = $this->rowsTolerant('SELECT id, title, description, type, filename, url, platform, video_id, thumbnail_url, duration, uploaded_at FROM videos WHERE is_archived = 0 ORDER BY uploaded_at DESC');
        if ($videos !== []) {
            $entry = ['path' => 'videos', 'lastmod' => $latest['videos'], 'group' => 'videos'] + $blank;
            foreach ($videos as $video) {
                $thumb = trim((string)($video['thumbnail_url'] ?? ''));
                if ($thumb === '' && ($video['platform'] ?? '') === 'youtube' && !empty($video['video_id'])) {
                    $thumb = 'https://i.ytimg.com/vi/' . rawurlencode((string)$video['video_id']) . '/hqdefault.jpg';
                }
                if ($thumb === '') $thumb = $this->baseUrl . '/assets/images/og-image.png';
                $player = null;
                $content = null;
                if (($video['type'] ?? '') === 'url') {
                    if (($video['platform'] ?? '') === 'youtube' && !empty($video['video_id'])) {
                        $player = 'https://www.youtube-nocookie.com/embed/' . rawurlencode((string)$video['video_id']) . '?rel=0';
                    } elseif (($video['platform'] ?? '') === 'odysee' && !empty($video['video_id'])) {
                        $player = 'https://odysee.com/$/embed/' . ltrim((string)$video['video_id'], '/');
                    } elseif (!empty($video['url'])) {
                        $player = (string)$video['url'];
                    }
                } elseif (!empty($video['filename'])) {
                    $content = $this->baseUrl . '/uploads/videos/' . ltrim((string)$video['filename'], '/');
                }
                if ($player === null && $content === null) continue;
                $description = trim(strip_tags((string)($video['description'] ?? '')));
                $entry['videos'][] = [
                    'thumbnail_loc' => $thumb,
                    'title' => (string)($video['title'] ?? 'Untitled'),
                    'description' => $description !== '' ? $description : (string)($video['title'] ?? 'Video by Yunus Emre Vurgun'),
                    'player_loc' => $player,
                    'content_loc' => $content,
                    'duration' => !empty($video['duration']) ? (string)(int)$video['duration'] : null,
                    'publication_date' => !empty($video['uploaded_at']) ? date('c', (int)strtotime((string)$video['uploaded_at'])) : null,
                ];
            }
            if ($entry['videos'] !== []) $add($entry);
        }

        return $this->entries = $entries;
    }

    /** Representative images actually shown on (or shared for) a static page. */
    private function pageImages(string $page): array {
        $img = fn(string $path, string $title): array => ['loc' => $this->baseUrl . '/' . $path, 'title' => $title, 'caption' => null];
        return match ($page) {
            '' => [
                $img('assets/images/og-image.png', 'Yunus Emre Vurgun, software developer and IT specialist, photographed in front of a pine stand with one gold birch'),
                $img('assets/images/yunus-emre-vurgun-portrait.jpg', 'Portrait of Yunus Emre Vurgun'),
                $img('assets/images/field/portrait-738.jpg', 'Yunus Emre Vurgun standing in front of a pine stand with one birch turned gold, under an overcast sky'),
            ],
            'about' => [$img('assets/images/yunus-emre-vurgun-portrait.jpg', 'Portrait of Yunus Emre Vurgun')],
            'chessko' => [$img('assets/images/og-chessko.png', 'Chessko: browser chess against Stockfish')],
            default => [],
        };
    }

    /** @param list<array> $entries */
    private function group(string $group): array {
        return array_values(array_filter($this->collect(), static fn(array $e): bool => $e['group'] === $group));
    }

    // ------------------------------------------------------------ rendering

    /** @param list<array> $entries */
    private function renderUrlset(array $entries): string {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $hasImages = $hasVideos = false;
        foreach ($entries as $entry) {
            $hasImages = $hasImages || $entry['images'] !== [];
            $hasVideos = $hasVideos || $entry['videos'] !== [];
        }
        if ($hasImages) $urlset->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        if ($hasVideos) $urlset->setAttribute('xmlns:video', 'http://www.google.com/schemas/sitemap-video/1.1');
        $xml->appendChild($urlset);
        foreach ($entries as $entry) {
            $url = $xml->createElement('url');
            $loc = $xml->createElement('loc');
            $loc->appendChild($xml->createTextNode($this->baseUrl . '/' . ltrim($entry['path'], '/')));
            $url->appendChild($loc);
            $modified = $entry['lastmod'] ? strtotime($entry['lastmod']) : false;
            if ($modified !== false && $modified > 0 && $modified <= time()) {
                $date = $xml->createElement('lastmod');
                $date->appendChild($xml->createTextNode(date('c', $modified)));
                $url->appendChild($date);
            }
            // Google allows at most 1000 images per <url>.
            foreach (array_slice($entry['images'], 0, 1000) as $image) {
                $node = $xml->createElement('image:image');
                $this->textChild($xml, $node, 'image:loc', $image['loc']);
                if (!empty($image['title'])) $this->textChild($xml, $node, 'image:title', (string)$image['title']);
                if (!empty($image['caption'])) $this->textChild($xml, $node, 'image:caption', (string)$image['caption']);
                $url->appendChild($node);
            }
            foreach ($entry['videos'] as $video) {
                $node = $xml->createElement('video:video');
                $this->textChild($xml, $node, 'video:thumbnail_loc', (string)$video['thumbnail_loc']);
                $this->textChild($xml, $node, 'video:title', mb_substr((string)$video['title'], 0, 100));
                $this->textChild($xml, $node, 'video:description', mb_substr((string)$video['description'], 0, 2048));
                if (!empty($video['content_loc'])) $this->textChild($xml, $node, 'video:content_loc', (string)$video['content_loc']);
                if (!empty($video['player_loc'])) {
                    $player = $xml->createElement('video:player_loc');
                    $player->appendChild($xml->createTextNode((string)$video['player_loc']));
                    $node->appendChild($player);
                }
                if (!empty($video['duration'])) $this->textChild($xml, $node, 'video:duration', (string)$video['duration']);
                if (!empty($video['publication_date'])) $this->textChild($xml, $node, 'video:publication_date', (string)$video['publication_date']);
                $this->textChild($xml, $node, 'video:family_friendly', 'yes');
                $this->textChild($xml, $node, 'video:live', 'no');
                $url->appendChild($node);
            }
            $urlset->appendChild($url);
        }
        return $xml->saveXML();
    }

    private function textChild(DOMDocument $xml, DOMElement $parent, string $name, string $value): void {
        $child = $xml->createElement($name);
        $child->appendChild($xml->createTextNode($value));
        $parent->appendChild($child);
    }

    // ------------------------------------------------------------ IndexNow

    /** @return array<string, ?int> URL => lastmod from the previous disk snapshots */
    private function snapshotStamps(): array {
        $stamps = [];
        foreach (['sitemap-pages.xml', 'sitemap-posts.xml', 'sitemap-images.xml', 'sitemap-videos.xml', 'sitemap.xml'] as $name) {
            $file = __DIR__ . '/../' . $name;
            if (!is_file($file)) continue;
            $doc = new DOMDocument();
            if (@$doc->load($file) === false) continue;
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $urls = $xpath->query('//sm:url');
            if ($urls === false) continue;
            foreach ($urls as $urlNode) {
                $loc = $xpath->query('sm:loc', $urlNode)->item(0)?->textContent;
                if ($loc === null || isset($stamps[$loc])) continue;
                $mod = $xpath->query('sm:lastmod', $urlNode)->item(0)?->textContent;
                $stamp = $mod ? strtotime($mod) : false;
                $stamps[$loc] = $stamp !== false ? $stamp : null;
            }
        }
        return $stamps;
    }

    /**
     * Submit appeared/changed/removed URLs to IndexNow (best effort; a slow
     * or unreachable endpoint must never break an admin save).
     *
     * @param array<string, ?int> $previous
     * @param array<string, ?int> $current
     */
    private function submitChanges(array $previous, array $current): void {
        if ($previous === []) return; // first snapshot: nothing to diff against
        $changed = [];
        foreach ($current as $url => $stamp) {
            if (!array_key_exists($url, $previous) || ($stamp !== null && $previous[$url] !== null && $stamp > $previous[$url])
                || ($stamp !== null && $previous[$url] === null)) {
                $changed[] = $url;
            }
        }
        foreach (array_keys($previous) as $url) {
            if (!array_key_exists($url, $current)) $changed[] = $url; // deleted: engines should drop it
        }
        if ($changed === []) return;
        try {
            require_once __DIR__ . '/IndexNow.php';
            IndexNow::submit(array_slice($changed, 0, 10000));
        } catch (Throwable $error) {
            error_log('IndexNow submission failed: ' . $error->getMessage());
        }
    }

    // ------------------------------------------------------------ helpers

    private function absUrl(string $path): string {
        return preg_match('#^https?://#i', $path) ? $path : $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function latestModified(string $query): ?string {
        try {
            $value = $this->db->query($query)->fetchColumn();
        } catch (PDOException $error) {
            // Optional modules create their tables on first use. Other DB failures must surface.
            if (!str_contains($error->getMessage(), 'no such table') && $error->getCode() !== '42S02') throw $error;
            return null;
        }
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Like latestModified(), but a missing optional column is also "unknown". */
    private function latestModifiedTolerant(string $query): ?string {
        try {
            $value = $this->db->query($query)->fetchColumn();
        } catch (PDOException $error) {
            $message = $error->getMessage();
            if (!str_contains($message, 'no such table') && !str_contains($message, 'no such column')
                && $error->getCode() !== '42S02' && $error->getCode() !== '42S22') throw $error;
            return null;
        }
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<array<string, mixed>> rows, or [] when the module/table is absent */
    private function rowsTolerant(string $query): array {
        try {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $error) {
            $message = $error->getMessage();
            if (!str_contains($message, 'no such table') && !str_contains($message, 'no such column')
                && $error->getCode() !== '42S02' && $error->getCode() !== '42S22') throw $error;
            return [];
        }
    }

    private function staticPageModified(string $page): ?string {
        foreach ([$page . '.php', $page . '/index.php', 'legal/' . $page . '.php'] as $candidate) {
            $file = __DIR__ . '/../views/' . $candidate;
            if (is_file($file)) return date('c', filemtime($file));
        }
        return null;
    }
}
