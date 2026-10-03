<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/seo.php';

$origin = seo_origin();
$pageUrl = $origin . '/yunobot';
$title = 'On-Device Chatbot Without an LLM – YunoBot Runs in Your Browser';
$description = 'YunoBot is an on-device chatbot without an LLM: a C++/WebAssembly core answers in English or Turkish in your browser and links its sources.';

// Every answer here is visible on the page (the first two as the paragraphs under
// their headings), and the figures are the ones documented in /yunobot/how-it-works.
$faq = [
    ['How does YunoBot work without a language model?', 'A C++ core compiled to a 913,776-byte WebAssembly module turns your message into hashed word and character features, scores 82 conversational intents with a 96-unit neural layer (401,363 parameters) and, for factual questions, ranks passages from this site with BM25-style term weights. It selects, composes or quotes replies; it does not generate free text.'],
    ['Does YunoBot send my messages to a server?', 'In a network test on 3 October 2026, no request made while loading the page or chatting contained any word of the test message. The reply is computed by a WebAssembly module inside the page. The site\'s analytics tags still load on every page.'],
    ['Which languages does YunoBot understand?', 'English, Turkish and mixed-language messages.'],
    ['What can I ask it?', 'Questions about this site\'s projects, writing, background and places, plus light small talk. When nothing matches well, it says it is not sure instead of guessing.'],
    ['How big is the download?', 'The WebAssembly core is 913,776 bytes and the bundled source pack is 405,655 bytes. Both are fetched when the page loads, and the service worker can keep them for offline use.'],
];

ui_render_head($title, $description, [
    'yunobot' => true,
    'docs' => true,
    seo_script([
        [
            '@type' => 'WebApplication',
            '@id' => $pageUrl . '#app',
            'name' => 'YunoBot',
            'url' => $pageUrl,
            'description' => $description,
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Any (modern web browser)',
            'browserRequirements' => 'Requires JavaScript and WebAssembly',
            'isAccessibleForFree' => true,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'inLanguage' => ['en', 'tr'],
            'featureList' => [
                'C++ core compiled to WebAssembly, running in a Web Worker',
                'Intent classifier with 401,363 parameters and a confidence gate',
                'BM25-style retrieval over this site\'s public pages, with source links',
                'English, Turkish and mixed-language conversation',
            ],
            'author' => seo_person(),
            'about' => seo_things(['chatbot', 'nlp']),
            'mentions' => seo_things(['webassembly', 'cpp', 'ann', 'bm25', 'web_worker']),
            'subjectOf' => [
                ['@type' => 'TechArticle', 'name' => 'How a chatbot without an LLM works', 'url' => $pageUrl . '/how-it-works'],
                ['@type' => 'TechArticle', 'name' => 'Does YunoBot send your messages anywhere?', 'url' => $pageUrl . '/does-it-send-my-messages'],
            ],
        ],
        seo_breadcrumbs([['Home', $origin . '/'], ['YunoBot', $pageUrl]]),
        seo_faq($faq),
    ]),
]);
?>
<body class="ui-yunobot-page">
<div class="ui-page">
    <?php ui_render_navbar('yunobot'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">AI Assistant</p>
            <h1 class="ui-section-title">YunoBot: an on-device chatbot without an LLM</h1>
            <p class="ui-section-text">YunoBot runs in your browser with no language model: a C++ core compiled to WebAssembly classifies your question and answers by quoting the matching passage from this site, in English, Turkish or both. In a network test, none of a message's text left the page. Say hello, ask about my work, or explore a project or journal topic, and follow the source links.</p>
        </section>

        <section class="ui-section ui-yunobot-workspace" aria-label="Ask YunoBot">
            <div class="ui-yunobot-container">
                <div class="ui-yunobot-chat" id="yunobotChat">
                    <!-- Status Bar -->
                    <div class="chat-status-bar" role="status" aria-live="polite">
                        <span class="chat-status-dot" id="statusDot"></span>
                        <span class="chat-status-text" id="statusText">Initializing...</span>
                        <?php ui_render_hampton('status'); ?>
                    </div>

                    <!-- Messages Area -->
                    <div class="ui-yunobot-messages" id="chatMessages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Chat messages">
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
                                placeholder="Ask about a project or topic…"
                                maxlength="2000"
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
            </div>
            <aside class="ui-yunobot-guide">
                <p class="ui-eyebrow">A guide to this site</p>
                <h2>Start with something specific.</h2>
                <p>A project name, a question about my background, or a topic from the journal gives YunoBot a useful starting point.</p>
                <ul><li>Ask in English, Turkish, or both.</li><li>Check the linked source for context.</li><li>Use Clear to start a new conversation.</li></ul>
                <?php ui_render_ebook_line('yunobot', true); ?>
                <details><summary>How it works &amp; privacy</summary><p>A C++ WebAssembly core classifies questions, searches public sources and keeps conversation context. Casual replies use reviewed sentence parts; factual answers quote or link their sources. It can still misunderstand a question.</p><p>The model and public source index load with the page. Chat messages stay in browser memory and are not sent to an AI service. Source links open the relevant page.</p></details>
            </aside>
        </section>

        <section class="ui-section dx-hub-section" aria-labelledby="yb-how">
            <h2 id="yb-how"><?= htmlspecialchars($faq[0][0]) ?></h2>
            <p><?= htmlspecialchars($faq[0][1]) ?> <a href="<?= FULL_BASE_PATH ?>yunobot/how-it-works">How it works, with its measured accuracy</a>.</p>
        </section>

        <section class="ui-section dx-hub-section" aria-labelledby="yb-privacy">
            <h2 id="yb-privacy"><?= htmlspecialchars($faq[1][0]) ?></h2>
            <p><?= htmlspecialchars($faq[1][1]) ?> <a href="<?= FULL_BASE_PATH ?>yunobot/does-it-send-my-messages">The test, and how to repeat it yourself</a>.</p>
        </section>

        <section class="ui-section dx-hub-section" aria-labelledby="yb-faq">
            <h2 id="yb-faq">More quick answers</h2>
            <dl class="dx-faq">
                <?php foreach (array_slice($faq, 2) as [$q, $a]): ?>
                <div><dt><?= htmlspecialchars($q) ?></dt><dd><?= htmlspecialchars($a) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>

<!-- ============================================================
     C++ WebAssembly core — worker, model and public data stay first-party.
     Versioned assets; messages are processed locally.
     ============================================================ -->
<script data-cfasync="false"
    data-worker="<?= FULL_BASE_PATH ?>assets/js/yunobot/wasm-worker.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/wasm-worker.js') ?>"
    data-wasm="<?= FULL_BASE_PATH ?>assets/js/yunobot/core.wasm?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/core.wasm') ?>"
    data-corpus="<?= FULL_BASE_PATH ?>assets/js/yunobot/knowledge-pack.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/knowledge-pack.js') ?>"
    data-live="<?= getenv('MODE') === 'development' ? '' : FULL_BASE_PATH . 'api/yunobot-knowledge.php' ?>"
    src="<?= FULL_BASE_PATH ?>assets/js/yunobot/wasm-engine.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot/wasm-engine.js') ?>" defer></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/yunobot.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/yunobot.js') ?>" defer></script>

<!-- ============================================================
     PERFORMANCE: Service Worker for offline caching
     ============================================================ -->
<script data-cfasync="false">
// Register service worker for offline support (only in production, scoped to yunobot)
if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= FULL_BASE_PATH ?>sw-yunobot.js?v=<?= filemtime(dirname(__DIR__, 2) . '/sw-yunobot.js') ?>', { scope: '<?= FULL_BASE_PATH ?>yunobot' })
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

</body>
</html>
