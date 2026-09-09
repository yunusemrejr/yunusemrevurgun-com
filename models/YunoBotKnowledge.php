<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HtmlSanitizer.php';

/** Public publishing snapshot. No chat prompts, users, drafts or admin settings. */
class YunoBotKnowledge {
    public function __construct(private ?PDO $db = null) {
        $this->db ??= Database::getInstance()->getConnection();
    }
    public function snapshot(): array {
        $base = rtrim(FULL_BASE_PATH, '/');
        $rows = $this->db->query("SELECT title, slug, content, updated_at FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
        $urls = $this->db->query("SELECT slug FROM blog_posts WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN);
        $passages = [];
        foreach ($rows as $row) {
            $safe = HtmlSanitizer::clean(mb_substr($row['content'], 0, 40000));
            $blocks = preg_split('#</(?:p|div|li|h[1-6]|blockquote)>|<br\s*/?>#i', $safe);
            foreach ($blocks as $block) {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (mb_strlen($text) < 35) continue;
                // Bounded, complete sentence groups; do not cut in the middle of a word.
                $sentences = preg_split('/(?<=[.!?])\s+(?=[A-ZİÖÜ])/u', $text);
                $chunk = '';
                foreach ($sentences as $sentence) {
                    if ($chunk !== '' && mb_strlen($chunk . $sentence) > 800) {
                        $passages[] = $this->passage($row, $chunk, $base); $chunk = '';
                    }
                    $chunk = trim($chunk . ' ' . $sentence);
                }
                if ($chunk !== '') $passages[] = $this->passage($row, $chunk, $base);
            }
        }
        $publishedUrls = array_map(fn($slug)=>$base.'/blog/'.rawurlencode($slug), $urls);
        $scopes = ['blog'];
        // Select public display fields explicitly; never export cross-post tokens,
        // admin notes, creator IDs, image filenames or unpublished blog content.
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tables = $this->db->query($driver === 'sqlite' ? "SELECT name FROM sqlite_master WHERE type='table'" : 'SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $collections = [
            ['updates', 'updates', 'SELECT id, title, description, update_date FROM updates ORDER BY update_date DESC LIMIT 200'],
            ['portfolio_projects', 'portfolio', 'SELECT id, title, description, technologies FROM portfolio_projects ORDER BY id DESC LIMIT 200'],
            ['travel_locations', 'travel', 'SELECT id, country, city, visited FROM travel_locations ORDER BY id DESC LIMIT 300'],
            ['gallery_images', 'gallery', 'SELECT id, title FROM gallery_images WHERE is_archived = 0 ORDER BY id DESC LIMIT 300'],
        ];
        foreach ($collections as [$table, $page, $query]) {
            if (!in_array($table, $tables, true)) continue;
            $scopes[] = $page;
            foreach ($this->db->query($query)->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $title = $page === 'travel' ? $item['city'] . ', ' . $item['country'] : trim((string)$item['title']);
                $url = $base . ($page === 'updates' ? '/updates/' . (int)$item['id'] : '/' . $page);
                $publishedUrls[] = $url;
                $text = $title;
                if ($page === 'updates') $text .= ' — ' . $item['update_date'] . '. ' . $item['description'];
                if ($page === 'portfolio') $text .= '. ' . $item['description'] . ' Technologies: ' . $item['technologies'];
                if ($page === 'travel') $text = 'Travel entry: ' . $text . (!empty($item['visited']) ? '. Visit date: ' . $item['visited'] : '');
                if ($page === 'gallery') $text = 'Public gallery image titled “' . $title . '”. Image contents are not automatically analysed.';
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(HtmlSanitizer::clean(mb_substr($text, 0, 12000))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($title !== '' && $text !== '') $passages[] = ['page'=>$page,'url'=>$url,'title'=>$title,'heading'=>$title,'text'=>$text];
            }
        }
        return ['version'=>2, 'scope'=>'blog', 'scopes'=>$scopes, 'publishedUrls'=>array_values(array_unique($publishedUrls)), 'passages'=>$passages];
    }
    private function passage(array $row, string $text, string $base): array {
        return ['page'=>'blog','url'=>$base.'/blog/'.rawurlencode($row['slug']),'title'=>$row['title'],'heading'=>$row['title'],'text'=>$text];
    }
}
