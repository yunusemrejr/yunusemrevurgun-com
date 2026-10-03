<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/seo.php';

$hfUrl = 'https://huggingface.co/yunusemrejr/GemmaClaim-270M';
$jsBase = FULL_BASE_PATH . 'assets/js/gemmaclaim/';
$appJs = dirname(__DIR__, 2) . '/assets/js/gemmaclaim/app.js';

$origin = seo_origin();
$pageUrl = $origin . '/gemmaclaim';
$title = 'Claim Splitter – Premises & Hidden Assumptions, Text Stays Local';
$description = 'Paste a claim and a 270M-parameter model lists its premises, hidden assumptions and weak points in your browser. The tool never uploads your text.';

// Each answer is also visible on the page, and matches /gemmaclaim/how-it-works.
$faq = [
    ['What does Claim Splitter do?', 'It takes an English claim or argument and returns its claim type, core claim, premises, conclusion, hidden assumptions, confounders, evidence needed and inference weaknesses.'],
    ['Is my text uploaded anywhere?', 'The tool\'s own code sends it nowhere: inference runs in your browser with wllama, and the only network request it starts is the one-time 253 MB model download from Hugging Face. The site\'s analytics tags load on every page.'],
    ['Why does it need a 253 MB download?', 'That file is the model: a 4-bit GGUF of a 270-million-parameter fine-tune. The browser caches it after the first load.'],
    ['How accurate is it?', 'It is small and wrong sometimes. The reported section-header F1 of 0.91 on 68 held-out prompts measures whether the output has the right structure, not whether it is correct, and one documented run misstates its own premise.'],
    ['Does it work for text that is not English?', 'No. A check in the page and the model\'s refusal training both decline non-English input.'],
    ['Which model is it?', 'GemmaClaim-270M, a fine-tune of Google\'s Gemma 3 270M, published on Hugging Face.'],
];

ui_render_head($title, $description, [
    'gemmaclaim' => true,
    seo_script([
        [
            '@type' => 'WebApplication',
            '@id' => $pageUrl . '#app',
            'name' => 'Claim Splitter',
            'url' => $pageUrl,
            'description' => $description,
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Any (modern web browser)',
            'browserRequirements' => 'Requires JavaScript and WebAssembly; a one-time 253 MB model download',
            'isAccessibleForFree' => true,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'inLanguage' => 'en',
            'featureList' => [
                'Splits an English claim into premises, conclusion and hidden assumptions',
                'Lists confounders, evidence needed and inference weaknesses',
                'GemmaClaim-270M running in the browser through wllama (llama.cpp compiled to WebAssembly)',
                'Refuses non-English and off-topic input',
            ],
            'author' => seo_person(),
            'about' => seo_things(['argument', 'fact_checking']),
            'mentions' => seo_things(['gemma', 'llama_cpp', 'webassembly', 'gguf', 'hugging_face']),
            'subjectOf' => [
                ['@type' => 'TechArticle', 'name' => 'How a 270M-parameter claim analyzer runs in your browser', 'url' => $pageUrl . '/how-it-works'],
                ['@type' => 'TechArticle', 'name' => 'What the Claim Splitter returns: three real runs', 'url' => $pageUrl . '/example-output'],
            ],
        ],
        seo_breadcrumbs([['Home', $origin . '/'], ['Claim Splitter', $pageUrl]]),
        seo_faq($faq),
    ]),
    '<link rel="modulepreload" href="' . $jsBase . 'app.js?v=' . (@filemtime($appJs) ?: 1) . '">',
]);
?>
<body class="ui-gemmaclaim-page">
<div class="ui-page">
    <?php ui_render_navbar('more'); ?>

    <main class="ui-main gc" id="main-content" tabindex="-1">
        <a class="gc-btn gc-btn-ghost gc-back" href="<?= FULL_BASE_PATH ?>">← Home</a>

        <header class="gc-head">
            <svg class="gc-sprite" viewBox="0 0 16 13" width="96" height="78" aria-hidden="true" focusable="false">
                <rect x="0" y="0" width="7" height="3" rx="1.2" fill="#db1816"/>
                <rect x="9" y="0" width="7" height="3" rx="1.2" fill="#db1816"/>
                <rect x="1" y="5" width="4" height="3" rx="1.2" fill="#262e4d"/>
                <rect x="6" y="5" width="4" height="3" rx="1.2" fill="#262e4d"/>
                <rect x="11" y="5" width="4" height="3" rx="1.2" fill="#262e4d"/>
                <rect x="3" y="10" width="4" height="3" rx="1.2" fill="#eee2d7"/>
                <rect x="9" y="10" width="4" height="3" rx="1.2" fill="#eee2d7"/>
            </svg>
            <div>
                <h1 class="gc-title">Claim Splitter: premises and hidden assumptions, in your browser</h1>
                <p class="gc-lead">Claim Splitter takes an English claim and lists its premises, hidden assumptions, confounders and weak points. A 270M-parameter model does it inside this tab, and the tool's code never uploads your text.</p>
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

        <section class="gc-how" aria-labelledby="gcFaqTitle">
            <h2 class="gc-h2" id="gcFaqTitle">Quick answers</h2>
            <dl class="gc-facts gc-faq">
                <?php foreach ($faq as [$q, $a]): ?>
                <div><dt><?= htmlspecialchars($q) ?></dt><dd><?= htmlspecialchars($a) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="gc-how" aria-labelledby="gcMoreTitle">
            <h2 class="gc-h2" id="gcMoreTitle">How it works and what it returns</h2>
            <ul class="gc-more">
                <li><a href="<?= FULL_BASE_PATH ?>gemmaclaim/how-it-works">How a 270M-parameter claim analyzer runs in your browser</a>: the model, the runtime, the prompt and the language check.</li>
                <li><a href="<?= FULL_BASE_PATH ?>gemmaclaim/example-output">Three real runs, one with a visible mistake</a>: the eight-section output, verbatim.</li>
            </ul>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<script data-cfasync="false">window.GC_BASE = <?= json_encode($jsBase) ?>;</script>
<script data-cfasync="false" type="module" src="<?= $jsBase ?>app.js?v=<?= @filemtime($appJs) ?: 1 ?>"></script>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
</body>
</html>
