<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

ui_render_head(
    'YunoBot | Yunus Emre Vurgun',
    'AI chat assistant powered by a custom neural network that runs entirely in your browser.',
    ['yunobot' => true]
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('yunobot'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">AI Assistant</p>
            <h1 class="ui-section-title">YunoBot</h1>
            <p class="ui-section-text">A privacy-first AI assistant powered by a custom neural network trained for this site. It runs entirely in your browser — no downloads, no API calls — and answers instantly.</p>
        </section>

        <section class="ui-section">
            <div class="ui-yunobot-container">
                <div class="ui-yunobot-chat" id="yunobotChat">
                    <!-- Status Bar -->
                    <div class="chat-status-bar" role="status" aria-live="polite">
                        <span class="chat-status-dot" id="statusDot"></span>
                        <span class="chat-status-text" id="statusText">Initializing...</span>
                    </div>

                    <!-- Messages Area -->
                    <div class="ui-yunobot-messages" id="chatMessages" role="log" aria-label="Chat messages">
                        <!-- Messages will be injected by JS -->
                    </div>

                    <!-- Example Questions (shown initially, hidden after first message) -->
                    <div class="example-questions" id="exampleQuestions">
                        <div class="example-questions-label">Try asking</div>
                        <div class="example-questions-list"></div>
                    </div>

                    <!-- Input Area -->
                    <div class="chat-actions" id="chatActions">
                        <button class="chat-action-btn" id="clearChatBtn" type="button" aria-label="Clear chat history">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Clear
                        </button>
                        <button class="chat-action-btn" id="exportChatBtn" type="button" aria-label="Export chat history">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Export
                        </button>
                        <button class="chat-action-btn" id="timeBtn" type="button" aria-label="Get current time">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Time
                        </button>
                    </div>
                    <div class="chat-input-container">
                        <div class="chat-input-wrapper">
                            <textarea
                                class="chat-input"
                                id="chatInput"
                                placeholder="Type a message..."
                                autocomplete="off"
                                rows="1"
                                aria-label="Chat message input"
                            ></textarea>
                            <span class="chat-input-hint">Enter to send · Shift+Enter for new line</span>
                        </div>
                        <button type="button" class="chat-send-button" id="sendButton" aria-label="Send message">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </div>
                </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<!-- ============================================================
     Custom neural network — weights + runtime ship with the page.
     No CDN, no external model download: instant load, instant answers.
     ============================================================ -->
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot/nn-weights.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/nn-weights.js') ?>" defer></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot/nn-engine.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/nn-engine.js') ?>" defer></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot/knowledge-pack.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/knowledge-pack.js') ?>" defer></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot/kb.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/kb.js') ?>" data-wasm="<?= FULL_BASE_PATH ?>assets/js/yunobot/brain.wasm?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/brain.wasm') ?>" defer></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot/ml-engine.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/ml-engine.js') ?>" defer></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot.js') ?>" defer></script>

<!-- ============================================================
     PERFORMANCE: Service Worker for offline caching
     ============================================================ -->
<script data-cfasync="false">
// Register service worker for offline support (only in production, scoped to yunobot)
if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= FULL_BASE_PATH ?>sw-yunobot.js', { scope: '<?= FULL_BASE_PATH ?>yunobot/' })
            .then(function(registration) {
                console.log('[YunoBot SW] Registered:', registration.scope);
            })
            .catch(function(error) {
                console.log('[YunoBot SW] Registration failed:', error);
            });
    });
}
</script>

<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
<?php ui_render_gumroad_widget(); ?>
</body>
</html>
