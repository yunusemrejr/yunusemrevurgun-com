<?php
/**
 * Quality 3D AI Slop model.
 *
 * Single-file HTML pages (3D experiments) uploaded by the admin and served to
 * the public at /slop/<slug>. Each row owns one file in uploads/slop/; the
 * public single view injects SEO metadata plus a small "go home" button into
 * the stored markup at serve time, so uploads stay byte-identical on disk.
 */
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Slop {
    private $db;

    /** Directory (relative to project root) where slop HTML files are stored. */
    const UPLOAD_DIR = 'uploads/slop';

    /** Tracked template the admin panel can copy into a deletable test entry. */
    const EXAMPLE_FILE = '_example-spinning-cube.html';

    /** Uploaded single-file pages stay small; 3D libraries load from CDN. */
    const MAX_FILE_SIZE = 5 * 1024 * 1024;

    const SECTION_PATH = 'slop';
    const SECTION_TITLE = 'Quality 3D AI Slop';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS slop_pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL DEFAULT 'Untitled',
                slug TEXT NOT NULL UNIQUE,
                description TEXT,
                filename TEXT NOT NULL,
                original_filename TEXT,
                file_size INT DEFAULT 0,
                created_by INT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT NULL,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS slop_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL DEFAULT 'Untitled',
                slug VARCHAR(255) NOT NULL UNIQUE,
                description TEXT,
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255),
                file_size INT DEFAULT 0,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                UNIQUE KEY uq_slop_slug (slug)
            )";
        }
        $this->db->exec($query);
    }

    public function getAllSlops() {
        $stmt = $this->db->query("SELECT * FROM slop_pages ORDER BY created_at DESC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSlopById($id) {
        $stmt = $this->db->prepare("SELECT * FROM slop_pages WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSlopBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM slop_pages WHERE slug = :slug");
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addSlop($data) {
        requireAdminSession(false);
        $title = mb_substr(trim((string)($data['title'] ?? '')), 0, 255);
        if ($title === '') {
            throw new Exception('Title is required.');
        }
        $slug = $this->createSlug($data['slug'] ?? $title);
        $query = "INSERT INTO slop_pages (title, slug, description, filename, original_filename, file_size, created_by)
                  VALUES (:title, :slug, :description, :filename, :original_filename, :file_size, :created_by)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':description', mb_substr(trim((string)($data['description'] ?? '')), 0, 2000), PDO::PARAM_STR);
        $stmt->bindValue(':filename', $data['filename'], PDO::PARAM_STR);
        $stmt->bindValue(':original_filename', $data['original_filename'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':file_size', $data['file_size'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return false;
        }
        return (int)$this->db->lastInsertId();
    }

    /**
     * Retitle/re-describe an entry. The slug is intentionally immutable: it is
     * the public URL, already indexed and possibly linked from elsewhere.
     */
    public function updateSlop($id, $data) {
        requireAdminSession(false);
        $allowed = ['title', 'description'];
        $fields = [];
        $params = [':id' => $id];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = $field === 'title'
                    ? mb_substr(trim((string)$data[$field]), 0, 255)
                    : mb_substr(trim((string)$data[$field]), 0, 2000);
                if ($field === 'title' && $value === '') {
                    throw new Exception('Title is required.');
                }
                $fields[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }
        if (empty($fields)) return false;
        $stmt = $this->db->prepare("UPDATE slop_pages SET " . implode(', ', $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteSlop($id) {
        requireAdminSession(false);
        $slop = $this->getSlopById($id);
        if (!$slop) return false;

        $stmt = $this->db->prepare("DELETE FROM slop_pages WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result && !empty($slop['filename'])) {
            require_once __DIR__ . '/../includes/upload_files.php';
            removeUploadFile(dirname(__DIR__) . '/' . self::UPLOAD_DIR, $slop['filename']);
        }
        return $result;
    }

    public function getTotalSlops() {
        return (int)$this->db->query("SELECT COUNT(*) FROM slop_pages")->fetchColumn();
    }

    public function slugExists($slug, $excludeId = null) {
        $query = "SELECT COUNT(*) FROM slop_pages WHERE slug = :slug";
        $params = [':slug' => $slug];
        if ($excludeId !== null) {
            $query .= " AND id != :id";
            $params[':id'] = $excludeId;
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /** Slug from a title; conflicts resolve to title-1, title-2, ... */
    public function createSlug($text) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$text);
        $slug = strtolower(is_string($ascii) ? $ascii : (string)$text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'slop-' . time();
        }
        if (!$this->slugExists($slug)) {
            return $slug;
        }
        $i = 1;
        do {
            $candidate = $slug . '-' . $i;
            $i++;
        } while ($this->slugExists($candidate));
        return $candidate;
    }

    /** Absolute filesystem path of a stored file; false when it escapes the upload dir. */
    public static function storedFilePath(string $filename) {
        if ($filename === '' || $filename !== basename($filename)) return false;
        $root = realpath(dirname(__DIR__) . '/' . self::UPLOAD_DIR);
        if ($root === false) return false;
        $path = $root . DIRECTORY_SEPARATOR . $filename;
        if (is_link($path) || !is_file($path)) return false;
        $real = realpath($path);
        if ($real === false || dirname($real) !== $root) return false;
        return $real;
    }

    /**
     * Copy the tracked example template into a uniquely-named stored file and
     * register it as a normal entry the owner can later delete from the panel.
     */
    public function seedExample() {
        requireAdminSession(false);
        $template = dirname(__DIR__) . '/' . self::UPLOAD_DIR . '/' . self::EXAMPLE_FILE;
        if (!is_file($template)) {
            throw new Exception('Example template is missing.');
        }
        $dir = dirname(__DIR__) . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new Exception('Upload directory is not writable.');
        }
        $filename = bin2hex(random_bytes(8)) . '-test-3d-example.html';
        if (!copy($template, $dir . '/' . $filename)) {
            throw new Exception('Could not copy the example file.');
        }
        chmod($dir . '/' . $filename, 0644);
        return $this->addSlop([
            'title' => 'Test 3D Example',
            'description' => 'Test entry for the Quality 3D AI Slop section. Safe to delete from the admin panel.',
            'filename' => $filename,
            'original_filename' => self::EXAMPLE_FILE,
            'file_size' => filesize($dir . '/' . $filename),
        ]);
    }

    /**
     * Inject SEO metadata into <head> (only tags the upload lacks) and the
     * go-home button script before </body>. The stored file is never modified.
     */
    public static function injectSeoAndHomeButton(string $html, array $slop, string $canonical): string {
        $title = trim((string)($slop['title'] ?? '')) !== '' ? trim((string)$slop['title']) : 'Untitled';
        $description = trim((string)($slop['description'] ?? ''));
        if ($description === '') {
            $description = $title . ' — a single-file 3D experiment in the Quality 3D AI Slop collection by Yunus Emre Vurgun.';
        }
        $pageTitle = $title . ' | Quality 3D AI Slop | Yunus Emre Vurgun';
        $escTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
        $escDesc = htmlspecialchars(mb_substr($description, 0, 300), ENT_QUOTES, 'UTF-8');
        $escCanonical = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
        $homeUrl = htmlspecialchars(rtrim(FULL_BASE_PATH, '/') . '/', ENT_QUOTES, 'UTF-8');

        $headTags = '';
        if (!preg_match('/<title[\s>]/i', $html)) {
            $headTags .= "<title>{$escTitle}</title>\n";
        }
        if (!preg_match('/<meta\s+[^>]*name=["\']description["\']/i', $html)) {
            $headTags .= "<meta name=\"description\" content=\"{$escDesc}\">\n";
        }
        if (!preg_match('/<link\s+[^>]*rel=["\']canonical["\']/i', $html)) {
            $headTags .= "<link rel=\"canonical\" href=\"{$escCanonical}\">\n";
        }
        if (!preg_match('/<meta\s+[^>]*name=["\']robots["\']/i', $html)) {
            $headTags .= "<meta name=\"robots\" content=\"index,follow,max-image-preview:large\">\n";
        }
        if (!preg_match('/<meta\s+[^>]*property=["\']og:title["\']/i', $html)) {
            $headTags .= "<meta property=\"og:type\" content=\"website\">\n"
                . "<meta property=\"og:url\" content=\"{$escCanonical}\">\n"
                . "<meta property=\"og:title\" content=\"{$escTitle}\">\n"
                . "<meta property=\"og:description\" content=\"{$escDesc}\">\n"
                . "<meta name=\"twitter:card\" content=\"summary\">\n"
                . "<meta name=\"twitter:title\" content=\"{$escTitle}\">\n"
                . "<meta name=\"twitter:description\" content=\"{$escDesc}\">\n";
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CreativeWork',
                    'name' => $title,
                    'description' => mb_substr($description, 0, 300),
                    'url' => $canonical,
                    'author' => ['@type' => 'Person', 'name' => 'Yunus Emre Vurgun', 'url' => rtrim(FULL_BASE_PATH, '/') . '/'],
                    'isPartOf' => ['@type' => 'CollectionPage', 'name' => self::SECTION_TITLE, 'url' => rtrim(FULL_BASE_PATH, '/') . '/' . self::SECTION_PATH],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim(FULL_BASE_PATH, '/') . '/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => self::SECTION_TITLE, 'item' => rtrim(FULL_BASE_PATH, '/') . '/' . self::SECTION_PATH],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonical],
                    ],
                ],
            ],
        ];
        $headTags .= '<script type="application/ld+json">'
            . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
            . "</script>\n";

        if ($headTags !== '') {
            $block = "<!-- yev-slop-seo -->\n" . $headTags;
            if (preg_match('/<head[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $html = substr($html, 0, $pos) . "\n" . $block . substr($html, $pos);
            } else {
                $html = $block . $html;
            }
        }

        $button = <<<'HTML'
<!-- yev-slop-home -->
<script>
(function () {
    if (document.getElementById('yev-slop-home')) return;
    var link = document.createElement('a');
    link.id = 'yev-slop-home';
    link.href = '__HOME__';
    link.textContent = '\\u2190 Home';
    link.setAttribute('aria-label', 'Go to the home page');
    link.style.cssText = 'position:fixed;left:12px;bottom:12px;z-index:2147483647;'
        + 'font:600 13px/1 system-ui,-apple-system,"Segoe UI",sans-serif;'
        + 'color:#f5efe4;background:rgba(21,18,15,.82);text-decoration:none;'
        + 'padding:8px 12px;border-radius:999px;border:1px solid rgba(245,239,228,.25);'
        + 'opacity:.75;transition:opacity .15s ease;';
    link.addEventListener('mouseenter', function () { link.style.opacity = '1'; });
    link.addEventListener('mouseleave', function () { link.style.opacity = '.75'; });
    link.addEventListener('focus', function () { link.style.opacity = '1'; });
    link.addEventListener('blur', function () { link.style.opacity = '.75'; });
    (document.body || document.documentElement).appendChild(link);
})();
</script>
<noscript><style>#yev-slop-home-fallback{display:block !important;}</style></noscript>
<a id="yev-slop-home-fallback" href="__HOME__" style="display:none;position:fixed;left:12px;bottom:12px;z-index:2147483647;font:600 13px/1 system-ui,sans-serif;color:#f5efe4;background:rgba(21,18,15,.82);text-decoration:none;padding:8px 12px;border-radius:999px;border:1px solid rgba(245,239,228,.25);">&larr; Home</a>
HTML;
        $button = str_replace('__HOME__', $homeUrl, $button);
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body\s*>/i', $button . "\n</body>", $html, 1);
        } else {
            $html .= "\n" . $button;
        }
        return $html;
    }
}
