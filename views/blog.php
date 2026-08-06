<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Blog.php';
require_once __DIR__ . '/includes/ui.php';

$blog = new Blog();
$postsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $postsPerPage;
$posts = $blog->getPosts($offset, $postsPerPage);
$totalPosts = $blog->getTotalPublishedPosts();
$totalPages = max(1, (int) ceil($totalPosts / $postsPerPage));
$currentPage = min($currentPage, $totalPages);

$pageMeta = [];
if ($totalPages > 1) {
    if ($currentPage > 1) {
        $pageMeta[] = '<link rel="prev" href="' . FULL_BASE_PATH . 'blog?page=' . ($currentPage - 1) . '">';
    }
    if ($currentPage < $totalPages) {
        $pageMeta[] = '<link rel="next" href="' . FULL_BASE_PATH . 'blog?page=' . ($currentPage + 1) . '">';
    }
}

ui_render_head(
    'Blog — Journal | Yunus Emre Vurgun, Developer',
    'Long-form notes, architecture logs, and technical writing by Yunus Emre Vurgun (Yemre, YEV).',
    $pageMeta
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Journal'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Journal</p>
            <h1 class="ui-section-title">Blog</h1>
            <p class="ui-section-text">Long-form notes, architecture logs, and technical writing.</p>
        </section>

        <section class="ui-section">
            <form class="ui-search-form" action="<?= FULL_BASE_PATH ?>search" method="get">
                <input class="ui-input" type="text" name="q" placeholder="Search blog posts..." required>
                <input type="hidden" name="type" value="blog">
                <button class="ui-btn ui-btn-secondary" type="submit">Search</button>
            </form>

            <?php if (empty($posts)): ?>
                <div class="ui-empty">No blog posts available yet.</div>
            <?php else: ?>
                <div class="ui-feed-list">
                    <?php foreach ($posts as $post):
                        $excerpt = !empty($post['excerpt']) ? $post['excerpt'] : strip_tags((string) $post['content']);
                        $excerpt = mb_substr(trim($excerpt), 0, 220) . (mb_strlen($excerpt) > 220 ? '...' : '');
                        ?>
                        <article class="ui-feed-card">
                            <a href="<?= FULL_BASE_PATH . 'blog/' . urlencode($post['slug']) ?>" style="display: block;">
                                <h2 class="ui-feed-title"><?= htmlspecialchars($post['title']) ?></h2>
                                <p class="ui-feed-meta"><?= date('F j, Y', strtotime($post['created_at'] ?? 'now')) ?></p>
                                <p class="ui-feed-text"><?= htmlspecialchars($excerpt) ?></p>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="ui-pagination" aria-label="Blog pagination">
                        <?php if ($currentPage > 1): ?>
                            <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>blog?page=<?= $currentPage - 1 ?>">Previous</a>
                        <?php else: ?>
                            <span class="ui-page-link is-disabled">Previous</span>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="ui-page-link<?= $i === $currentPage ? ' is-active' : '' ?>" href="<?= FULL_BASE_PATH ?>blog?page=<?= $i ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a class="ui-page-link" href="<?= FULL_BASE_PATH ?>blog?page=<?= $currentPage + 1 ?>">Next</a>
                        <?php else: ?>
                            <span class="ui-page-link is-disabled">Next</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
