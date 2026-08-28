<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/models/Videos.php';

$videos = new Videos();
$videoList = $videos->getAllVideos(false);

ui_render_head(
    'Videos | Yunus Emre Vurgun',
    'Video collection — uploads, YouTube embeds, and Odysee clips.',
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('videos'); ?>

    <main class="ui-main">
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
                        <div class="ui-video-card" data-video-id="<?= $video['id'] ?>">
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
        });
    });
})();
</script>

<?php if (count($videoList) > 0): ?>
<style>
.ui-videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 24px;
}
.ui-video-card {
    background: var(--surface, #fff);
    border-radius: var(--radius-md, 8px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: box-shadow 0.2s;
}
.ui-video-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}
.ui-video-embed-container {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 aspect ratio */
    background: #000;
}
.ui-video-embed {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ui-video-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}
.ui-video-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: #222;
}
.ui-video-play-btn {
    position: relative;
    z-index: 2;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: rgba(0,0,0,0.7);
    border: 2px solid rgba(255,255,255,0.8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
}
.ui-video-play-btn:hover {
    background: rgba(0,0,0,0.9);
    transform: scale(1.05);
}
.ui-video-player {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}
.ui-video-info {
    padding: 14px 16px;
}
.ui-video-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 4px;
}
.ui-video-desc {
    font-size: 0.875rem;
    color: #434343;
    margin: 0 0 6px;
    line-height: 1.5;
}
.ui-video-duration,
.ui-video-platform {
    display: inline-block;
    font-size: 0.75rem;
    color: var(--color-text-tertiary);
    background: var(--color-bg-elevated);
    padding: 2px 8px;
    border-radius: 4px;
    margin-right: 6px;
}
.ui-section-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--color-text-tertiary);
}
@media (max-width: 480px) {
    .ui-videos-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<?php endif; ?>

<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
