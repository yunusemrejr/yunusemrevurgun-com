<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/collection.php';
require_once dirname(__DIR__, 2) . '/models/Videos.php';

$videos = new Videos();
$videoList = $videos->getAllVideos(false);

ui_render_head(
    'Videos | Yunus Emre Vurgun',
    'Video collection — uploads, YouTube embeds, and Odysee clips.',
    [ui_collection_schema('Videos', 'videos', array_column($videoList, 'title'), array_map(fn($item) => 'video-' . $item['id'], $videoList))]
);
?>
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('videos'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Video</p>
            <h1 class="ui-section-title">Videos</h1>
            <p class="ui-section-text">A collection of videos — uploaded clips, YouTube embeds, and Odysee content.</p>
        </section>

        <section class="ui-section">
            <?php if (count($videoList) > 0): ?>
                <div class="ui-videos-grid" id="videosGrid">
                    <?php foreach ($videoList as $video): 
                        $duration = $video['duration'] > 0 ? gmdate('i:s', intval($video['duration'])) : '';
                    ?>
                        <div class="ui-video-card" id="video-<?= (int)$video['id'] ?>" data-video-id="<?= $video['id'] ?>">
                            <?php if ($video['type'] === 'url'): ?>
                                <?php 
                                    $embedUrl = '';
                                    $platform = $video['platform'] ?? '';
                                    if ($platform === 'youtube' && !empty($video['video_id'])) {
                                        $embedUrl = 'https://www.youtube-nocookie.com/embed/' . urlencode($video['video_id']) . '?rel=0';
                                    } elseif ($platform === 'odysee' && !empty($video['video_id'])) {
                                        $embedUrl = 'https://odysee.com/$/embed/' . ltrim($video['video_id'], '/');
                                    } elseif (!empty($video['url'])) {
                                        $embedUrl = $video['url'];
                                    }
                                ?>
                                <div class="ui-video-embed-container">
                                    <div class="ui-video-embed" data-embed-url="<?= htmlspecialchars($embedUrl) ?>">
                                        <?php if (!empty($video['thumbnail_url'])): ?>
                                            <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="" class="ui-video-thumb" loading="lazy">
                                        <?php else: ?>
                                            <div class="ui-video-placeholder">
                                                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor" opacity="0.4"><path d="M8 5v14l11-7z"/></svg>
                                            </div>
                                        <?php endif; ?>
                                        <button class="ui-video-play-btn" type="button" aria-label="Play video">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php 
                                    $videoPath = FULL_BASE_PATH . 'uploads/videos/' . htmlspecialchars($video['filename']);
                                ?>
                                <div class="ui-video-embed-container">
                                    <video class="ui-video-player" controls preload="metadata" playsinline>
                                        <source src="<?= $videoPath ?>" type="video/mp4">
                                        <p>Your browser does not support HTML5 video.</p>
                                    </video>
                                </div>
                            <?php endif; ?>
                            <div class="ui-video-info">
                                <h2 class="ui-video-title"><?= htmlspecialchars($video['title'] ?? 'Untitled') ?></h2>
                                <?php if (!empty($video['description'])): ?>
                                    <p class="ui-video-desc"><?= htmlspecialchars($video['description']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($duration)): ?>
                                    <span class="ui-video-duration"><?= $duration ?></span>
                                <?php endif; ?>
                                <?php if ($video['type'] === 'url' && !empty($video['platform'])): ?>
                                    <span class="ui-video-platform"><?= ucfirst($video['platform']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ui-section-empty">
                    <p>No videos available yet.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<script>
(function() {
    'use strict';

    // Lazy-load embeds: on click, replace thumbnail with actual iframe
    var embeds = document.querySelectorAll('.ui-video-embed');
    embeds.forEach(function(embed) {
        var btn = embed.querySelector('.ui-video-play-btn');
        if (!btn) return;

        btn.addEventListener('click', function() {
            var url = embed.getAttribute('data-embed-url');
            if (!url) return;

            var iframe = document.createElement('iframe');
            iframe.setAttribute('src', url);
            iframe.title = embed.closest('.ui-video-card')?.querySelector('.ui-video-title')?.textContent || 'Video player';
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
            iframe.setAttribute('allowfullscreen', '');
            iframe.setAttribute('loading', 'lazy');
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.position = 'absolute';
            iframe.style.top = '0';
            iframe.style.left = '0';
            iframe.style.border = '0';

            // Clear embed content and append iframe
            embed.innerHTML = '';
            embed.appendChild(iframe);
            iframe.focus();
        });
    });
})();
</script>

<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>

</body>
</html>
