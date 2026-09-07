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
        return ['version'=>2, 'scope'=>'blog', 'publishedUrls'=>array_map(fn($slug)=>$base.'/blog/'.rawurlencode($slug),$urls), 'passages'=>$passages];
    }
    private function passage(array $row, string $text, string $base): array {
        return ['page'=>'blog','url'=>$base.'/blog/'.rawurlencode($row['slug']),'title'=>$row['title'],'heading'=>$row['title'],'text'=>$text];
    }
}
