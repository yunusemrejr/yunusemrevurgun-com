<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once __DIR__ . '/includes/ui.php';
require_once dirname(__DIR__) . '/models/Music.php';

$music = new Music();
$tracks = $music->getAllTracks(false);

ui_render_head(
    'Music | Yunus Emre Vurgun',
    'Music collection with audio player.',
    ['music' => true]
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('music'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Audio</p>
            <h1 class="ui-section-title">Music</h1>
            <p class="ui-section-text">A collection of audio tracks. Click to play.</p>
        </section>

        <section class="ui-section">
            <div class="ui-music-container">
                <?php if (count($tracks) > 0): ?>
                    <div class="ui-music-list" id="musicList">
                        <?php foreach ($tracks as $index => $track): 
                            $trackUrl = FULL_BASE_PATH . 'uploads/music/' . htmlspecialchars($track['filename']);
                            $duration = $track['duration'] > 0 ? gmdate('i:s', round($track['duration'])) : '--:--';
                            $recordedDate = !empty($track['recorded_at']) ? date('M d, Y', strtotime($track['recorded_at'])) : '';
                        ?>
                            <div class="ui-music-track" data-track-id="<?= $track['id'] ?>" data-src="<?= $trackUrl ?>">
                                <div class="ui-music-track-cover">
                                    <span class="ui-music-track-number"><?= $index + 1 ?></span>
                                </div>
                                <div class="ui-music-track-info">
                                    <span class="ui-music-track-title"><?= htmlspecialchars($track['title'] ?? 'Untitled') ?></span>
                                    <?php if (!empty($track['description'])): ?>
                                        <span class="ui-music-track-desc"><?= htmlspecialchars($track['description']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($recordedDate)): ?>
                                        <span class="ui-music-track-date">Recorded <?= $recordedDate ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ui-music-track-meta">
                                    <span class="ui-music-track-duration"><?= $duration ?></span>
                                </div>
                                <button class="ui-music-play-btn" type="button" aria-label="Play track">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Player bar -->
                    <div class="ui-music-player" id="musicPlayer">
                        <div class="ui-music-player-track">
                            <span class="ui-music-player-title" id="playerTitle">Select a track</span>
                        </div>
                        <div class="ui-music-player-controls">
                            <button class="ui-music-player-btn" id="prevBtn" type="button" aria-label="Previous track">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2"/></svg>
                            </button>
                            <button class="ui-music-player-btn ui-music-player-btn-play" id="playBtn" type="button" aria-label="Play">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </button>
                            <button class="ui-music-player-btn" id="nextBtn" type="button" aria-label="Next track">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2"/></svg>
                            </button>
                        </div>
                        <div class="ui-music-player-progress">
                            <span class="ui-music-player-time" id="currentTime">0:00</span>
                            <div class="ui-music-player-bar" id="progressBar">
                                <div class="ui-music-player-bar-fill" id="progressFill"></div>
                            </div>
                            <span class="ui-music-player-time" id="totalTime">0:00</span>
                        </div>
                        <div class="ui-music-player-volume">
                            <button class="ui-music-player-btn" id="muteBtn" type="button" aria-label="Mute">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                            </button>
                            <input type="range" class="ui-music-player-volume-slider" id="volumeSlider" min="0" max="1" step="0.05" value="0.8" aria-label="Volume">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ui-music-empty">
                        <p>No tracks available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php
        $links = $music->getAllLinks();
        if (count($links) > 0):
        $platformNames = [
            'spotify' => 'Spotify',
            'apple-music' => 'Apple Music',
            'youtube-music' => 'YouTube Music',
            'soundcloud' => 'SoundCloud',
            'bandcamp' => 'Bandcamp',
            'amazon-music' => 'Amazon Music',
            'deezer' => 'Deezer',
            'tidal' => 'Tidal',
            'other' => 'Other',
        ];
        $platformIcons = [
            'spotify' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>',
            'apple-music' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#FA243B"><path d="M12 0C5.372 0 0 5.372 0 12s5.372 12 12 12 12-5.372 12-12S18.628 0 12 0zm4.26 17.323c-.234.383-.7.508-1.083.274-2.958-1.804-6.664-2.748-10.866-1.94-.475.09-.943-.23-1.032-.705-.09-.476.23-.943.706-1.033 4.648-.894 8.77.158 12.104 2.198.382.234.508.7.274 1.082zm1.169-3.654c-.292.478-.874.63-1.353.338-3.388-2.08-8.533-2.726-12.543-1.49-.586.18-1.218-.156-1.398-.742-.18-.586.155-1.218.742-1.398 4.526-1.391 10.166-.66 14.004 1.687.478.292.63.874.338 1.353zm.115-3.857c-4.072-2.416-10.788-2.637-14.663-1.466-.708.214-1.454-.18-1.668-.888-.214-.708.18-1.454.888-1.668 4.442-1.345 11.844-1.092 16.475 1.635.635.377.842 1.187.465 1.822-.377.634-1.187.841-1.822.464z"/></svg>',
            'youtube-music' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#FF0000"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm6.56 16.087c-.224.652-.864 1.06-1.565 1.06-2.11-.018-4.467-.018-6.828-.018-2.36 0-4.718 0-6.828.018-.7 0-1.34-.407-1.564-1.06-.262-.785-.262-2.09-.262-4.087s0-3.302.262-4.087c.224-.652.864-1.06 1.565-1.06 2.11.018 4.467.018 6.828.018 2.36 0 4.718 0 6.828-.018.7 0 1.34.407 1.565 1.06.261.785.261 2.09.261 4.087s0 3.302-.262 4.087zM10.5 8.75v6.5l6-3.25-6-3.25z"/></svg>',
            'soundcloud' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#FF5500"><path d="M1.175 12.225c-.194 0-.353.16-.353.353v4.412c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-4.412c0-.194-.16-.353-.353-.353zm2.382 2.647c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.647 0c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.647 0c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.647 0c-.194 0-.353.16-.353.353v1.765c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-1.765c0-.194-.16-.353-.353-.353zm2.117-2.647c-.194 0-.353.16-.353.353v4.412c0 .194.16.353.353.353.194 0 .353-.16.353-.353v-4.412c0-.194-.16-.353-.353-.353zM21.58 10.18c-1.23 0-2.36.437-3.232 1.153-.14-2.828-2.457-5.098-5.343-5.098-.275 0-.544.028-.808.074-.16.028-.334.039-.497.039-.883 0-1.675.338-2.28.885-.083.075-.18.135-.248.22-.078.098-.105.214-.105.337v7.268c0 .194.16.353.353.353h11.16c1.554 0 2.823-1.269 2.823-2.823 0-1.554-1.27-2.823-2.823-2.823z"/></svg>',
            'bandcamp' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#629AA9"><path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/></svg>',
            'deezer' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#FEAA2D"><path d="M18.81 4.19v3.38H24V4.19h-5.19zM0 20.62h5.19v-3.38H0v3.38zm6.23 0h5.19v-3.38H6.23v3.38zm6.24 0h5.19v-3.38h-5.19v3.38zm6.34 0H24v-3.38h-5.19v3.38zM0 16.43h5.19v-3.38H0v3.38zm6.23 0h5.19v-3.38H6.23v3.38zm6.24 0h5.19v-3.38h-5.19v3.38zm6.34 0H24v-3.38h-5.19v3.38zM0 12.24h5.19V8.86H0v3.38zm6.23 0h5.19V8.86H6.23v3.38zm12.58 0H24V8.86h-5.19v3.38z"/></svg>',
            'tidal' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="#000000"><path d="M12.012 3.992L8.008 7.996 4.004 3.992 0 7.996 4.004 12 8.008 7.996l4.004 4.004 4.004-4.004-4.004-4.004zm0 8.008l-4.004 4.004 4.004 4.004 4.004-4.004-4.004-4.004z"/></svg>',
        ];
        ?>
        <section class="ui-section">
            <p class="ui-eyebrow">Listen Elsewhere</p>
            <h2 class="ui-section-title">Platform Links</h2>
            <p class="ui-section-text">Find my music on these platforms.</p>

            <div class="ui-music-links-grid">
                <?php foreach ($links as $link):
                    $pf = $link['platform'];
                    $pname = $platformNames[$pf] ?? 'Link';
                    $picon = $platformIcons[$pf] ?? '';
                ?>
                <a href="<?= htmlspecialchars($link['url']) ?>" class="ui-music-link-card" target="_blank" rel="noopener noreferrer">
                    <span class="ui-music-link-icon"><?= $picon ?></span>
                    <span class="ui-music-link-title"><?= htmlspecialchars($link['title']) ?></span>
                    <?php if (!empty($link['description'])): ?>
                    <span class="ui-music-link-desc"><?= htmlspecialchars($link['description']) ?></span>
                    <?php endif; ?>
                    <span class="ui-music-link-platform"><?= htmlspecialchars($pname) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php ui_render_footer(); ?>
</div>

<?php if (count($tracks) > 0): ?>
<script>
(function() {
    'use strict';

    const audio = new Audio();
    const tracks = document.querySelectorAll('.ui-music-track');
    const trackElements = Array.from(tracks);
    let currentIndex = -1;
    let isPlaying = false;

    const playBtn = document.getElementById('playBtn');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const muteBtn = document.getElementById('muteBtn');
    const progressFill = document.getElementById('progressFill');
    const progressBar = document.getElementById('progressBar');
    const currentTimeEl = document.getElementById('currentTime');
    const totalTimeEl = document.getElementById('totalTime');
    const playerTitle = document.getElementById('playerTitle');
    const volumeSlider = document.getElementById('volumeSlider');

    function formatTime(s) {
        if (isNaN(s) || s < 0) return '0:00';
        const m = Math.floor(s / 60);
        const sec = Math.floor(s % 60);
        return m + ':' + (sec < 10 ? '0' : '') + sec;
    }

    function updatePlayBtn() {
        const svg = playBtn.querySelector('svg');
        if (isPlaying) {
            svg.innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
        } else {
            svg.innerHTML = '<polygon points="5 3 19 12 5 21 5 3"/>';
        }
    }

    function selectTrack(index) {
        trackElements.forEach(function(el, i) {
            el.classList.toggle('is-active', i === index);
        });
    }

    function loadTrack(index) {
        if (index < 0 || index >= trackElements.length) return;
        const el = trackElements[index];
        currentIndex = index;
        const src = el.getAttribute('data-src');
        const title = el.querySelector('.ui-music-track-title').textContent;
        playerTitle.textContent = title;
        audio.src = src;
        audio.load();
        selectTrack(index);
    }

    function playTrack(index) {
        loadTrack(index);
        audio.play().then(function() {
            isPlaying = true;
            updatePlayBtn();
        }).catch(function() {});
    }

    function togglePlay() {
        if (currentIndex < 0) {
            playTrack(0);
            return;
        }
        if (isPlaying) {
            audio.pause();
            isPlaying = false;
        } else {
            audio.play().then(function() {
                isPlaying = true;
            }).catch(function() {});
        }
        updatePlayBtn();
    }

    function playNext() {
        if (currentIndex < 0) { playTrack(0); return; }
        const next = (currentIndex + 1) % trackElements.length;
        playTrack(next);
    }

    function playPrev() {
        if (currentIndex < 0) { playTrack(0); return; }
        const prev = (currentIndex - 1 + trackElements.length) % trackElements.length;
        playTrack(prev);
    }

    // Click on track row
    trackElements.forEach(function(el, i) {
        el.addEventListener('click', function(e) {
            if (e.target.closest('.ui-music-play-btn')) return;
            if (currentIndex === i && isPlaying) {
                togglePlay();
            } else {
                playTrack(i);
            }
        });
        el.querySelector('.ui-music-play-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            if (currentIndex === i) {
                togglePlay();
            } else {
                playTrack(i);
            }
        });
    });

    // Player controls
    playBtn.addEventListener('click', togglePlay);
    nextBtn.addEventListener('click', playNext);
    prevBtn.addEventListener('click', playPrev);

    // Audio events
    audio.addEventListener('timeupdate', function() {
        if (audio.duration) {
            const pct = (audio.currentTime / audio.duration) * 100;
            progressFill.style.width = pct + '%';
            currentTimeEl.textContent = formatTime(audio.currentTime);
        }
    });

    audio.addEventListener('loadedmetadata', function() {
        totalTimeEl.textContent = formatTime(audio.duration);
        currentTimeEl.textContent = '0:00';
        progressFill.style.width = '0%';
    });

    audio.addEventListener('ended', function() {
        isPlaying = false;
        updatePlayBtn();
        playNext();
    });

    audio.addEventListener('play', function() {
        isPlaying = true;
        updatePlayBtn();
    });

    audio.addEventListener('pause', function() {
        isPlaying = false;
        updatePlayBtn();
    });

    // Progress bar click
    progressBar.addEventListener('click', function(e) {
        if (!audio.duration) return;
        const rect = progressBar.getBoundingClientRect();
        const pct = (e.clientX - rect.left) / rect.width;
        audio.currentTime = pct * audio.duration;
    });

    // Volume
    volumeSlider.addEventListener('input', function() {
        audio.volume = parseFloat(this.value);
    });
    audio.volume = 0.8;

    muteBtn.addEventListener('click', function() {
        audio.muted = !audio.muted;
        muteBtn.classList.toggle('is-muted', audio.muted);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (e.key === ' ') { e.preventDefault(); togglePlay(); }
        if (e.key === 'ArrowRight') playNext();
        if (e.key === 'ArrowLeft') playPrev();
    });
})();
</script>
<?php endif; ?>

<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
