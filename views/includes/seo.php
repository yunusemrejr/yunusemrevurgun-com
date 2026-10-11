<?php
/**
 * Structured-data helpers for the project hubs and their documentation pages.
 *
 * Every entity below was looked up on the live Wikidata API on 2026-10-03 and
 * matched on its description, not on its label alone ("Stockfish" alone resolves
 * to a surname and to dried cod first). Add an entry only after checking it the
 * same way; a wrong Q-id is worse than a missing one because it tells search
 * engines the page is about something else.
 */

if (!function_exists('seo_origin')) {
    function seo_origin(): string
    {
        return rtrim(FULL_BASE_PATH, '/');
    }
}

if (!function_exists('seo_wikidata')) {
    /** @return array<string, array{0: string, 1: string}> key => [label, Wikidata Q-id] */
    function seo_wikidata(): array
    {
        static $map = [
            // chess
            'stockfish'           => ['Stockfish', 'Q1775686'],              // open source chess engine
            'chess_engine'        => ['Chess engine', 'Q1248874'],           // computer program for chess analysis and game
            'alpha_beta'          => ['Alpha–beta pruning', 'Q570496'],
            'negamax'             => ['Negamax', 'Q3874238'],
            'transposition_table' => ['Transposition table', 'Q7835333'],
            'uci'                 => ['Universal Chess Interface', 'Q1772684'],
            'knn'                 => ['k-nearest neighbors algorithm', 'Q1071612'],
            'logistic_regression' => ['Logistic regression', 'Q1132755'],    // "logistic regression model"
            'bandit'              => ['Multi-armed bandit', 'Q2882343'],
            // web platform
            'webassembly'         => ['WebAssembly', 'Q20155677'],
            'web_worker'          => ['Web worker', 'Q7978628'],
            'webgpu'              => ['WebGPU', 'Q28957081'],
            'javascript'          => ['JavaScript', 'Q2005'],
            'nodejs'              => ['Node.js', 'Q756100'],
            'php'                 => ['PHP', 'Q59'],
            // machine learning and language
            'chatbot'             => ['Chatbot', 'Q870780'],
            'ann'                 => ['Artificial neural network', 'Q192776'],
            'rnn'                 => ['Recurrent neural network', 'Q1457734'],
            'gru'                 => ['Gated recurrent unit', 'Q25325415'],
            'relu'                => ['Rectifier (activation function)', 'Q7303176'],
            'softmax'             => ['Softmax function', 'Q7554146'],
            'naive_bayes'         => ['Naive Bayes classifier', 'Q812530'],
            'bm25'                => ['Okapi BM25', 'Q2068750'],
            'nlp'                 => ['Natural language processing', 'Q30642'],
            'text_classification' => ['Text classification', 'Q102190569'],
            'rl'                  => ['Reinforcement learning', 'Q830687'],
            'evolution_strategy'  => ['Evolution strategy', 'Q2912857'],
            'llm'                 => ['Large language model', 'Q115305900'],
            'gemma'               => ['Gemma (Google language models)', 'Q124629757'], // the family; Gemma 3 has no item of its own
            'gguf'                => ['GGUF', 'Q127427530'],
            'llama_cpp'           => ['llama.cpp', 'Q125998452'],
            'ollama'              => ['Ollama', 'Q124636097'],
            'hugging_face'        => ['Hugging Face', 'Q108943604'],
            'mcp'                 => ['Model Context Protocol', 'Q133436854'],
            // reasoning
            'fact_checking'       => ['Fact-checking', 'Q59555084'],
            'argument'            => ['Argument', 'Q186619'],
            'premise'             => ['Premise', 'Q321703'],
            'confounding'         => ['Confounding', 'Q1125472'],
            'falsifiability'      => ['Falsifiability', 'Q220888'],
            // software and platforms
            'c_lang'              => ['C (programming language)', 'Q15777'],
            'cpp'                 => ['C++', 'Q2407'],
            'rust'                => ['Rust (programming language)', 'Q575650'],
            'linux'               => ['Linux', 'Q388'],
            'gtk'                 => ['GTK', 'Q189464'],
            'sdl'                 => ['Simple DirectMedia Layer', 'Q727439'],
            'wayland'             => ['Wayland', 'Q14561'],
            'x11'                 => ['X Window System', 'Q178481'],
            'disk_analyzer'       => ['Disk space analyzer', 'Q2845269'],
            // genres
            'rts'                 => ['Real-time strategy', 'Q208189'],
            'shmup'               => ["Shoot 'em up", 'Q1044478'],
            'alife'               => ['Artificial life', 'Q263847'],
        ];
        return $map;
    }
}

if (!function_exists('seo_thing')) {
    /** A schema.org Thing that points at its Wikidata item, for `about` / `mentions`. */
    function seo_thing(string $key): ?array
    {
        $map = seo_wikidata();
        if (!isset($map[$key])) return null;
        [$name, $qid] = $map[$key];
        return ['@type' => 'Thing', 'name' => $name, '@id' => 'https://www.wikidata.org/entity/' . $qid, 'sameAs' => 'https://www.wikidata.org/wiki/' . $qid];
    }
}

if (!function_exists('seo_things')) {
    function seo_things(array $keys): array
    {
        return array_values(array_filter(array_map('seo_thing', $keys)));
    }
}

if (!function_exists('seo_person')) {
    /** The site owner, referenced by the @id the home page defines. */
    function seo_person(): array
    {
        return ['@type' => 'Person', '@id' => seo_origin() . '/#person', 'name' => 'Yunus Emre Vurgun', 'url' => seo_origin() . '/about'];
    }
}

if (!function_exists('seo_breadcrumbs')) {
    /** @param list<array{0: string, 1: string}> $trail [label, absolute url] */
    function seo_breadcrumbs(array $trail): array
    {
        $items = [];
        foreach ($trail as $i => [$name, $url]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
}

if (!function_exists('seo_faq')) {
    /** @param list<array{0: string, 1: string}> $qa Plain-text answers; each must also be visible on the page. */
    function seo_faq(array $qa): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn(array $q): array => [
                '@type' => 'Question',
                'name' => $q[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]],
            ], $qa),
        ];
    }
}

if (!function_exists('seo_script')) {
    /** One JSON-LD <script> for a list of graph nodes. */
    function seo_script(array $nodes): string
    {
        return '<script type="application/ld+json">' . json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($nodes)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ) . '</script>';
    }
}

if (!function_exists('seo_plain')) {
    /** Visible-text version of an HTML fragment, for schema text fields and word counts. */
    function seo_plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>', '<br/>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/', ' ', $text));
    }
}

if (!function_exists('seo_description')) {
    /**
     * A meta description from free text: tags and entities resolved, whitespace collapsed, then cut at the
     * last sentence end that leaves at least half the budget, else at a word boundary with an ellipsis.
     * Replaces blind mb_substr() cuts that ended mid-word and ran paragraphs together.
     */
    function seo_description(string $text, int $max = 155): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $floor = (int)floor($max * 0.5);
        if (preg_match('/^(.{' . $floor . ',}[.!?])(?=\s|$)/us', $cut, $m)) {
            return $m[1];
        }
        $cut = (string)preg_replace('/\s+\S*$/u', '', $cut);
        return rtrim($cut, " \t,;:—-") . '…';
    }
}

if (!function_exists('seo_asset_url')) {
    /** A stored media path as an absolute URL: kept as is when it already is one, else made site-relative. */
    function seo_asset_url(string $path): string
    {
        return preg_match('#^https?://#i', $path) ? $path : FULL_BASE_PATH . ltrim($path, '/');
    }
}

if (!function_exists('seo_auto_breadcrumbs')) {
    /**
     * A BreadcrumbList for a plain inner page, from its path: Home > Section [> this page]. Pages that
     * build their own trail (hubs, docs) never reach this. $title is the page <title>; its brand suffix is
     * dropped for the last crumb.
     */
    function seo_auto_breadcrumbs(string $path, string $title): ?array
    {
        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }
        $names = [
            'about' => 'About', 'portfolio' => 'Portfolio', 'gallery' => 'Gallery', 'blog' => 'Blog', 'travel' => 'Travel',
            'updates' => 'Updates', 'contact' => 'Contact', 'more' => 'Explore', 'rmrp' => 'Random Memories', 'sitemap' => 'Sitemap',
            'privacy' => 'Privacy policy', 'terms' => 'Terms of service', 'cookies' => 'Cookie policy', 'music' => 'Music',
            'videos' => 'Videos', 'comedy' => 'Comedy', 'slop' => 'Quality 3D AI Slop', 'downloads' => 'Downloads',
            'post-code' => 'Post-Code', 'science-corner' => 'Science Corner',
        ];
        $segments = explode('/', $path);
        $origin = seo_origin();
        $trail = [['Home', $origin . '/']];
        $url = $origin;
        foreach ($segments as $i => $segment) {
            $url .= '/' . $segment;
            $last = $i === count($segments) - 1;
            if ($i === 0) {
                $label = $names[$segment] ?? ucwords(str_replace('-', ' ', $segment));
            } else {
                $label = $last ? trim((string)preg_split('/\s+[—|–]\s+/u', $title)[0]) : ucwords(str_replace('-', ' ', $segment));
            }
            $trail[] = [$label !== '' ? $label : ucwords(str_replace('-', ' ', $segment)), $url];
        }
        return seo_breadcrumbs($trail);
    }
}

