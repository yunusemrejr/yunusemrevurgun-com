<?php
/**
 * Socials model
 *
 * Site-wide social profile links, editable from /admin/socials. This is the
 * single source of truth for every place a social link can appear:
 *   - site footer (ui_render_footer)   — entries with visibility 'footer'|'both'
 *   - contact page                     — entries with visibility 'contact'|'both'
 *   - /more "Socials" section          — every active link-kind entry
 *   - home JSON-LD Person.sameAs       — every active link-kind entry
 * The landing footer intentionally shows no socials (site design decision).
 *
 * `kind` is 'link' (plain anchor) or 'popup' (footer-only button that opens the
 * X/Twitter joke dialog; the dialog markup lives in ui.php and is emitted only
 * when an active popup-kind entry is footer-visible). Popup entries carry no
 * URL and are skipped by the contact page and /more listing.
 */
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class Socials {
    private $db;

    /** Seed runs once per PHP process; footer renders on many pages. */
    private static $seeded = false;

    /** Per-request cache for the location-filtered queries (footer is rendered once per page). */
    private static $listCache = [];

    const KIND_LINK = 'link';
    const KIND_POPUP = 'popup';

    const VIS_FOOTER = 'footer';
    const VIS_CONTACT = 'contact';
    const VIS_BOTH = 'both';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
        $this->seedDefaultsIfEmpty();
    }

    private function createTableIfNotExists() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS socials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                url TEXT,
                handle TEXT,
                icon TEXT NOT NULL DEFAULT 'link',
                kind TEXT NOT NULL DEFAULT 'link',
                visibility TEXT NOT NULL DEFAULT 'both',
                active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS socials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                url VARCHAR(500),
                handle VARCHAR(150),
                icon VARCHAR(30) NOT NULL DEFAULT 'link',
                kind ENUM('link', 'popup') NOT NULL DEFAULT 'link',
                visibility ENUM('footer', 'contact', 'both') NOT NULL DEFAULT 'both',
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
        }

        $this->db->exec($query);
    }

    /**
     * First-run defaults mirror the pre-database hardcoded socials so a fresh
     * table (dev SQLite, prod MySQL) renders identically to the old markup.
     * Mastodon is footer-only and X is a popup joke button — exactly as before.
     */
    private function seedDefaultsIfEmpty() {
        if (self::$seeded) return;
        self::$seeded = true;

        $count = (int) $this->db->query('SELECT COUNT(*) FROM socials')->fetchColumn();
        if ($count > 0) return;

        $defaults = [
            ['GitHub',       'https://github.com/yunusemrejr',                     '@yunusemrejr',       'github',    self::KIND_LINK,  self::VIS_BOTH,    10],
            ['YouTube',      'https://www.youtube.com/@yunusemrevurgun1',           '@yunusemrevurgun1',  'youtube',   self::KIND_LINK,  self::VIS_BOTH,    20],
            ['LinkedIn',     'https://linkedin.com/in/yunus-emre-vurgun-49ba9a177','Profile',            'linkedin',  self::KIND_LINK,  self::VIS_BOTH,    30],
            ['Mastodon',     'https://mastodon.social/@yunusemrevurgn',             '@yunusemrevurgn',    'mastodon',  self::KIND_LINK,  self::VIS_FOOTER,  40],
            ['Bluesky',      'https://bsky.app/profile/yunusemrevurgun.bsky.social','@yunusemrevurgun',   'bluesky',   self::KIND_LINK,  self::VIS_BOTH,    50],
            ['Threads',      'https://www.threads.com/@yemrevu',                    '@yemrevu',           'threads',   self::KIND_LINK,  self::VIS_BOTH,    60],
            ['Instagram',    'https://instagram.com/yemrevu',                       '@yemrevu',           'instagram', self::KIND_LINK,  self::VIS_BOTH,    70],
            ['X / Twitter',  '',                                                    '',                   'x',         self::KIND_POPUP, self::VIS_FOOTER,  80],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO socials (name, url, handle, icon, kind, visibility, active, sort_order)
             VALUES (:name, :url, :handle, :icon, :kind, :visibility, 1, :sort_order)'
        );
        foreach ($defaults as $row) {
            $stmt->bindValue(':name', $row[0], PDO::PARAM_STR);
            $stmt->bindValue(':url', $row[1], PDO::PARAM_STR);
            $stmt->bindValue(':handle', $row[2], PDO::PARAM_STR);
            $stmt->bindValue(':icon', $row[3], PDO::PARAM_STR);
            $stmt->bindValue(':kind', $row[4], PDO::PARAM_STR);
            $stmt->bindValue(':visibility', $row[5], PDO::PARAM_STR);
            $stmt->bindValue(':sort_order', $row[6], PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    /** All rows (admin listing), ordered by sort_order then id. */
    public function getAll() {
        $stmt = $this->db->query('SELECT * FROM socials ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->db->prepare('SELECT * FROM socials WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Every active entry (used by /more and home JSON-LD). */
    public function getActive() {
        $stmt = $this->db->query('SELECT * FROM socials WHERE active = 1 ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active entries for one placement: 'footer' or 'contact'.
     * 'both' entries appear in every placement; popup-kind entries are only
     * meaningful in the footer and are returned there too (callers that need
     * plain links filter kind themselves).
     */
    public function getActiveByLocation($location) {
        $location = ($location === self::VIS_CONTACT) ? self::VIS_CONTACT : self::VIS_FOOTER;
        if (isset(self::$listCache[$location])) {
            return self::$listCache[$location];
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM socials
             WHERE active = 1 AND (visibility = :loc OR visibility = :both)
             ORDER BY sort_order ASC, id ASC"
        );
        $stmt->bindValue(':loc', $location, PDO::PARAM_STR);
        $stmt->bindValue(':both', self::VIS_BOTH, PDO::PARAM_STR);
        $stmt->execute();
        self::$listCache[$location] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return self::$listCache[$location];
    }

    /** Active link-kind entries (plain anchors) — /more + JSON-LD sameAs. */
    public function getActiveLinks() {
        $stmt = $this->db->query(
            "SELECT * FROM socials WHERE active = 1 AND kind = '" . self::KIND_LINK . "'
             ORDER BY sort_order ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($data) {
        requireAdminSession(true);
        $stmt = $this->db->prepare(
            'INSERT INTO socials (name, url, handle, icon, kind, visibility, active, sort_order)
             VALUES (:name, :url, :handle, :icon, :kind, :visibility, :active, :sort_order)'
        );
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':url', $data['url'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':handle', $data['handle'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':icon', $data['icon'] ?? 'link', PDO::PARAM_STR);
        $stmt->bindValue(':kind', $data['kind'] ?? self::KIND_LINK, PDO::PARAM_STR);
        $stmt->bindValue(':visibility', $data['visibility'] ?? self::VIS_BOTH, PDO::PARAM_STR);
        $stmt->bindValue(':active', $data['active'] ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', $data['sort_order'] ?? 0, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function update($id, $data) {
        requireAdminSession(true);
        $allowed = ['name', 'url', 'handle', 'icon', 'kind', 'visibility', 'active', 'sort_order'];
        $fields = [];
        $params = [':id' => $id];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $stmt = $this->db->prepare('UPDATE socials SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public function delete($id) {
        requireAdminSession(true);
        $stmt = $this->db->prepare('DELETE FROM socials WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    public function getTotal() {
        return (int) $this->db->query('SELECT COUNT(*) FROM socials')->fetchColumn();
    }

    public static function allowedIcons(): array {
        return ['github', 'youtube', 'linkedin', 'mastodon', 'bluesky', 'threads', 'instagram', 'x', 'link'];
    }

    public static function allowedKinds(): array {
        return [self::KIND_LINK, self::KIND_POPUP];
    }

    public static function allowedVisibilities(): array {
        return [self::VIS_FOOTER, self::VIS_CONTACT, self::VIS_BOTH];
    }

    /**
     * Icon catalog: brand glyph paths (same artwork the footer used when these
     * were hardcoded). Returns a ready-to-echo <svg> element.
     */
    public static function iconSvg(string $key, string $class = ''): string {
        $icons = self::icons();
        if (!isset($icons[$key])) $key = 'link';
        [$viewBox, $inner] = $icons[$key];
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
        return '<svg' . $classAttr . ' viewBox="' . $viewBox . '" fill="currentColor" aria-hidden="true">' . $inner . '</svg>';
    }

    public static function icons(): array {
        return [
            'github' => ['0 0 24 24', '<path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.399s2.046.133 3.003.399c2.293-1.552 3.301-1.23 3.301-1.23.652 1.653.241 2.873.117 3.176.77.84 1.236 1.91 1.236 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>'],
            'youtube' => ['0 0 24 24', '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>'],
            'linkedin' => ['0 0 24 24', '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>'],
            'mastodon' => ['0 0 24 24', '<path d="M23.268 5.313c-.35-2.578-2.617-4.61-5.304-5.004C17.51.242 15.792 0 11.813 0h-.03c-3.98 0-4.835.242-5.288.309C3.882.692 1.496 2.518.917 5.127.64 6.412.61 7.837.661 9.143c.074 1.874.088 3.745.26 5.611.118 1.24.325 2.47.62 3.68.55 2.237 2.777 4.098 4.96 4.857 2.336.792 4.849.923 7.256.38.265-.061.527-.132.786-.213.585-.184 1.27-.39 1.774-.753a.057.057 0 0 0 .023-.043v-1.809a.052.052 0 0 0-.02-.041.053.053 0 0 0-.046-.01 20.282 20.282 0 0 1-4.709.545c-2.73 0-3.463-1.284-3.674-1.818a5.593 5.593 0 0 1-.319-1.433.053.053 0 0 1 .066-.054c1.517.363 3.072.546 4.632.546.376 0 .75 0 1.125-.01 1.57-.044 3.224-.124 4.768-.422.038-.008.077-.015.11-.024 2.435-.464 4.753-1.92 4.989-5.604.008-.145.03-1.52.03-1.67.002-.512.167-3.63-.024-5.545zm-3.748 9.195h-2.561V8.29c0-1.309-.55-1.976-1.67-1.976-1.23 0-1.846.79-1.846 2.35v3.403h-2.546V8.663c0-1.56-.617-2.35-1.848-2.35-1.112 0-1.668.668-1.67 1.977v6.218H4.822V8.102c0-1.31.337-2.35 1.011-3.12.696-.77 1.608-1.164 2.74-1.164 1.311 0 2.302.5 2.962 1.498l.638 1.06.638-1.06c.66-.999 1.65-1.498 2.96-1.498 1.13 0 2.043.395 2.74 1.164.675.77 1.012 1.81 1.012 3.12z"/>'],
            'bluesky' => ['0 0 16 16', '<path d="M3.468 1.948C5.303 3.325 7.276 6.118 8 7.616c.725-1.498 2.698-4.29 4.532-5.668C13.855.955 16 .186 16 2.632c0 .489-.28 4.105-.444 4.692-.572 2.04-2.653 2.561-4.504 2.246 3.236.551 4.06 2.375 2.281 4.2-3.376 3.464-4.852-.87-5.23-1.98-.07-.204-.103-.3-.103-.218 0-.081-.033.014-.102.218-.379 1.11-1.855 5.444-5.231 1.98-1.778-1.825-.955-3.65 2.28-4.2-1.85.315-3.932-.205-4.503-2.246C.28 6.737 0 3.12 0 2.632 0 .186 2.145.955 3.468 1.948"/>'],
            'threads' => ['0 0 24 24', '<path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.75-1.757-.513-.586-1.308-.883-2.359-.89h-.029c-.844 0-1.992.232-2.721 1.32L7.734 7.847c.98-1.454 2.568-2.256 4.478-2.256h.044c3.194.02 5.097 1.975 5.287 5.388.108.046.216.094.321.142 1.49.7 2.58 1.761 3.154 3.07.797 1.82.871 4.79-1.548 7.158-1.85 1.81-4.094 2.628-7.277 2.65Zm1.003-11.69c-.242 0-.487.007-.739.021-1.836.103-2.98.946-2.916 2.143.067 1.256 1.452 1.839 2.784 1.767 1.224-.065 2.818-.543 3.086-3.71a10.5 10.5 0 0 0-2.215-.221z"/>'],
            'instagram' => ['0 0 24 24', '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>'],
            'x' => ['0 0 24 24', '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>'],
            'link' => ['0 0 24 24', '<path d="M10.59 13.41c.41.39.41 1.03 0 1.42-.39.39-1.03.39-1.42 0a5.003 5.003 0 0 1 0-7.07l3.54-3.54a5.003 5.003 0 0 1 7.07 0 5.003 5.003 0 0 1 0 7.07l-1.49 1.49c.01-.82-.12-1.64-.4-2.42l.47-.48a2.982 2.982 0 0 0 0-4.24 2.982 2.982 0 0 0-4.24 0l-3.53 3.53a2.982 2.982 0 0 0 0 4.24zm2.82-4.24c-.41-.39-.41-1.03 0-1.42.39-.39 1.03-.39 1.42 0a5.003 5.003 0 0 1 0 7.07l-3.54 3.54a5.003 5.003 0 0 1-7.07 0 5.003 5.003 0 0 1 0-7.07l1.49-1.49c-.01.82.12 1.64.4 2.43l-.47.47a2.982 2.982 0 0 0 0 4.24 2.982 2.982 0 0 0 4.24 0l3.53-3.53a2.982 2.982 0 0 0 0-4.24z"/>'],
        ];
    }
}
