<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/collection.php';
require_once dirname(__DIR__, 2) . '/models/Videos.php';

$videos = new Videos();
$videoList = $videos->getAllVideos(false);
$musicVideos = require dirname(__DIR__, 2) . '/content/music-videos.php';

$videoItems = [];
foreach ($musicVideos as $film) {
    $filmBase = FULL_BASE_PATH . $film['media_path'];
    $videoItems[] = ['anchor' => $film['slug'], 'item' => [
        '@type' => 'VideoObject',
        'name' => $film['title'] . ' — music video',
        'description' => $film['description'],
        'thumbnailUrl' => [$filmBase . '-poster.jpg'],
        'contentUrl' => $filmBase . '-1080p.mp4',
        'uploadDate' => $film['published_at'],
        'duration' => 'PT' . (float)$film['duration'] . 'S',
        'author' => ['@id' => rtrim(FULL_BASE_PATH, '/') . '/#person'],
        'creditText' => 'Illustrative stock footage from Mixkit; original coded visuals and edit. Stock performers are not Yunus Emre Vurgun.',
    ]];
}
foreach ($videoList as $videoRow) {
    $object = seo_video_object($videoRow);
    if ($object !== null) $videoItems[] = ['anchor' => 'video-' . (int)$videoRow['id'], 'item' => $object];
}
$videoMeta = $videoItems !== []
    ? [seo_collection_graph('Videos', 'videos', $videoItems)]
    : [ui_collection_schema('Videos', 'videos', array_column($videoList, 'title'), array_map(fn($item) => 'video-' . $item['id'], $videoList))];
$videoFresh = seo_newest_stamp($videoList, ['updated_at', 'uploaded_at']);
foreach ($musicVideos as $film) {
    if ($videoFresh === null || strtotime($film['published_at']) > strtotime($videoFresh)) $videoFresh = $film['published_at'];
}
if ($videoFresh !== null) $videoMeta['date_modified'] = $videoFresh;

ui_render_head(
    'Videos & Music Videos | Yunus Emre Vurgun',
    'Music videos and clips by Yunus Emre Vurgun. Watch Destontris, a full-length black-and-white film for the original instrumental.',
    $videoMeta + ['music_video_css' => '<link rel="stylesheet" href="' . FULL_BASE_PATH . 'assets/css/music-videos.css?v=' . filemtime(dirname(__DIR__, 2) . '/assets/css/music-videos.css') . '">']
);
?>
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('videos'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Video</p>
            <h1 class="ui-section-title">Videos</h1>
            <p class="ui-section-text">Music films, uploaded clips, and videos from YouTube and Odysee.</p>
            <nav class="music-video-jump" aria-label="Video sections"><a href="#music-videos">Music Videos</a><a href="#other-videos">More videos</a></nav>
        </section>

        <section class="ui-section music-video-section" id="music-videos" aria-labelledby="music-videos-title">
            <p class="ui-eyebrow">Original instrumentals</p>
            <h2 class="ui-section-title" id="music-videos-title">Music Videos</h2>
            <?php foreach ($musicVideos as $film):
                $filmBase = FULL_BASE_PATH . $film['media_path'];
                $filmId = htmlspecialchars($film['slug']);
                $filmDuration = (int)floor($film['duration'] / 60) . ':' . str_pad((string)((int)$film['duration'] % 60), 2, '0', STR_PAD_LEFT);
            ?>
            <article class="music-video-feature" id="<?= $filmId ?>" aria-labelledby="<?= $filmId ?>-title">
                <div class="music-video-heading">
                    <div><h3 id="<?= $filmId ?>-title"><?= htmlspecialchars($film['title']) ?></h3><p><?= htmlspecialchars($film['artist']) ?> · <?= $filmDuration ?> · Instrumental</p></div>
                    <a href="#<?= $filmId ?>" aria-label="Link to <?= htmlspecialchars($film['title']) ?>">Permalink</a>
                </div>
                <p class="music-video-advisory" id="<?= $filmId ?>-advisory"><strong>Motion advisory:</strong> fast cuts, accelerated movement and blur effects.</p>
                <video id="<?= $filmId ?>-player" class="music-video-player" width="1920" height="1080" controls playsinline preload="none" poster="<?= htmlspecialchars($filmBase) ?>-poster.jpg" aria-label="<?= htmlspecialchars($film['title']) ?> music video" aria-describedby="<?= $filmId ?>-advisory">
                    <source src="<?= htmlspecialchars($filmBase) ?>-1080p.mp4" type="video/mp4">
                    <track kind="captions" src="<?= htmlspecialchars($filmBase) ?>-captions.vtt" srclang="en" label="Instrumental description">
                    <p><a href="<?= htmlspecialchars($filmBase) ?>-1080p.mp4">Watch the MP4 video</a>.</p>
                </video>
                <div class="music-video-tools">
                    <label for="<?= $filmId ?>-quality">Video quality <select id="<?= $filmId ?>-quality" data-music-video-quality="<?= $filmId ?>-player"><option value="<?= htmlspecialchars($filmBase) ?>-1080p.mp4">1080p</option><option value="<?= htmlspecialchars($filmBase) ?>-720p.mp4">720p · smaller file</option></select></label>
                    <a href="<?= htmlspecialchars($filmBase) ?>-1080p.mp4" download>Download video</a>
                </div>
                <p class="music-video-description"><?= htmlspecialchars($film['description']) ?></p>
                <details class="music-video-details">
                    <summary>Listen without the motion</summary>
                    <p>Instrumental music; no vocals. The audio-only player uses the original song file.</p>
                    <audio controls preload="none" aria-label="<?= htmlspecialchars($film['title']) ?> original instrumental"><source src="<?= htmlspecialchars($filmBase) ?>-original.mp3" type="audio/mpeg"><a href="<?= htmlspecialchars($filmBase) ?>-original.mp3">Listen to the original MP3</a>.</audio>
                    <p><a href="<?= htmlspecialchars($filmBase) ?>-original.mp3" download="Destontris.mp3">Download the original MP3</a></p>
                </details>
                <details class="music-video-details">
                    <summary>Film &amp; footage credits</summary>
                    <p>Music: <?= htmlspecialchars($film['artist']) ?>. Original coded visuals and a section-led monochrome edit, combining licensed stock footage with animated silver forms.</p>
                    <p>Stock performers are illustrative and are not <?= htmlspecialchars($film['artist']) ?> or the recording session.</p>
                    <p>Footage: <a href="https://mixkit.co/license/#videoFree" rel="noopener">Mixkit Stock Video Free License</a>, verified for music videos and commercial projects.</p>
                    <ul><?php foreach ($film['credits'] as $credit): ?><li><a href="<?= htmlspecialchars($credit['url']) ?>" rel="noopener"><?= htmlspecialchars($credit['title']) ?></a></li><?php endforeach; ?></ul>
                </details>
            </article>
            <?php endforeach; ?>
        </section>

        <section class="ui-section" id="other-videos" aria-labelledby="other-videos-title">
            <h2 class="ui-section-title" id="other-videos-title">More videos</h2>
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
                                            <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="" class="ui-video-thumb"<?= str_starts_with((string)$video['thumbnail_url'], 'https://img.youtube.com/') ? ' width="480" height="360"' : ui_image_size_attrs(preg_replace('#^' . preg_quote(FULL_BASE_PATH, '#') . '#', '', (string)$video['thumbnail_url'])) ?> loading="lazy" decoding="async">
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
                                    <video class="ui-video-player" controls preload="metadata" playsinline<?= !empty($video['thumbnail_url']) ? ' poster="' . htmlspecialchars($video['thumbnail_url']) . '"' : '' ?>>
                                        <source src="<?= $videoPath ?>" type="video/mp4">
                                        <p>Your browser does not support HTML5 video.</p>
                                    </video>
                                </div>
                            <?php endif; ?>
                            <div class="ui-video-info">
                                <h3 class="ui-video-title"><?= htmlspecialchars($video['title'] ?? 'Untitled') ?></h3>
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
                    <p>No other videos available yet.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<script>
(function() {
    'use strict';

    var musicPlayers = document.querySelectorAll('.music-video-feature video, .music-video-feature audio');
    musicPlayers.forEach(function(active) {
        active.addEventListener('play', function() {
            musicPlayers.forEach(function(other) { if (other !== active) other.pause(); });
        });
    });

    document.querySelectorAll('[data-music-video-quality]').forEach(function(select) {
        select.addEventListener('change', function() {
            var player = document.getElementById(select.getAttribute('data-music-video-quality'));
            if (!player) return;
            var position = player.currentTime || 0;
            var resume = !player.paused;
            player.addEventListener('loadedmetadata', function() {
                player.currentTime = Math.min(position, player.duration || position);
                if (resume) player.play().catch(function() {});
            }, { once: true });
            player.querySelector('source').src = select.value;
            player.load();
        });
    });

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
