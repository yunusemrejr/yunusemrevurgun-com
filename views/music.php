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
</body>
</html>
