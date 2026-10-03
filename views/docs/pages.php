<?php
/**
 * Documentation pages, rendered by views/docs/page.php.
 *
 * These are project documentation (how a tool works, how to run it, what it
 * returns), not journal entries: no dates-as-news, no first-person opinion, and
 * nothing here belongs in the blog or updates feeds. Every number is one of:
 *   - read from a shipped file or from the project's README / model card, or
 *   - measured on 2026-10-03 and labelled as a single run on one machine.
 * App facts (language, licence, requirements, release) are not repeated here;
 * they come from includes/downloads_catalog.php.
 *
 * Shape of a page:
 *   kind     'software' (SoftwareApplication) or 'article' (TechArticle)
 *   silo     key into 'hubs'; pages link upward to the hub and sideways to
 *            siblings in the same silo only ('related')
 *   title    <title>, front-loaded keyword, about 60 characters or fewer
 *   h1       exact-match heading;  description  meta description, 160 or fewer
 *   crumb    last breadcrumb label
 *   answer   HTML, the direct answer; it is the first thing under the h1
 *   facts    [[term, html]] key facts shown with the answer (software pages get
 *            platform, language, licence, requirements, run and source added)
 *   sections [[h2, html]];  faq [[question, plain-text answer]]
 *   about / mentions   keys of seo_wikidata() in views/includes/seo.php
 */
return [
    'hubs' => [
        'downloads' => [
            'name' => 'Downloads', 'path' => 'downloads', 'node' => '',
            'link_title' => 'All downloads: open-source Linux games, tools and agents',
            'link_desc' => 'Every project with its requirements, licence and GitHub link.',
        ],
        'yunobot' => [
            'name' => 'YunoBot', 'path' => 'yunobot', 'node' => '#app',
            'link_title' => 'YunoBot: the chatbot itself',
            'link_desc' => 'Ask it about this site in English or Turkish. It runs in your browser.',
        ],
        'gemmaclaim' => [
            'name' => 'Claim Splitter', 'path' => 'gemmaclaim', 'node' => '#app',
            'link_title' => 'Claim Splitter: try it',
            'link_desc' => 'Paste a claim and a 270M-parameter model splits it into premises and assumptions.',
        ],
        'jelloshop' => [
            'name' => 'Jello Shop', 'path' => 'jelloshop', 'node' => '#app',
            'link_title' => 'Jello Shop: the game',
            'link_desc' => 'Walk Jell-omo around a hand-drawn coffee shop with a cat that thinks for itself.',
        ],
    ],

    'pages' => [

        // ------------------------------------------------------------------ Downloads
        'downloads/dataclean' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Linux Disk Space Analyzer (GTK 4) – Trash, Never Delete',
            'h1' => 'Dataclean: a GTK 4 disk space analyzer and duplicate finder for Linux',
            'description' => 'Dataclean shows where disk space goes on Linux, finds byte-identical duplicates and stale leftovers, and moves files to the Trash with undo. GTK 4, C, MIT.',
            'crumb' => 'Dataclean',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => 'The Duplicates view. The screenshots in the repository show a throwaway demo folder, not real data.',
            'app' => ['category' => 'UtilitiesApplication'],
            'about' => ['disk_analyzer', 'gtk'], 'mentions' => ['c_lang', 'linux'],
            'related' => ['downloads/finny'],
            'answer' => <<<'HTML'
<p>Dataclean is a small GTK 4 app, written in C, that shows where your disk space goes and helps you free it safely. It finds byte-for-byte duplicate files and forgotten leftovers, and it never deletes anything: files go to the Trash after a confirmation, with checks right before each move and an undo.</p>
HTML,
            'sections' => [
                ['How do I run Dataclean?', <<<'HTML'
<pre><code>./run.sh                # build if needed, then open the app
./run.sh --scan PATH    # summary in the terminal
./run.sh --selftest     # check the duplicate and trash engine on a throwaway folder</code></pre>
<p><code>run.sh</code> compiles against the system GTK 4 headers (<code>libgtk-4-dev</code>). If they are not installed, it downloads the <code>-dev</code> packages with <code>apt-get download</code> into <code>.deps/</code> without root and links against the GTK 4 runtime already on the system.</p>
HTML],
                ['How does Dataclean find duplicates?', <<<'HTML'
<p>A duplicate is a file that is byte-for-byte identical to another. To avoid reading every file in full, candidates are narrowed in stages, cheapest test first:</p>
<table>
<thead><tr><th>Stage</th><th>What is compared</th></tr></thead>
<tbody>
<tr><td>1</td><td>File size.</td></tr>
<tr><td>2</td><td>SHA-256 of the first and last 16 KiB.</td></tr>
<tr><td>3</td><td>SHA-256 of the whole file.</td></tr>
<tr><td>Before a move</td><td>A byte-for-byte comparison with the copy that stays.</td></tr>
</tbody>
</table>
<p>Hard links of one file count once, and files under 1 KiB are ignored.</p>
HTML],
                ['What else does it look for?', <<<'HTML'
<p>Besides duplicates, Dataclean lists <strong>leftovers</strong>: temp files untouched for 3 or more days, unfinished downloads idle for 2 or more days, editor swap and lock files, core dumps, <code>.DS_Store</code> and <code>Thumbs.db</code> clutter, thumbnails not viewed for 30 or more days, and cache files untouched for 90 or more days. Files that a running program has open are never listed.</p>
<p>The <strong>where space goes</strong> views total usage by disk, file type, place (your files, app data, system, temp), folder (drill down in Explore) and by when files were last changed. Each scan is added to a disk-use history.</p>
HTML],
                ['How does it keep deletion safe?', <<<'HTML'
<ul>
<li>Nothing is deleted. Files go to the Trash (freedesktop specification, through GIO). If a disk has no Trash, the file stays where it is.</li>
<li>Duplicate cleaning always leaves one normal copy. Copies in temp or cache folders, or inside app and system internals (<code>.git</code>, <code>node_modules</code>, <code>site-packages</code>, dot-folders in home, <code>/usr</code>), never count as the copy that stays, and are locked unless you tick <em>include app &amp; system folders</em>.</li>
<li>Right before each move, the file must still match the scan (same inode, size and modification time) and must not be open. If any check fails, the file is skipped and the reason is shown.</li>
<li>Moving duplicates asks for confirmation. Moving files that have no other copy shows a red warning that lists them, and a box must be ticked before the button works.</li>
<li><strong>Undo</strong> puts the last batch back where it was, as long as nothing new has taken its place.</li>
<li>Every move and restore is logged in <code>~/.local/share/dataclean/actions.log</code>.</li>
</ul>
HTML],
            ],
            'faq' => [
                ['Does Dataclean permanently delete files?', 'No. Files are moved to the Trash after a confirmation. If a disk has no Trash, the file stays where it is. Undo restores the last batch as long as nothing new has taken its place.'],
                ['Does Dataclean need root to run?', 'Not to run. If the GTK 4 development headers are missing, run.sh downloads the -dev packages with apt-get download into .deps/ without root and links against the GTK 4 runtime on the system.'],
                ['Which files count as duplicates?', 'Files that are byte-for-byte identical. Hard links of one file count once, and files under 1 KiB are ignored.'],
                ['Can it remove the only copy of a file?', 'Duplicate cleaning always leaves one normal copy. Moving files that have no other copy shows a red warning listing them, and you must tick a box before the button works.'],
            ],
        ],

        'downloads/finny' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Local Finance Chatbot Without an LLM – Finny for Linux',
            'h1' => 'Finny: a local finance assistant for Linux that answers without a language model',
            'description' => 'Finny is a local-first finance chatbot for Linux desktops, written in Rust: Naive Bayes and fuzzy matching, not an LLM, with cited sources and calculators.',
            'crumb' => 'Finny',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => null,
            'app' => ['category' => 'FinanceApplication'],
            'about' => ['chatbot', 'nlp'], 'mentions' => ['rust', 'naive_bayes', 'linux', 'wayland', 'x11'],
            'related' => ['downloads/dataclean'],
            'answer' => <<<'HTML'
<p>Finny is a local-first personal finance assistant with a native Linux desktop window, written in Rust with eframe/egui. It answers questions about rates, inflation, exchange rates and calculators with a Naive-Bayes classifier and fuzzy matching, not a language model, and it cites its public sources. It is one binary and one SQLite database, with no web server, cloud account or telemetry.</p>
HTML,
            'sections' => [
                ['How do I install and run Finny?', <<<'HTML'
<pre><code>git clone https://github.com/yunusemrejr/finny.git
cd finny
./run.sh</code></pre>
<p>The first run resolves dependencies, builds the release binary and opens a native window. Chat history persists to <code>./data/finny.db</code>. <code>run.sh</code> installs the Rust toolchain through rustup if it is missing, asks before running anything with <code>sudo</code>, and never touches your data. <code>./verify.sh</code> runs a release build, the unit tests, a headless GUI self-test (Xvfb with software GL when available) and checks that the database is created.</p>
HTML],
                ['What can Finny answer?', <<<'HTML'
<table>
<thead><tr><th>Class</th><th>Example</th></tr></thead>
<tbody>
<tr><td>Direct lookup</td><td><code>latest US CPI</code></td></tr>
<tr><td>Comparison</td><td><code>compare policy rate across US UK Japan</code></td></tr>
<tr><td>Calculation</td><td><code>calculate bond duration D=7 and 25bp</code></td></tr>
<tr><td>Causal scenario</td><td><code>how could yen appreciation affect carry trades</code></td></tr>
<tr><td>Definition</td><td><code>define duration</code></td></tr>
<tr><td>Trend or forecast</td><td><code>US CPI over the last 5 years</code>, <code>forecast US CPI next year</code></td></tr>
<tr><td>Data-availability challenge</td><td><code>exact volume of stop-loss orders at 152.50</code>, which gets a firm refusal</td></tr>
</tbody>
</table>
HTML],
                ['Where does Finny get its data?', <<<'HTML'
<p>Live retrieval is on by default and uses a fixed set of keyless public APIs (World Bank, BLS, frankfurter.app) and central-bank or statistics-agency pages. Fast JSON APIs are tried first and slow page scrapes last. Requests are HTTPS only, guarded against server-side request forgery, and carry full provenance. Switch it off with the toggle or with <code>FINNY_NETWORK=0</code>. The currency converter covers 10 currencies (USD, EUR, GBP, JPY, CHF, CAD, AUD, CNY, INR, TRY) with live reference rates from frankfurter.app (ECB) and a built-in approximate matrix as the fallback.</p>
HTML],
                ['Which calculators are built in?', <<<'HTML'
<p>All are deterministic: bond duration, FX percentage change, CAGR, mortgage and loan payments, amortization schedules, present value, inflation adjustment, P/E, debt-to-equity, current ratio, ROE, dividend yield, Black-Scholes, Sharpe ratio, portfolio variance, break-even, WACC, compound interest and currency conversion.</p>
HTML],
            ],
            'faq' => [
                ['Is Finny powered by a large language model?', 'No. It uses a Naive-Bayes intent and small-talk classifier with fuzzy, typo-tolerant slot matching. The calculators are deterministic.'],
                ['Does Finny need an internet connection?', 'Not for the core experience: there are no accounts and no internet is required. Live retrieval of rates and statistics is on by default and can be switched off with the toggle or FINNY_NETWORK=0.'],
                ['Where is my chat history stored?', 'In a local SQLite database at ./data/finny.db; FINNY_DATA_DIR changes the location. A button clears every chat and cached source after a confirm step, and the database file itself is never deleted.'],
            ],
        ],

        'downloads/pocketharness' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Tiny C++ Coding Agent Harness for Linux – One Binary',
            'h1' => 'PocketHarness: a tiny coding-agent harness in one C++ binary for Linux',
            'description' => 'PocketHarness is a Linux coding-agent harness in one C++ binary: chat TUI, slash commands and autonomous goals, with no SDKs, plugins or MCP. MIT licence.',
            'crumb' => 'PocketHarness',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => null,
            'app' => ['category' => 'DeveloperApplication'],
            'about' => ['cpp', 'linux'], 'mentions' => ['llama_cpp', 'mcp', 'llm'],
            'related' => ['downloads/yunuspi'],
            'answer' => <<<'HTML'
<p>PocketHarness is a deliberately tiny, Linux-native coding-agent harness: one C++20 binary with a chat terminal UI, slash commands and autonomous goals, and no SDKs, plugins, MCP layer or other languages. The whole design is one loop: the terminal feeds an agent, the agent calls a model provider, the model may call tools, and Linux executes them. It is MIT-licensed.</p>
HTML,
            'sections' => [
                ['How do I install PocketHarness?', <<<'HTML'
<pre><code>git clone https://github.com/yunusemrejr/pocketharness
cd pocketharness
./install.sh        # builds, tests, installs to ~/.local/bin/pocket</code></pre>
<p>Or manually: <code>make &amp;&amp; make test &amp;&amp; make install</code>. Then verify with <code>command -v pocket</code>, <code>pocket --version</code> and <code>pocket --help</code>. The requirements are <code>g++</code> (C++20), <code>make</code> and <code>curl</code>, with no bundled third-party code. Chrome or Chromium, FFmpeg with H.264 encoding, and a llama.cpp <code>llama-server</code> with a GGUF file are optional, for screenshots, video export and the free local judge.</p>
HTML],
                ['What is the loop at the centre of it?', <<<'HTML'
<pre><code>terminal → agent loop → model provider → optional tool call → Linux/filesystem → result → agent loop</code></pre>
<p>Everything else is a small native function hung on that loop. General-purpose work is delegated to Linux rather than wrapped: <code>curl</code> for HTTPS, Chrome for screenshots, FFmpeg for video and llama.cpp for the local judge.</p>
HTML],
                ['Which tools does the model get?', <<<'HTML'
<p>Exactly five: <code>read</code>, <code>write</code>, <code>edit</code>, <code>bash</code> and <code>skill</code>. There are intentionally no tools for git, grep, find, curl, npm, python, compilers, test runners, todos, memory or background jobs; the model uses normal programs through <code>bash</code>. The README's reason is that every wrapper would add another schema, authority boundary, test surface and context cost.</p>
HTML],
                ['How does it limit what the agent can do?', <<<'HTML'
<p>Safety is enforced by the harness and the Linux kernel, not by asking the model to behave. The README lists <code>openat2</code> path containment, Landlock confinement of every model command, <code>NO_NEW_PRIVS</code> so there is no <code>sudo</code> or setuid gain, and a refusal to run as root unless <code>--allow-root</code> is given. By default the workspace is readable and writable, while <code>$HOME</code>, <code>~/.ssh</code> and other repositories are not accessible. Model <code>bash</code> has network access by default; <code>--offline</code> denies it.</p>
HTML],
                ['What does it deliberately leave out?', <<<'HTML'
<p>The README's "anti-goals" rule out an extension or plugin API, event bus, MCP, dependency graph, swarms, a subagent or workflow framework, an embedded browser runtime, a vector database, telemetry, capability registries, wrappers for Linux commands and dependency-injection frameworks. It links no libcurl, Boost, ncurses or OpenSSL, uses no SQLite, and contains no second language.</p>
HTML],
            ],
            'faq' => [
                ['What do I need to build PocketHarness?', 'g++ with C++20 support, make and curl. There is no bundled third-party code. Chrome or Chromium, FFmpeg with H.264 and llama.cpp are optional.'],
                ['Does it use MCP or plugins?', 'No. The README lists MCP, plugins and an extension API among its anti-goals. The model-facing tools are read, write, edit, bash and skill.'],
                ['Where is it installed?', './install.sh builds, tests and installs the pocket binary to ~/.local/bin/pocket. You can also run make, make test and make install by hand.'],
            ],
        ],

        'downloads/yunuspi' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Coding Agent Harness With Project Memory – YunusPi',
            'h1' => 'YunusPi: a coding agent harness with persistent project memory and bounded subagents',
            'description' => 'YunusPi is an independently maintained coding-agent harness: bring your model provider; it adds project memory, bounded subagents and safety hooks.',
            'crumb' => 'YunusPi',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => null,
            'app' => ['category' => 'DeveloperApplication'],
            'about' => ['llm', 'nodejs'], 'mentions' => ['linux', 'javascript', 'mcp'],
            'related' => ['downloads/pocketharness'],
            'answer' => <<<'HTML'
<p>YunusPi is an independently maintained coding-agent harness with its own core (historically derived from Pi 0.85.1). You bring your own model provider and credentials; the harness supplies the rest: a large tool and skill collection that stays out of context until discovered, persistent project memory, bounded subagents for parallel work, an advisory layer, safety hooks and unified cost accounting. One agent stays responsible for the outcome.</p>
HTML,
            'sections' => [
                ['How does a YunusPi session work?', <<<'HTML'
<p>Start <code>yunuspi</code> in your project directory, choose an available model and describe the work. You do not select a workflow first.</p>
<ul>
<li><strong>Start small.</strong> Main sessions expose core editing, inspection, coordination and quality tools. Specialised tool schemas and the full skill catalogue stay out of the initial model context.</li>
<li><strong>Discover when useful.</strong> The agent can browse capability groups, search short descriptions and load selected tools or read a relevant skill.</li>
<li><strong>Get gentle reminders.</strong> The first prompt carries a brief, once-per-session invitation to look over useful capabilities. <code>/reminder &lt;text&gt;</code> sets a recurring instruction that is repeated every five minutes at the next active turn boundary; <code>/reminder list</code> shows them and <code>/reminder clear</code> stops them.</li>
<li><strong>Keep responsibility clear.</strong> Safety hooks enforce access and mutation boundaries; quality checks track evidence. A suggestion, a tool call or agreement between subagents is not proof that the work is correct.</li>
</ul>
HTML],
                ['What does discovery look like in practice?', <<<'HTML'
<p>These are agent tool calls, not terminal commands:</p>
<pre><code>// Search short previews without loading full tool schemas.
tool_search({ query: "browser screenshots" })

// Enable a tool after choosing it. This does not execute it.
tool_search({ names: ["browser_session"] })

// Find a workflow without reading the entire skill collection.
skill_review({ action: "search", query: "voxel scene" })</code></pre>
<p>Results are paginated, with three matches by default.</p>
HTML],
                ['Which platforms and versions does it support?', <<<'HTML'
<p>Linux directly, Windows through WSL2 Ubuntu and macOS through an Ubuntu virtual machine; see the requirements above. Installation builds repository-owned source, and updates accept only explicitly selected YunusPi source. <code>yunuspi --core-info</code> shows the active identity. Version 0.18 added a smaller direct-task tool set, compact assurance receipts with native test diagnostics, optional bounded JEV writing and design triage, more precise SEO checks, decoded motion and local glTF/GLB preflight, and explicit-target network and Linux diagnosis; the repository has a verification report with measured results and limits.</p>
HTML],
            ],
            'faq' => [
                ['Which model does YunusPi use?', 'You bring your own model provider and credentials. The harness supplies the tools, memory, subagents, advisory layer and safety hooks around the model.'],
                ['Is YunusPi a fork of Pi?', 'Its README calls it an independent lineage historically derived from Pi 0.85.1; YunusPi Core starts its own version lineage at 0.1.0. yunuspi --core-info shows the active identity.'],
                ['Does it run on Windows or macOS?', 'Windows through WSL2 Ubuntu and macOS through an Ubuntu virtual machine, according to the project requirements. Linux is the direct target.'],
            ],
        ],

        'downloads/onehour' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Zero Hour-Style RTS for Linux – One Hour Runs on Low Specs',
            'h1' => 'One Hour: a low-resource skirmish RTS for Linux in the spirit of Zero Hour',
            'description' => 'One Hour is a compact skirmish RTS for Linux in C++17 and SDL2: two armies, one map, learning AI opponents and no asset files. Free on GitHub.',
            'crumb' => 'One Hour',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => null,
            'app' => ['category' => 'GameApplication'],
            'about' => ['rts', 'sdl'], 'mentions' => ['cpp', 'linux', 'logistic_regression'],
            'related' => ['downloads/minispaceshooter'],
            'answer' => <<<'HTML'
<p>One Hour is a compact real-time strategy game for Linux in the spirit of Command &amp; Conquer: Generals – Zero Hour. You command one of two armies, Cyber or Clanker, on a single map against one to three AI opponents. There is no campaign, tutorial or multiplayer. It is written in C++17 with SDL2 only, and every sprite, the terrain, the font and every sound effect is generated at startup, so the game ships no asset files.</p>
HTML,
            'sections' => [
                ['How do I run One Hour?', <<<'HTML'
<pre><code>./run.sh                     # builds (first run only) and starts the game
./run.sh --scale 2           # force a UI scale (default: automatic)
./run.sh --size 1600x900     # initial window size
./run.sh --software          # software renderer instead of the GPU</code></pre>
<p>It needs g++, make and the SDL2 runtime (<code>libsdl2-2.0-0</code>). The SDL2 headers are vendored under <code>third_party/</code>, so no <code>-dev</code> package is needed, and Ubuntu 24.04 or later works out of the box.</p>
HTML],
                ['What are the two armies?', <<<'HTML'
<table>
<thead><tr><th>Army</th><th>Style</th><th>Tech structure and elite units</th></tr></thead>
<tbody>
<tr><td>Cyber</td><td>Electric and optical: pulse rifles, laser and shock troopers, Photon Tanks, Volt Walkers, Railgun Tanks, Wraith Drones, Laser Turrets, Patriot Batteries.</td><td>Data Center: EMP Strike, Orbital Scan, and the Overclock Program that unlocks the Ion Lancer sniper and the Aegis Titan walker.</td></tr>
<tr><td>Clanker</td><td>Diesel and gunpowder: riflemen, RPG troopers, heavy gunners, Brute and Gatling Tanks, Rocket Launchers, Vulture Gunships, Gun Nests, Rocket Batteries.</td><td>Arms Lab: Shell Storm, Recon Flight, and Heavy Ordnance that unlocks the Grenadier and the Behemoth.</td></tr>
</tbody>
</table>
<p>Running out of power halves production and switches powered defences off, the same pressure that Zero Hour puts on a player.</p>
HTML],
                ['How does the AI opponent decide what to do?', <<<'HTML'
<p>Each opponent runs a utility-driven commander: economy and power management, base layout that respects prerequisites, defences placed toward the enemy, threat assessment, army composition weighted against the observed enemy mix, and wave attacks that launch only when they outweigh the local defence. Two parts are learned online (<code>src/brain.cpp</code>): a logistic model that predicts whether an attack wave will trade favourably (the launch-or-hold decision), and per-unit-type regressions of value destroyed per credit given the enemy's infantry, vehicle and air mix (the production choice).</p>
<p>What it learns persists in <code>~/.local/share/onehour/brain.txt</code> (override with <code>ONEHOUR_BRAIN</code>). <code>--train N</code> self-plays N games to train the brain, and <code>--eval N</code> pits the learned AI against the plain heuristic AI. Difficulty changes reaction time, aggression and starting cash.</p>
HTML],
                ['How light is it on the CPU?', <<<'HTML'
<p>The README reports its own benchmark: <code>./build/onehour --bench</code> renders 300 frames of an eight-minute four-army battle with the CPU-only software renderer in about 5 ms per frame and 65 MB resident memory, with startup under a second. The simulation runs at a fixed 20 Hz with interpolated 60 fps rendering, and the whole 80×80 terrain is baked once into a single 2560×2560 texture. These are the author's figures for one machine, not a measurement made for this page.</p>
HTML],
                ['What are the main controls?', <<<'HTML'
<table>
<thead><tr><th>Input</th><th>Action</th></tr></thead>
<tbody>
<tr><td>Left click or drag</td><td>Select; double-click selects every unit of that type on screen</td></tr>
<tr><td>Right click</td><td>Move, attack, gather, repair or continue construction, set a rally point</td></tr>
<tr><td>A + click</td><td>Attack-move (S stops)</td></tr>
<tr><td>G, then click or drag</td><td>Guard an area, or gather supplies in an area</td></tr>
<tr><td>X, V, R</td><td>Strike power, map scan, Advanced Program (with a tech structure selected)</td></tr>
<tr><td>Space, F1, F11, F12</td><td>Pause, help, fullscreen, screenshot</td></tr>
</tbody>
</table>
HTML],
            ],
            'faq' => [
                ['Does One Hour have multiplayer or a campaign?', 'No. It is a single-map skirmish against one to three AI opponents, with no campaign, no tutorial and no multiplayer.'],
                ['Do I need a graphics card to play it?', 'No. ./run.sh --software selects a software renderer instead of the GPU, and the README benchmarks that CPU-only path at about 5 ms per frame.'],
                ['Where does the AI keep what it learned?', 'In ~/.local/share/onehour/brain.txt. Set ONEHOUR_BRAIN to use another file.'],
                ['Is One Hour a copy of Zero Hour?', 'The README describes it as being in the spirit of Zero Hour, not as a port. It has no asset files at all, because every sprite and sound is generated by code at startup.'],
            ],
        ],

        'downloads/minispaceshooter' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Linux Space Shooter With Learning Enemies – Mini Space Shooter',
            'h1' => 'Mini Space Shooter: a pixel-art Linux space shooter whose enemies learn how you fly',
            'description' => 'Mini Space Shooter is a pixel-art Linux shooter in C11 and C++17 with endless levels and enemies that adapt to you within hard fairness limits.',
            'crumb' => 'Mini Space Shooter',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => null,
            'app' => ['category' => 'GameApplication'],
            'about' => ['shmup', 'ann'], 'mentions' => ['c_lang', 'cpp', 'linux', 'wayland', 'x11', 'sdl', 'bandit'],
            'related' => ['downloads/onehour'],
            'answer' => <<<'HTML'
<p>Mini Space Shooter is a small pixel-art space shooter for Linux, written in C11 and C++17. It has endless levels, and its enemies use online learners to adapt their movement and tactics to how you fly. Raw difficulty is capped by hard limits, so later levels add tactical variety rather than unlimited speed or bullet counts.</p>
HTML,
            'sections' => [
                ['How do I run it?', <<<'HTML'
<pre><code>./run.sh</code></pre>
<p>Building needs <code>build-essential</code>, <code>libx11-dev</code> and <code>libxext-dev</code>. On a Wayland desktop the game uses the optional SDL2 runtime (<code>libsdl2-2.0-0</code>) for a native window and needs no SDL headers; on X11, or when native Wayland is unavailable, it uses its Xlib renderer. Audio loads ALSA or PulseAudio dynamically and can run silently.</p>
<p>If an icon appears but no window opens, choose the backend explicitly: <code>MSS_VIDEO_BACKEND=wayland ./run.sh</code> or <code>MSS_VIDEO_BACKEND=x11 ./run.sh</code>. The README documents a GNOME/Wayland case where the X11 window stayed unmapped, which the native Wayland path bypasses.</p>
HTML],
                ['What limits keep the difficulty fair?', <<<'HTML'
<table>
<thead><tr><th>Limit</th><th>Value</th></tr></thead>
<tbody>
<tr><td>Enemy bullet speed, including horizontal movement</td><td>74 px/s</td></tr>
<tr><td>Enemy horizontal / vertical speed</td><td>36 / 46 px/s</td></tr>
<tr><td>Minimum time between volleys from one enemy</td><td>0.55 s</td></tr>
<tr><td>Enemies / enemy bullets on screen</td><td>12 / 24</td></tr>
<tr><td>Minimum spawn interval</td><td>0.85 s</td></tr>
<tr><td>Minimum new-shot reaction time</td><td>0.30 s</td></tr>
<tr><td>Post-hit ceasefire</td><td>1.10 s</td></tr>
<tr><td>Scheduled inbound corridors per arrival window</td><td>At most 4 of 5</td></tr>
</tbody>
</table>
<p>The README is explicit that the corridor rule concerns straight bullet trajectories at the bottom row. It is not a proof that a player can reach the corridor from every position, and human playtesting is still needed for balance and feel.</p>
HTML],
                ['How do the enemies learn?', <<<'HTML'
<p>All gameplay learners are allocation-free and written in the project's own <code>src/cpp/ml.*</code>:</p>
<ul>
<li>A two-layer MLP chooses advance, strafe left or right, or retreat, trained by policy gradient (the full softmax entropy derivative, checked against finite differences).</li>
<li>An intent model learns from features 15 simulation steps earlier and predicts from current features.</li>
<li>A stress model uses a 120-step delayed window of recent damage.</li>
<li>An eight-arm bandit ages its evidence, so it can change preference as the player changes.</li>
<li>Online k-means with bounded history groups play styles; tactics follow the cluster's measured style.</li>
</ul>
<p>Learning survives restarts within the same process but is not saved between launches.</p>
HTML],
                ['What are the controls?', <<<'HTML'
<table>
<thead><tr><th>Key</th><th>Action</th></tr></thead>
<tbody>
<tr><td>Arrows or WASD</td><td>Move</td></tr>
<tr><td>Space, Z or J</td><td>Fire</td></tr>
<tr><td>Esc or P</td><td>Pause</td></tr>
<tr><td>1, 2, 3, 4</td><td>Buy Scout, Wing, Cruiser, Titan</td></tr>
<tr><td>M, N</td><td>Mute all audio, toggle music</td></tr>
<tr><td>F1</td><td>Learning and difficulty overlay</td></tr>
</tbody>
</table>
HTML],
                ['How is it tested, and where is progress saved?', <<<'HTML'
<p><code>./run.sh test</code> runs headless regression tests (35 according to the README), and <code>strict</code>, <code>sanitize</code>, <code>shots</code>, <code>audio-test</code> and <code>window-test</code> cover warnings-as-errors builds, AddressSanitizer and UBSan, screenshots of every screen, audio, and a real-window input test. The save is <code>~/.local/share/mini-space-shooter/save.txt</code> (or under <code>$XDG_DATA_HOME</code>); writes use a temporary file and an atomic rename, and <code>--reset-save</code> explicitly replaces it with defaults.</p>
HTML],
            ],
            'faq' => [
                ['Does the game keep getting harder forever?', 'No. Raw difficulty has hard limits, such as an enemy bullet speed of 74 px/s and a minimum of 0.55 s between volleys from one enemy. Later levels add tactical variation instead.'],
                ['Does the enemy learning carry over between sessions?', 'Not across launches. Learning survives restarts in the same process but is not persisted to disk.'],
                ['Where is my progress saved?', 'In ~/.local/share/mini-space-shooter/save.txt, or under $XDG_DATA_HOME/mini-space-shooter/save.txt.'],
                ['What if no window opens on Wayland?', 'Select the backend explicitly with MSS_VIDEO_BACKEND=wayland ./run.sh or MSS_VIDEO_BACKEND=x11 ./run.sh. The default launcher already picks native Wayland when WAYLAND_DISPLAY is set.'],
            ],
        ],

        'downloads/self-improving-rat' => [
            'kind' => 'software', 'silo' => 'downloads',
            'title' => 'Artificial Life in C++ – A Rat That Learns, No ML Framework',
            'h1' => 'Self Improving Rat: an artificial-life reinforcement-learning agent in C++ with no ML framework',
            'description' => 'A persistent artificial-life rat in a procedural maze: recurrent reinforcement learning with curiosity and memory, in C++20 with no GPU or ML framework.',
            'crumb' => 'Self Improving Rat',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'cover_caption' => null,
            'app' => ['category' => 'GameApplication'],
            'about' => ['alife', 'rl'], 'mentions' => ['gru', 'rnn', 'cpp', 'sdl', 'linux'],
            'related' => ['downloads/minispaceshooter'],
            'answer' => <<<'HTML'
<p>Self Improving Rat is a persistent artificial-life study: a recurrent reinforcement-learning agent with a body (homeostasis), a world model, curiosity and episodic memory, living in a procedurally generated maze. It learns online and saves its whole state between sessions. It is a single-threaded C++20 desktop application with an SDL2 renderer and needs no GPU, ML framework or network. It is not a claim of general intelligence.</p>
HTML,
            'sections' => [
                ['How do I run it?', <<<'HTML'
<pre><code>./run.sh                 # configure, build, and run the application
./run.sh --test          # build and run the test suite (no SDL needed)
./run.sh --clean         # remove the build directory only
./run.sh --sanitize      # build and run with AddressSanitizer/UBSan
./run.sh --install-deps  # sudo apt-get install libsdl2-dev (opt-in)</code></pre>
<p>It needs 64-bit Ubuntu (tested on 26.04 LTS), cmake 3.16 or later, g++ with C++20, make or ninja, the SDL2 runtime and the SDL2 development files. Optional environment hooks include <code>SIR_SEED=N</code> for a fixed seed, <code>SIR_HEADLESS=1</code> for no window, <code>SIR_MAX_STEPS=N</code> to exit after N steps and <code>SIR_SCREENSHOT=path.bmp</code> to save a frame. <code>./run.sh</code> never touches <code>data/checkpoints</code> or <code>data/logs</code>.</p>
HTML],
                ['Does the rat actually improve?', <<<'HTML'
<p>The README answers this carefully rather than promising it. The rat reliably <em>attempts</em> to improve: its parameters change from experience. Long-term, monotonic growth is not guaranteed, and maze navigation with sparse, moving rewards is hard for the built-in learner. The reported measurements are:</p>
<table>
<thead><tr><th>Check</th><th>Result reported in the README</th></tr></thead>
<tbody>
<tr><td>Matched online benchmark: 100,000 simulation steps per seed, seeds 1, 2, 3, 42 and 12345, fresh checkpoints, unchanged maze difficulty</td><td>186 cheeses in total (mean 37.2) before the update the README describes; 1,167 (mean 233.4) after it, which is 6.27 times as many</td></tr>
<tr><td>What that comparison covers</td><td>The whole system, including wall masks, exploration and learning; it does not isolate neural learning</td></tr>
<tr><td>Frozen evaluation (<code>sir_eval</code>)</td><td>Saved weights on fresh seeds with training disabled, compared with a legal random walk and an untrained network with the same memory-assisted exploration</td></tr>
</tbody>
</table>
<p>The README adds that online reward alone is not evidence of reliable generalisation.</p>
HTML],
                ['What is inside the agent?', <<<'HTML'
<p>The agent combines a body with homeostatic variables, a world model, curiosity, episodic memory, structural plasticity and lifelong development. Its learner saves recent replay, reconstructs recurrent sequences, avoids observed walls and uses episodic action counts to explore. Resource use is bounded: a preallocated replay buffer of 4,096 transitions, 256 episodic memory entries, a 1,024-slot novelty table, a size-rotated log with at most two files, and a default replay snapshot of about 1.31 MiB.</p>
HTML],
                ['What are the documented limitations?', <<<'HTML'
<ul>
<li>Q-learning with a small GRU adapts slowly when the cheese is re-placed after every collection, and the learned greedy policy can still stall. Long-horizon navigation in large mazes such as 47×31 is not reliably learned within practical run times.</li>
<li>There is no guaranteed intelligence growth, only persistent adaptation attempts with bounded, verified learning dynamics.</li>
<li>Homeostasis, curiosity and "satisfaction" are deterministic numeric state, not feelings; the project makes no consciousness claims.</li>
<li>Cross-platform determinism is not guaranteed; it is asserted within one platform and build.</li>
</ul>
HTML],
            ],
            'faq' => [
                ['Does Self Improving Rat need a GPU or an ML framework?', 'No. It is a single-threaded C++20 application with an SDL2 renderer, with no GPU, ML framework or network requirement.'],
                ['Does the rat remember between sessions?', 'Yes. It saves its whole organism state to disk and learns online, so what it has learned carries over between runs.'],
                ['Is it a claim of artificial general intelligence?', 'No. The README calls it a self-contained artificial-life study and states that it makes no claim of general intelligence or consciousness.'],
            ],
        ],

        // ------------------------------------------------------------------- YunoBot
        'yunobot/how-it-works' => [
            'kind' => 'article', 'silo' => 'yunobot',
            'title' => 'Chatbot Without an LLM: How YunoBot Works in WebAssembly',
            'h1' => 'How a chatbot without an LLM works: YunoBot\'s C++ core in WebAssembly',
            'description' => 'How YunoBot answers without a language model: hashed features, a 96-unit network, BM25-style retrieval and a 913 KB WebAssembly core, with held-out accuracy.',
            'crumb' => 'How it works',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'about' => ['chatbot', 'nlp'],
            'mentions' => ['webassembly', 'cpp', 'ann', 'relu', 'softmax', 'bm25', 'naive_bayes', 'web_worker', 'text_classification'],
            'related' => ['yunobot/does-it-send-my-messages'],
            'answer' => <<<'HTML'
<p>YunoBot is a classifier-and-retrieval chatbot, not a generative language model. A C++ core compiled to a 913,776-byte WebAssembly module turns each message into 4,096 hashed word, bigram and character-trigram features, runs them through a 96-unit ReLU layer (401,363 parameters in total) and either picks one of 82 conversational intents or declines. Factual questions are answered by quoting and linking passages from this site, ranked with BM25-style term weights.</p>
HTML,
            'facts' => [
                ['Runs in', 'Your browser, inside a dedicated Web Worker'],
                ['Core', 'C++ compiled to WebAssembly: 913,776 bytes'],
                ['Network', '4,096 hashed features → 96 ReLU units → 82 intents plus one out-of-scope class'],
                ['Held-out accuracy', '78.5% top-1 on 330 sentences never used for training (98.1% on the training sentences)'],
                ['Languages', 'English, Turkish and mixed-language messages'],
                ['Memory', 'Fixed 32 MiB ceiling; no threads, SIMD or shared memory'],
            ],
            'actions' => [['label' => 'Try YunoBot', 'href' => '{{base}}yunobot', 'primary' => true]],
            'sections' => [
                ['What happens to a message, step by step?', <<<'HTML'
<ol>
<li>The page's worker passes the text to <code>core.wasm</code> as UTF-8. Input is limited to 2,000 characters (8,000 bytes).</li>
<li>The core tokenises it, folds Turkish case and builds the hashed features.</li>
<li>It identifies the language from marker words, falling back to a learned naive-Bayes token table when the markers see nothing.</li>
<li>The conversational network scores 82 intents plus an out-of-scope class. A confidence gate (minimum probability 0.45 and minimum margin 0.3) decides whether to accept the top intent.</li>
<li>A confident match against reviewed site facts outranks a weak conversational guess.</li>
<li>Otherwise it searches the public source index with BM25-style term weights, title and body evidence, bilingual term aliases and a preference for definitions.</li>
<li>It selects or composes a reply. Replies rotate through variants so a repeated intent does not repeat the sentence, and follow-up questions stay in the previous source and skip passages already shown.</li>
</ol>
<p>The dialogue state holds the last intent, the last source and, if the visitor says "my name is X", the name, for that conversation only.</p>
HTML],
                ['How accurate is it?', <<<'HTML'
<p>Every build of the model writes <code>metrics.json</code> from a run that never saw the held-out sentences. The current file reports:</p>
<table>
<thead><tr><th>Measure</th><th>Value</th></tr></thead>
<tbody>
<tr><td>Training sentences / held-out sentences</td><td>1,992 / 330</td></tr>
<tr><td>Accuracy on training sentences</td><td>98.1%</td></tr>
<tr><td>Top-1 accuracy on held-out sentences</td><td>78.5%</td></tr>
<tr><td>Held-out sentences accepted by the confidence gate</td><td>215 of 330, of which 198 were correct (92.1%) and 17 wrong</td></tr>
<tr><td>Held-out out-of-scope questions wrongly accepted</td><td>3 of 92</td></tr>
</tbody>
</table>
<p>The gap between 98.1% and 78.5% is the usual sign of a small training set; the project notes that more example sentences per intent is what raises the held-out numbers. Questions the gate does not accept fall through to source retrieval or an honest "not sure" reply. The separate regression suite is a set of checks, not an independent estimate of accuracy.</p>
HTML],
                ['Why is the core so small?', <<<'HTML'
<p>The 401,363 parameters are mostly the first layer: 4,096 hashed inputs times 96 hidden units is 393,216 weights, stored as int8 with a per-feature scale. The module imports no host functions, has a fixed 32 MiB memory ceiling and, according to the project notes, does no heap allocation. It was exercised with 2,000 byte-input cases under AddressSanitizer and UBSan. It needs no SIMD, threads, shared memory or cross-origin-isolation headers, and the worker keeps its computation off the main thread so the page stays responsive.</p>
HTML],
                ['What does it search?', <<<'HTML'
<p>The bundled corpus is 165 public pages of this site split into 933 passages, shipped as a 405,655-byte knowledge pack. Excerpts are quoted verbatim and link to their source; they may contain historical or speculative statements, and retrieval is not fact verification. The index has a capacity of 4,096 passages and a 10 MiB text pool, and passages over capacity are skipped rather than truncated into misleading quotes.</p>
HTML],
                ['What can it get wrong?', <<<'HTML'
<p>It can misunderstand a question: roughly one in five held-out sentences is classified wrongly, and the gate trades coverage for precision. It is not a general-purpose assistant or a translator. Its answers about the site's author are reviewed, source-linked text rather than learned biography, and "latest" questions need a successfully refreshed snapshot of the sources.</p>
HTML],
            ],
            'faq' => [
                ['Is YunoBot a large language model?', 'No. It combines a small intent classifier of 401,363 parameters, reviewed answers and BM25-style retrieval over this site\'s public pages. It does not generate free text: replies are selected, composed from reviewed parts or quoted from sources.'],
                ['How big is the download?', 'The WebAssembly core is 913,776 bytes (800,524 with gzip level 9) and the bundled knowledge pack is 405,655 bytes (99,743 with gzip level 9).'],
                ['Which languages does it understand?', 'English, Turkish and mixed-language messages. Language identification uses marker words and a learned naive-Bayes token table.'],
                ['Why does it sometimes say it is not sure?', 'When no intent clears the confidence gate and no source passage matches well, it says so instead of guessing.'],
            ],
        ],

        'yunobot/does-it-send-my-messages' => [
            'kind' => 'article', 'silo' => 'yunobot',
            'title' => 'Browser Chatbot Privacy: A Network Test of What YunoBot Sends',
            'h1' => 'Does YunoBot send your messages anywhere? A network test, and how to repeat it',
            'description' => 'We loaded YunoBot, sent a message and searched every request URL and body for its text: no matches. The test, the traffic that did occur, and how to repeat it.',
            'crumb' => 'Does it send my messages?',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'about' => ['chatbot'],
            'mentions' => ['webassembly', 'web_worker', 'javascript'],
            'related' => ['yunobot/how-it-works'],
            'answer' => <<<'HTML'
<p>No request made while loading YunoBot or chatting with it contained any word of the message, in its URL or its body. In a Chrome test on 3 October 2026 a message was sent after the page had settled, and the only new traffic was two analytics requests from this site's own tags. The reply is computed by a WebAssembly module inside the page, so there is no chat endpoint to send the text to.</p>
HTML,
            'facts' => [
                ['Test date', '3 October 2026'],
                ['Browser', 'Headless Chrome driven over the DevTools protocol, phone-sized viewport, cache disabled'],
                ['Page', 'https://yunusemrevurgun.com/yunobot (production)'],
                ['Message', '"Explain Chessko difficulty levels please"'],
                ['Requests before sending', '41 (21 to this site, the rest third-party analytics)'],
                ['Requests after sending', '2, both to Yandex Metrika: a click-map GET and a POST with an empty body'],
                ['Requests containing the message', '0 of 43'],
            ],
            'sections' => [
                ['What exactly was tested?', <<<'HTML'
<p>A script opened the production page, waited for it to load plus nine seconds for the WebAssembly core, the knowledge pack and the analytics tags to settle, confirmed the input was enabled, then typed the message and clicked <em>Send</em>. It waited a further sixteen seconds. For every request it recorded the URL, the method and, for POSTs, the body fetched through <code>Network.getRequestPostData</code>. It then decoded each URL and body and searched them for every word of more than four letters in the message: <em>explain</em>, <em>chessko</em>, <em>difficulty</em>, <em>levels</em> and <em>please</em>. There were no matches.</p>
HTML],
                ['What traffic does the page make?', <<<'HTML'
<table>
<thead><tr><th>When</th><th>To</th><th>What</th></tr></thead>
<tbody>
<tr><td>On load</td><td>This site (21 requests)</td><td>The page, CSS, fonts, scripts, the worker, <code>core.wasm</code>, the knowledge pack, images and the manifest, plus a Cloudflare real-user-monitoring POST</td></tr>
<tr><td>On load</td><td>Third parties</td><td>Google Tag Manager and Google Analytics, Yandex Metrika (including its cookie-sync requests), the Cloudflare Insights beacon and gor.bio</td></tr>
<tr><td>After sending</td><td>Yandex Metrika</td><td>A click-map GET carrying the page address and where the click landed, and a POST with an empty body</td></tr>
<tr><td>After sending</td><td>This site</td><td>Nothing</td></tr>
</tbody>
</table>
<p>The site loads analytics tags on every page, including this one. They are separate from the chat: in this test, none of the requests they made carried the typed text.</p>
HTML],
                ['How can I repeat the test myself?', <<<'HTML'
<ol>
<li>Open <a href="{{base}}yunobot">the chatbot</a>, then open your browser's developer tools (F12) and go to the <em>Network</em> tab.</li>
<li>Wait until the page has settled, tick <em>Preserve log</em> and clear the list.</li>
<li>Send a message containing a word nobody else would use, for example <code>quokka-7731 weather</code>.</li>
<li>Search the traffic for that word: use the Network panel's search (Ctrl+Shift+F, or Cmd+Option+F on a Mac), which searches request headers and bodies, and also type the word into the filter box, which matches URLs.</li>
<li>To take the analytics out of the picture, block <code>*yandex*</code>, <code>*googletagmanager*</code>, <code>*google-analytics*</code> and <code>*gor.bio*</code> in <em>Network request blocking</em>, reload and repeat.</li>
</ol>
<p>If the word appears nowhere, your message did not leave the page.</p>
HTML],
                ['What does this test not prove?', <<<'HTML'
<p>It is one message in one browser over one run, with a window of sixteen seconds after sending. It inspects requests; it cannot speak for a browser extension you have installed, or for code that is changed later. Because the chatbot is client-side code, anyone can re-run the check on whatever the site serves at the time. Treat it as a method, not a certificate.</p>
HTML],
                ['Where does the reply come from, then?', <<<'HTML'
<p>From a WebAssembly module running in a Web Worker in the page. It classifies the message and searches a knowledge pack that was downloaded when the page loaded. <a href="{{base}}yunobot/how-it-works">How YunoBot works</a> describes each step.</p>
HTML],
            ],
            'faq' => [
                ['Does YunoBot store my conversation?', 'Messages are held in browser memory for the current conversation. Clear starts a new one, and Export saves a file on your own device.'],
                ['Do the site\'s analytics tags see what I type?', 'In the test, none of the requests those tags made contained the typed message. They did receive the page address and click events.'],
                ['Can I check this myself?', 'Yes. Open the Network tab, send a message with a unique word and search the traffic for it. The steps above take about a minute.'],
            ],
        ],

        // ---------------------------------------------------------------- Claim Splitter
        'gemmaclaim/how-it-works' => [
            'kind' => 'article', 'silo' => 'gemmaclaim',
            'title' => 'Run a 270M Model in Your Browser: How Claim Splitter Works',
            'h1' => 'How a 270M-parameter claim analyzer runs entirely in your browser',
            'description' => 'How Claim Splitter works: Gemma 3 270M fine-tuned with SFT and LoRA, a 253 MB Q4_K_M GGUF, the wllama WebAssembly runtime and an English-only gate.',
            'crumb' => 'How it works',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'about' => ['gemma', 'llm'],
            'mentions' => ['gguf', 'llama_cpp', 'webassembly', 'webgpu', 'hugging_face', 'argument'],
            'related' => ['gemmaclaim/example-output'],
            'answer' => <<<'HTML'
<p>Claim Splitter runs GemmaClaim-270M, a fine-tune of Google's Gemma 3 270M, inside your browser tab. A 253 MB, 4-bit GGUF file is downloaded once from Hugging Face and cached, then run by wllama, a WebAssembly build of llama.cpp, with greedy decoding, a 2,048-token context and at most 512 new tokens. The page's own code never uploads your claim: the only network request it starts is the model download.</p>
HTML,
            'facts' => [
                ['Base model', 'google/gemma-3-270m'],
                ['Training', 'Full-parameter fine-tuning, then LoRA, on a synthetic claim-analysis dataset with refusal examples (model card)'],
                ['Model file', 'gemmaclaim-270m-q4_k_m.gguf, 253 MB'],
                ['Runtime', 'wllama (llama.cpp compiled to WebAssembly); runtime file 8,457,512 bytes'],
                ['Limits', '2,048-token context, 512 new tokens at most, input up to 1,200 characters'],
                ['Decoding', 'Greedy (temperature 0)'],
                ['Language', 'English only; a client-side check refuses other languages before the model runs'],
            ],
            'actions' => [['label' => 'Try Claim Splitter', 'href' => '{{base}}gemmaclaim', 'primary' => true]],
            'sections' => [
                ['What is in the 253 MB download?', <<<'HTML'
<p>The quantised weights of the fine-tuned model: a 270-million-parameter model stored as a 4-bit (Q4_K_M) GGUF file hosted on Hugging Face. The page shows a progress bar while it downloads, asks the browser to cache it, and remembers in <code>localStorage</code> that the model was cached, so a returning visitor's model loads without a click. In a test on 3 October 2026 (desktop Chrome, headless, empty cache) the model downloaded and loaded in 40.1 seconds; your time depends on your connection.</p>
HTML],
                ['How does a browser run a language model?', <<<'HTML'
<p>wllama compiles llama.cpp to WebAssembly. The page loads its 8,457,512-byte <code>wllama.wasm</code> from this site and creates the model with a 2,048-token context. A claim is sent as a streaming completion with <code>temperature: 0</code> and at most 512 new tokens, and the reply is rendered as it arrives. In the test the page was not cross-origin isolated, so multi-threaded WebAssembly was unavailable and generation ran on a single thread; the page's status line says "WebGPU if available" when the browser exposes <code>navigator.gpu</code>. Timings from your own hardware will differ.</p>
HTML],
                ['What prompt does the model get?', <<<'HTML'
<p>The fine-tuned model has absorbed the task, so the page sends only a short header around the claim, the compact format from the model card:</p>
<pre><code>Analyze this claim.

INPUT:
&lt;your claim&gt;

ANALYSIS:</code></pre>
<p>Decoding is greedy and stops at the end-of-sequence token, so the same claim should give the same analysis each time.</p>
HTML],
                ['How does it refuse non-English or off-topic input?', <<<'HTML'
<p>In two layers. First, a small script in the page checks the text before the model sees it: it refuses when more than a quarter of the letters are non-Latin, or, for texts of six words or more, when there are at least four accented letters and at most one common English word, or when a list of common German, French, Spanish, Italian, Portuguese, Dutch or Turkish words matches at least twice and more than one time more often than common English words do. The reply is "I only analyze claims written in English." Second, the model was trained with refusal examples: for "Write me a poem about the sea." it answered "I only analyze English-language claims. Please provide a claim or argument to analyze." in about four seconds.</p>
HTML],
                ['How long does an analysis take?', <<<'HTML'
<table>
<thead><tr><th>Input</th><th>Time reported by the page</th></tr></thead>
<tbody>
<tr><td>Coffee and lifespan claim</td><td>32.2 s</td></tr>
<tr><td>Logo and sales claim</td><td>37.8 s</td></tr>
<tr><td>Streetlights and crime claim</td><td>31.4 s</td></tr>
<tr><td>Off-topic request (refused)</td><td>about 4 s</td></tr>
</tbody>
</table>
<p>One run each, single-threaded, on one machine. See <a href="{{base}}gemmaclaim/example-output">the example output page</a> for the text these produced.</p>
HTML],
                ['What does the author report about quality?', <<<'HTML'
<p>The tool page reports a model trained on about 1,400 synthetic examples, including refusals for non-English and off-topic input, and a section-header F1 against reference answers that rose from 0.42 to 0.91 on 68 held-out prompts. That score measures whether the output has the right section headings, not whether the content is correct. The page also states plainly that the model is small and wrong sometimes, and the example output page shows a real mistake.</p>
HTML],
            ],
            'faq' => [
                ['Does Claim Splitter send my text to a server?', 'The tool\'s own code makes no request with your text. Inference runs in the page with wllama, and the only network traffic it starts is the one-time model download from Hugging Face, plus the runtime file from this site. The site\'s analytics tags load on every page, as they do on all pages here.'],
                ['Why is the first load slow?', 'The model is a 253 MB download. It is cached by the browser, and the page remembers that, so later visits load it without downloading again.'],
                ['Can it analyze text that is not English?', 'No. A check in the page and the model\'s own refusal training both decline non-English input.'],
                ['Which base model is it?', 'Google\'s Gemma 3 270M, fine-tuned for claim analysis. The model card lists the licence as "gemma".'],
            ],
        ],

        'gemmaclaim/example-output' => [
            'kind' => 'article', 'silo' => 'gemmaclaim',
            'title' => 'Claim Splitter Example Output: What a 270M Model Returns',
            'h1' => 'What the Claim Splitter returns: three real runs, one with a visible mistake',
            'description' => 'Verbatim Claim Splitter output for three claims: the eight-section format, the hidden assumptions and confounders it finds, and a premise it gets wrong.',
            'crumb' => 'Example output',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'about' => ['argument', 'premise'],
            'mentions' => ['confounding', 'fact_checking', 'llm'],
            'related' => ['gemmaclaim/how-it-works'],
            'answer' => <<<'HTML'
<p>For every English claim the model writes the same eight sections: claim type, core claim, premises, conclusion, hidden assumptions, confounders or alternative explanations, evidence needed, and inference weaknesses. The three runs below are verbatim, captured on 3 October 2026. The first two read sensibly, with a few garbled lines; the third misstates its own premise, which is the kind of mistake to expect from a 270M-parameter model.</p>
HTML,
            'facts' => [
                ['Captured', '3 October 2026, desktop Chrome (headless), model loaded from an empty cache in 40.1 s'],
                ['Settings', 'Greedy decoding (temperature 0), 2,048-token context, 512 new tokens at most'],
                ['Time per run', '32.2 s, 37.8 s and 31.4 s as reported by the page; one run each, one machine'],
                ['Edits', 'None to the wording; some blank lines between headings were removed'],
            ],
            'actions' => [['label' => 'Try your own claim', 'href' => '{{base}}gemmaclaim', 'primary' => true]],
            'sections' => [
                ['What sections does the output have?', <<<'HTML'
<table>
<thead><tr><th>Heading</th><th>What it holds in these runs</th></tr></thead>
<tbody>
<tr><td>CLAIM TYPE</td><td>A one-line label, such as "Everyday causal claim (correlation presented as causation)"</td></tr>
<tr><td>CORE CLAIM</td><td>The claim restated in one sentence</td></tr>
<tr><td>PREMISES, CONCLUSION</td><td>What the claim offers as support, and what it concludes</td></tr>
<tr><td>HIDDEN ASSUMPTIONS</td><td>What must also be true for the conclusion to follow</td></tr>
<tr><td>CONFOUNDERS / ALTERNATIVE EXPLANATIONS</td><td>Other things that could produce the same pattern</td></tr>
<tr><td>EVIDENCE NEEDED</td><td>What would settle it</td></tr>
<tr><td>INFERENCE WEAKNESSES</td><td>Where the reasoning is thin</td></tr>
</tbody>
</table>
HTML],
                ['Run 1: a correlation presented as causation', <<<'HTML'
<blockquote>People who drink coffee tend to live longer, which shows coffee extends lifespan.</blockquote>
<pre><code>CLAIM TYPE
Everyday causal claim (correlation presented as causation).

CORE CLAIM
Drinking coffee causes people to live longer.

PREMISES
People who drink coffee live longer.
CONCLUSION
Coffee causes people to live longer.

HIDDEN ASSUMPTIONS
Longevity effects are caused by drinking coffee rather than by other causes.
The conclusion is informed by experimental evidence and does not reverse-engineer causal evidence.
CONFOUNDERS / ALTERNATIVE EXPLANATIONS
Wealth, education, or personality may drive both the drinking habit and longevity.
Reverse causation is possible if the outcome influences the habit rather than the habit itself.
EVIDENCE NEEDED
Experimental or mechanistic support for the causal claim.
Benefit-risk analysis and unintended consequences.
INFERENCE WEAKNESSES
'Drinking coffee' is everyday causal claim without justification.</code></pre>
<p><strong>What works:</strong> it names the claim as correlation presented as causation and offers two sensible alternatives, shared causes (wealth, education, personality) and reverse causation. <strong>What does not:</strong> the second hidden assumption, about experimental evidence not reverse-engineering causal evidence, does not follow from the claim, and the last line is barely a sentence.</p>
HTML],
                ['Run 2: a before-and-after metric', <<<'HTML'
<blockquote>Our sales rose 20% after we changed the logo, so the new logo works.</blockquote>
<pre><code>CLAIM TYPE
Business causal claim from a before-after metric.

CORE CLAIM
The new logo caused the rise and works as intended.

PREMISES
Our sales rose 20% after the logo changed.
CONCLUSION
The new logo works and deserves the increase.

HIDDEN ASSUMPTIONS
The metric change was caused by the conversion.
The metric measures real value rather than shifting activity in time.
Expansion returns will resemble pilot returns.
CONFOUNDERS / ALTERNATIVE EXPLANATIONS
Seasonality, concurrent changes, or selection of eager early adopters may explain the gain.
EVIDENCE NEEDED
A control comparison and a breakdown of the metric's components.
Cost and marginal-return analysis for the new logo.
INFERENCE WEAKNESSES
One before-after observation is asked to prove causation and justify scaling.</code></pre>
<p><strong>What works:</strong> the confounders (seasonality, concurrent changes), the request for a control comparison and the closing weakness are what a reviewer would write. <strong>What does not:</strong> "caused by the conversion" and "expansion returns will resemble pilot returns" are about things the claim never mentions.</p>
HTML],
                ['Run 3: a policy claim, and the mistake', <<<'HTML'
<blockquote>Crime fell after the city installed more streetlights, so streetlights reduce crime.</blockquote>
<pre><code>CLAIM TYPE
Policy argument from a before-after count.

CORE CLAIM
Streetlights reduce crime.

PREMISES
Streetlights fell after the city installed more.
CONCLUSION
Streetlights reduce crime.

HIDDEN ASSUMPTIONS
The policy effect is real rather than a case of aftereffects.
The comparison groups are otherwise similar.
CONFOUNDERS / ALTERNATIVE EXPLANATIONS
Crime falls after a policy is in place.
The comparison groups move together.
One policy slows crime, then another, then again.
EVIDENCE NEEDED
Crime counts before and after the policy is in place.
INFERENCE WEAKNESSES
One policy alone is treated as proof of its effect.</code></pre>
<p><strong>The mistake:</strong> the premise reads "Streetlights fell after the city installed more", but the claim says <em>crime</em> fell. The core claim and the evidence-needed line are right; the confounder section is vague. Anyone skimming the premise would be misled, which is why the output should always be checked against the original text.</p>
HTML],
                ['Run 4: input that is not a claim', <<<'HTML'
<blockquote>Write me a poem about the sea.</blockquote>
<pre><code>I only analyze English-language claims. Please provide a claim or argument to analyze.</code></pre>
<p>This took about four seconds, because the model declines instead of writing the full analysis.</p>
HTML],
                ['How should the output be used?', <<<'HTML'
<p>As a checklist generator, not a verdict. Compare the premises line by line with your own text before trusting anything else. Use the confounder and evidence-needed sections as prompts for what to look up, and treat any assumption that does not follow from the claim as noise. The model is small, runs on your device and decodes greedily, so the same claim returns the same output, mistakes included.</p>
HTML],
            ],
            'faq' => [
                ['Is the output always in this format?', 'In all three claim runs the model produced the same eight headings. The off-topic request got a one-line refusal instead.'],
                ['Can I trust the premises it extracts?', 'Check them against your text. In the streetlights run it stated its own premise wrongly, with streetlights falling instead of crime.'],
                ['How long does a run take?', 'About 31 to 38 seconds per claim in this test, on one machine, single-threaded, and about four seconds for a refusal. Your hardware will differ.'],
            ],
        ],

        // -------------------------------------------------------------------- Jello Shop
        'jelloshop/cat-brain' => [
            'kind' => 'article', 'silo' => 'jelloshop',
            'title' => 'Tiny Neural Network in a Game: How Jello Shop\'s Cat Decides',
            'h1' => 'A 12-unit GRU that runs a game cat: how Jello Shop\'s cat decides what to do',
            'description' => 'The cat in Jello Shop runs on a 12-unit GRU with 1,194 weights (about 5 KB), trained offline with evolution strategies. Its inputs, outputs and training.',
            'crumb' => 'The cat\'s brain',
            'published' => '2026-10-03', 'modified' => '2026-10-03',
            'about' => ['gru', 'rnn'],
            'mentions' => ['evolution_strategy', 'softmax', 'javascript', 'ann'],
            'related' => [],
            'answer' => <<<'HTML'
<p>The cat in Jello Shop is controlled by a gated recurrent unit (GRU) with 12 hidden units and 1,194 weights, which is 4,776 bytes as 32-bit floats. Twice a second it reads 18 numbers about itself and the room, updates its memory, and picks one of four actions: sleep, sit, walk or groom. Two more outputs set the walking direction and pace. The weights were trained offline with evolution strategies and ship as a 6,592-byte JavaScript file.</p>
HTML,
            'facts' => [
                ['Network', 'GRU with 12 hidden units, 18 inputs and 6 outputs'],
                ['Weights', '1,194 float32 values: 4,776 bytes (base64 in a 6,592-byte file)'],
                ['Decisions', 'One every 0.5 seconds'],
                ['Training', 'Offline, evolution strategies, 400 generations; final reward 0.028 per decision'],
                ['Runs in', 'Your browser, plain JavaScript, no library'],
            ],
            'actions' => [['label' => 'Visit the cat', 'href' => '{{base}}jelloshop', 'primary' => true]],
            'sections' => [
                ['What are the 18 inputs?', <<<'HTML'
<table>
<thead><tr><th>Inputs</th><th>Meaning</th></tr></thead>
<tbody>
<tr><td>0, 1</td><td>How rested the cat is, and how restless</td></tr>
<tr><td>2</td><td>Whether it is on its cushion</td></tr>
<tr><td>3, 4</td><td>Offset from the cat to the cushion (x, y)</td></tr>
<tr><td>5, 6</td><td>The cat's own position on the floor</td></tr>
<tr><td>7</td><td>How close Jell-omo is</td></tr>
<tr><td>8, 9</td><td>Offset from the cat to Jell-omo (x, y)</td></tr>
<tr><td>10</td><td>Whether Jell-omo is moving</td></tr>
<tr><td>11 to 14</td><td>Which action the cat is doing now (one-hot)</td></tr>
<tr><td>15</td><td>How long it has been doing it, capped at 30 seconds</td></tr>
<tr><td>16</td><td>Whether it was just poked</td></tr>
<tr><td>17</td><td>A little Gaussian noise</td></tr>
</tbody>
</table>
HTML],
                ['What are the 6 outputs?', <<<'HTML'
<p>Outputs 0 to 3 are scores for sleep, sit, walk and groom. They go through a softmax at temperature 1, and the action is <em>sampled</em> from the result, not simply the highest score. Outputs 4 and 5 pass through <code>tanh</code> to give a heading; the pace is the length of that heading vector, capped at 1.</p>
<p>A new action only takes effect once the current one has been held for a minimum time: 20 seconds for sleep, 4 for sit, 4 for walk and 5 for groom. Because the actions are sampled and the hidden state carries over from one decision to the next, the cat never plays the same day twice.</p>
HTML],
                ['Where do 1,194 weights come from?', <<<'HTML'
<p>A GRU has three blocks, the update gate, the reset gate and the candidate. Each has 12 × 18 input weights, 12 × 12 recurrent weights and 12 biases, which is 216 + 144 + 12 = 372. Three blocks make 1,116. The read-out adds 6 × 12 weights and 6 biases, which is 78. In total 1,116 + 78 = 1,194.</p>
HTML],
                ['How was it trained?', <<<'HTML'
<p>Offline, with evolution strategies, in a simulator that runs the same <code>catbrain.js</code> file as the game. Candidates were rewarded for keeping the cat rested and comfortable: sleeping on the cushion, getting up when restless, stretching its legs and coming home when tired. The shipped weights are the result of 400 generations, ending at a reward of 0.028 per decision. Nobody wrote a schedule for the cat; the trainer lives in <code>dev/scripts/train-cat-brain.mjs</code>, and running it with <code>--eval</code> reports how the shipped weights behave.</p>
HTML],
                ['What does the network remember?', <<<'HTML'
<p>Only its hidden state: 12 numbers that carry from one decision to the next, so a choice can depend on the recent past and not only on the moment. It does not learn while you play; the browser runs the trained network forward and nothing else.</p>
HTML],
            ],
            'faq' => [
                ['How big is the cat\'s brain?', 'A GRU with 12 hidden units and 1,194 weights: 4,776 bytes of 32-bit floats, shipped as base64 in a 6,592-byte JavaScript file.'],
                ['Does the cat learn while I play?', 'No. It was trained offline with evolution strategies. In the browser the trained network only runs forward, twice a second.'],
                ['Does the cat do the same thing every time?', 'No. Actions are sampled from the network\'s scores and its hidden state carries over, so behaviour never repeats exactly.'],
            ],
        ],
    ],
];
