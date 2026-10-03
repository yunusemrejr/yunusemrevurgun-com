<?php
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Sitemap {
    private PDO $db;
    private string $baseUrl;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->baseUrl = rtrim(FULL_BASE_PATH, '/');
    }

    /** Render from published content; serving the sitemap never writes files. */
    public function renderXML(): string {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->appendChild($urlset);
        // Listing pages change when their content does, not when their template does,
        // so their lastmod is the newest row they list (null when there is none).
        $latest = [
            'blog' => $this->latestModified("SELECT MAX(COALESCE(updated_at, created_at)) FROM blog_posts WHERE status = 'published'"),
            'updates' => $this->latestModified('SELECT MAX(COALESCE(updated_at, created_at)) FROM updates'),
            'rmrp' => $this->latestModified('SELECT MAX(COALESCE(updated_at, created_at)) FROM rmrp_memories'),
            'slop' => $this->latestModified('SELECT MAX(COALESCE(updated_at, created_at)) FROM slop_pages'),
        ];
        $latest['downloads'] = date('c', (int)max(@filemtime(__DIR__ . '/../includes/downloads_catalog.php'), @filemtime(__DIR__ . '/../views/downloads/index.php')));
        $homeStamps = array_filter([
            $latest['blog'] ? strtotime($latest['blog']) : false,
            $latest['updates'] ? strtotime($latest['updates']) : false,
            @filemtime(__DIR__ . '/../views/home.php'),
        ]);
        $latest[''] = $homeStamps ? date('c', max($homeStamps)) : null;

        $staticPages = ['', 'about', 'portfolio', 'blog', 'gallery', 'travel', 'updates', 'rmrp', 'contact', 'music', 'videos', 'downloads', 'slop', 'yunobot', 'gemmaclaim', 'chessko', 'chessko/how-it-works', 'chessko/search-and-evaluation', 'chessko/machine-learning', 'chessko/stockfish-webassembly', 'chessko/difficulty-levels', 'jelloshop', 'post-code', 'science-corner', 'comedy', 'more', 'sitemap', 'privacy', 'terms', 'cookies'];
        foreach ($staticPages as $page) {
            // Listing pages depend on database content, not the template timestamp.
            $modified = $latest[$page] ?? (in_array($page, ['portfolio', 'gallery', 'travel', 'music', 'videos', 'more'], true)
                ? null : $this->staticPageModified($page));
            $this->addUrl($xml, $urlset, $page, $modified);
        }
        // Documentation pages under the project hubs (views/docs/pages.php); each
        // carries its own 'modified' date.
        foreach ((require __DIR__ . '/../views/docs/pages.php')['pages'] as $docPath => $doc) {
            $this->addUrl($xml, $urlset, $docPath, $doc['modified'] ?? null);
        }
        foreach ([
            ['blog', "SELECT slug AS path, COALESCE(updated_at, created_at) AS lastmod FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC"],
            ['updates', 'SELECT id AS path, COALESCE(updated_at, created_at) AS lastmod FROM updates ORDER BY created_at DESC'],
            ['rmrp', 'SELECT id AS path, COALESCE(updated_at, created_at) AS lastmod FROM rmrp_memories ORDER BY created_at DESC'],
            ['slop', 'SELECT slug AS path, COALESCE(updated_at, created_at) AS lastmod FROM slop_pages ORDER BY created_at DESC'],
        ] as [$section, $query]) {
            try {
                $statement = $this->db->query($query);
            } catch (PDOException $error) {
                // Optional modules create their tables on first use. Other DB failures must surface.
                if (!str_contains($error->getMessage(), 'no such table') && $error->getCode() !== '42S02') throw $error;
                continue;
            }
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                if ((string)$row['path'] === '') continue;
                $this->addUrl($xml, $urlset, $section . '/' . rawurlencode((string)$row['path']), $row['lastmod']);
            }
        }
        return $xml->saveXML();
    }

    /** Compatibility snapshot for existing publishing hooks; atomic, production-only. */
    public function generateSitemapXML(): bool {
        $content = $this->renderXML();
        if (getenv('MODE') === 'development' || PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') return true;
        foreach ([__DIR__ . '/../sitemap.xml', __DIR__ . '/../public/sitemap.xml'] as $path) {
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
        return true;
    }

    public function forceRegenerate(): bool {
        try { return $this->generateSitemapXML(); }
        catch (Throwable $error) { error_log('Sitemap snapshot failed: ' . $error->getMessage()); return false; }
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

    private function staticPageModified(string $page): ?string {
        foreach ([$page . '.php', $page . '/index.php', 'legal/' . $page . '.php'] as $candidate) {
            $file = __DIR__ . '/../views/' . $candidate;
            if (is_file($file)) return date('c', filemtime($file));
        }
        return null;
    }

    private function addUrl(DOMDocument $xml, DOMElement $urlset, string $path, ?string $lastmod = null): void {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc');
        $loc->appendChild($xml->createTextNode($this->baseUrl . '/' . ltrim($path, '/')));
        $url->appendChild($loc);
        $modified = $lastmod ? strtotime($lastmod) : false;
        if ($modified !== false && $modified > 0 && $modified <= time()) {
            $date = $xml->createElement('lastmod');
            $date->appendChild($xml->createTextNode(date('c', $modified)));
            $url->appendChild($date);
        }
        $urlset->appendChild($url);
    }
}
