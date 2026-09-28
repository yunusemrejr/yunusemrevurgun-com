<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

$hfUrl = 'https://huggingface.co/yunusemrejr/GemmaClaim-270M';
$jsBase = FULL_BASE_PATH . 'assets/js/gemmaclaim/';
$appJs = dirname(__DIR__, 2) . '/assets/js/gemmaclaim/app.js';

ui_render_head(
    'Claim Splitter | Yunus Emre Vurgun',
    'Paste a claim and a 270M-parameter model splits it into premises, hidden assumptions and weak spots. It runs in your browser; nothing is sent to a server.',
    ['gemmaclaim' => true]
);
?>
<body class="ui-gemmaclaim-page">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main gc" id="main-content" tabindex="-1">
        <a class="gc-btn gc-btn-ghost gc-back" href="<?= FULL_BASE_PATH ?>">&lt; Home</a>

        <header class="gc-head">
            <svg class="gc-sprite" viewBox="0 0 16 13" width="96" height="78" shape-rendering="crispEdges" aria-hidden="true" focusable="false">
                <rect x="0" y="0" width="7" height="3" fill="#e0a33f"/>
                <rect x="9" y="0" width="7" height="3" fill="#e0a33f"/>
                <rect x="1" y="5" width="4" height="3" fill="#ece2d0"/>
                <rect x="6" y="5" width="4" height="3" fill="#ece2d0"/>
                <rect x="11" y="5" width="4" height="3" fill="#ece2d0"/>
                <rect x="3" y="10" width="4" height="3" fill="#8d7f68"/>
                <rect x="9" y="10" width="4" height="3" fill="#8d7f68"/>
            </svg>
            <div>
                <h1 class="gc-title">Claim Splitter</h1>
                <p class="gc-lead">Paste a claim. A tiny model takes it apart: premises, hidden assumptions, weak spots. It runs on your device.</p>
                <p class="gc-links"><a class="gc-btn gc-btn-ghost" href="<?= htmlspecialchars($hfUrl) ?>" target="_blank" rel="noopener noreferrer">Model on Hugging Face ↗</a></p>
            </div>
        </header>

        <section class="gc-chat" aria-label="Claim Splitter chat">
            <div class="gc-status">
                <button type="button" class="gc-btn" id="gcLoad">Load model · 253 MB</button>
                <div class="gc-bar" id="gcBar" role="progressbar" aria-label="Model download" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><span id="gcBarFill"></span></div>
                <p class="gc-state" id="gcState" role="status">Model not loaded. Downloaded once, then cached in your browser.</p>
            </div>

            <div class="gc-log" id="gcLog" role="log" aria-live="polite" aria-label="Conversation"></div>

            <div class="gc-examples" id="gcExamples">
                <p class="gc-label">Try</p>
                <div class="gc-example-list">
                    <button type="button" class="gc-btn gc-btn-ghost" data-claim="People who drink coffee tend to live longer, which shows coffee extends lifespan.">Coffee</button>
                    <button type="button" class="gc-btn gc-btn-ghost" data-claim="Our sales rose 20% after we changed the logo, so the new logo works.">Logo</button>
                    <button type="button" class="gc-btn gc-btn-ghost" data-claim="Crime fell after the city installed more streetlights, so streetlights reduce crime.">Lights</button>
                    <button type="button" class="gc-btn gc-btn-ghost" data-claim="Write me a poem about the sea.">Off-topic</button>
                </div>
            </div>

            <form class="gc-form" id="gcForm">
                <label class="gc-label" for="gcInput">Claim</label>
                <textarea id="gcInput" class="gc-input" rows="3" maxlength="1200" placeholder="Paste an English claim or argument" disabled></textarea>
                <div class="gc-form-row">
                    <p class="gc-hint">English only · 1200 chars max</p>
                    <button type="button" class="gc-btn gc-btn-ghost" id="gcClear">Clear</button>
                    <button type="submit" class="gc-btn" id="gcSend" disabled>Split</button>
                </div>
            </form>
        </section>

        <section class="gc-how" aria-labelledby="gcHowTitle">
            <h2 class="gc-h2" id="gcHowTitle">How it works</h2>
            <dl class="gc-facts">
                <div><dt>Model</dt><dd>Gemma 3 270M, fine-tuned to decompose claims (full fine-tune, then LoRA).</dd></div>
                <div><dt>Data</dt><dd>About 1,400 synthetic examples, including refusals for non-English and off-topic input.</dd></div>
                <div><dt>Runtime</dt><dd>4-bit GGUF (253 MB) run by llama.cpp compiled to WebAssembly, on the GPU through WebGPU where the browser has it. Greedy decoding.</dd></div>
                <div><dt>Score</dt><dd>Section-header F1 against reference answers went from 0.42 to 0.91 on 68 held-out prompts.</dd></div>
            </dl>
            <p class="gc-note">It is small and it is wrong sometimes. Read the output as a sketch of a claim's structure, not a verdict.</p>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<script>window.GC_BASE = <?= json_encode($jsBase) ?>;</script>
<script type="module" src="<?= $jsBase ?>app.js?v=<?= @filemtime($appJs) ?: 1 ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
