<?php
/**
 * Auto-generated visuals shared by /rmrp, /post-code and /science-corner.
 *
 * Nothing here is stored: a topic is classified from the text itself, and the
 * cover art is drawn deterministically from a seed (entry id or title), so a
 * new entry gets a unique, on-brand illustration the moment it is published.
 * Colours come from CSS custom properties (.vis--<motif> in knowledge.css /
 * rmrp.css) so the art follows the jelly palette.
 */

if (!function_exists('vis_topics')) {
    /** Motif key => [label, keyword list]. Order is also the tie-break order. */
    function vis_topics(): array
    {
        return [
            'space'    => ['Space',     ['black hole', 'star', 'galax', 'nasa', 'telescope', 'planet', 'orbit', 'solar', 'cosmic', 'neutron', 'universe', 'mars', 'moon', 'hubble', 'webb', 'astronom', 'centauri', 'comet', 'asteroid', 'nebula', 'supernova']],
            'security' => ['Security',   ['vulnerab', 'breach', 'cryptograph', 'attack', 'exploit', 'encrypt', 'credential', 'malware', 'cve', 'zero-day', 'side-channel', 'ransom', 'phishing', 'nist', 'cipher', 'authentication', 'private key']],
            'ai'       => ['AI & ML',    ['neural', 'model', 'training', 'llm', 'transformer', 'agent', 'distill', 'reinforcement', 'behavior cloning', 'machine learning', 'deep learning', 'inference', 'gradient', 'artificial intelligence', ' ai ', 'embedding']],
            'hardware' => ['Hardware',   ['chip', 'cpu', 'gpu', 'amd', 'apple', 'transistor', 'silicon', 'processor', 'semiconductor', 'arm ', 'cache', 'dram', 'nanometer', 'fpga', 'circuit']],
            'earth'    => ['Earth',      ['rock', 'crust', 'ocean', 'volcan', 'earthquake', 'climate', 'geolog', 'fossil', 'glacier', 'mantle', 'tectonic', 'bermuda', 'seafloor', 'atmosphere']],
            'software' => ['Software',   ['linux', 'python', 'kernel', 'compiler', 'dns', 'gps', 'network', 'protocol', 'software', 'code', 'database', 'api', 'rust', 'javascript', 'open-source', 'git ', 'operating system', 'algorithm']],
            'physics'  => ['Physics',    ['quantum', 'photon', 'particle', 'physic', 'relativity', 'entropy', 'superconduct', 'electron', 'magnet', 'wave', 'energy', 'laser']],
            'life'     => ['Life',       ['cell', 'dna', 'gene', 'brain', 'species', 'protein', 'biolog', 'virus', 'evolution', 'neuron', 'organism', 'bacteri']],
            'math'     => ['Math',       ['theorem', 'prime', 'proof', 'equation', 'mathemat', 'geometry', 'algebra', 'topology', 'probabilit']],
        ];
    }

    function vis_topic_label(string $key): string
    {
        return vis_topics()[$key][0] ?? 'Notes';
    }

    /** Classify free text into one motif key ('notes' when nothing matches). */
    function vis_classify(string $text): string
    {
        $hay = ' ' . mb_strtolower($text) . ' ';
        $best = 'notes';
        $bestScore = 0;
        foreach (vis_topics() as $key => [, $words]) {
            $score = 0;
            foreach ($words as $w) {
                $score += substr_count($hay, $w);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $key;
            }
        }
        return $best;
    }
}

if (!function_exists('vis_reading_minutes')) {
    function vis_reading_minutes(string $plain): int
    {
        return max(1, (int) ceil(str_word_count($plain) / 220));
    }

    /**
     * Pull figures with a unit out of the text ("37.2%", "12 miles", "94 years")
     * so they can be shown as big callouts. Bare years are ignored.
     *
     * @return list<array{value:string,unit:string}>
     */
    function vis_key_numbers(string $plain, int $max = 4): array
    {
        $units = '%|percent|times|million|billion|trillion|thousand|years?|miles?|feet|foot|km|kilometers?|meters?|metres?|minutes?|seconds?|hours?|days?|weeks?|months?|GB|MB|TB|PB|GHz|MHz|nm|bits?|bytes?|solar masses|light-years?|degrees?|tons?|kg';
        if (!preg_match_all('/(?<![\w.])(\d[\d,]*(?:\.\d+)?)\s?(' . $units . ')(?![a-z])/iu', $plain, $m, PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($m as $hit) {
            $unit = strtolower($hit[2]);
            if ($unit === 'percent') $unit = '%';
            $key = $hit[1] . $unit;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['value' => $hit[1], 'unit' => $unit];
            if (count($out) >= $max) break;
        }
        return $out;
    }

    /** First sentence, trimmed — used as the pull-quote/takeaway. */
    function vis_first_sentence(string $plain, int $limit = 220): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', $plain));
        if (preg_match('/^(.{20,}?[.!?])(\s|$)/us', $plain, $m)) {
            $plain = $m[1];
        }
        return mb_strlen($plain) > $limit ? rtrim(mb_substr($plain, 0, $limit - 1)) . '…' : $plain;
    }
}

if (!function_exists('vis_cover')) {
    /**
     * Deterministic cover art for a motif. The SVG is decorative (aria-hidden)
     * and stretches to its container; colours are set by the .vis--<motif>
     * classes. Same seed => same picture.
     */
    function vis_cover(string $motif, string|int $seed, string $class = ''): string
    {
        $n = is_int($seed) ? $seed : crc32($seed);
        mt_srand($n);
        $r = static fn(int $a, int $b): int => mt_rand($a, $b);
        $W = 640; $H = 260;
        $g = '';

        switch ($motif) {
            case 'space':
                for ($i = 0; $i < 46; $i++) {
                    $g .= sprintf('<circle cx="%d" cy="%d" r="%.1f" class="v-fg" opacity="%.2f"/>', $r(0, $W), $r(0, $H), $r(3, 16) / 10, $r(3, 9) / 10);
                }
                $cx = $r(200, 440); $cy = $r(90, 170);
                for ($i = 1; $i <= 3; $i++) {
                    $g .= sprintf('<ellipse cx="%d" cy="%d" rx="%d" ry="%d" transform="rotate(%d %d %d)" class="v-line" fill="none"/>', $cx, $cy, 50 + $i * 42, 18 + $i * 16, $r(-25, 25), $cx, $cy);
                }
                $g .= sprintf('<circle cx="%d" cy="%d" r="%d" class="v-hi"/>', $cx, $cy, $r(22, 34));
                $g .= sprintf('<circle cx="%d" cy="%d" r="%d" class="v-fg"/>', $cx + $r(70, 140), $cy - $r(20, 60), $r(5, 9));
                break;

            case 'security':
                for ($y = 20; $y < $H; $y += 26) {
                    $row = '';
                    for ($x = 0; $x < 34; $x++) $row .= $r(0, 1);
                    $g .= sprintf('<text x="8" y="%d" class="v-code" opacity="%.2f">%s</text>', $y, $r(12, 30) / 100, $row);
                }
                $lx = $r(150, 420);
                switch ($n % 3) {
                    case 0: // padlock
                        $g .= sprintf('<path d="M%d 120 v-34 a46 46 0 0 1 92 0 v34" class="v-line thick" fill="none"/>', $lx);
                        $g .= sprintf('<rect x="%d" y="112" width="124" height="98" rx="22" class="v-hi"/>', $lx - 16);
                        $g .= sprintf('<circle cx="%d" cy="152" r="11" class="v-bg"/><rect x="%d" y="156" width="8" height="26" rx="4" class="v-bg"/>', $lx + 46, $lx + 42);
                        break;
                    case 1: // shield with a check
                        $g .= sprintf('<path d="M%d 40 l70 24 v56 c0 46 -30 74 -70 92 c-40 -18 -70 -46 -70 -92 v-56 z" class="v-hi"/>', $lx + 46);
                        $g .= sprintf('<path d="M%d 136 l22 22 l44 -48" class="v-line thick" fill="none" stroke="var(--vb)"/>', $lx + 6);
                        break;
                    default: // key
                        $g .= sprintf('<circle cx="%d" cy="130" r="38" class="v-hi"/><circle cx="%d" cy="130" r="14" class="v-bg"/>', $lx, $lx);
                        $g .= sprintf('<rect x="%d" y="122" width="150" height="16" rx="8" class="v-hi"/><rect x="%d" y="138" width="14" height="24" rx="4" class="v-hi"/><rect x="%d" y="138" width="14" height="34" rx="4" class="v-hi"/>', $lx + 30, $lx + 120, $lx + 96);
                }
                break;

            case 'ai':
                $layers = [3, 5, 6, 4, 2];
                $pts = [];
                foreach ($layers as $li => $count) {
                    $x = 70 + $li * 125;
                    for ($k = 0; $k < $count; $k++) {
                        $pts[$li][] = [$x, 30 + ($k + 0.5) * (($H - 60) / $count)];
                    }
                }
                for ($li = 0; $li < count($layers) - 1; $li++) {
                    foreach ($pts[$li] as $a) foreach ($pts[$li + 1] as $b) {
                        $g .= sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" class="v-line" opacity="0.35"/>', $a[0], $a[1], $b[0], $b[1]);
                    }
                }
                foreach ($pts as $li => $col) foreach ($col as $p) {
                    $hot = $r(0, 3) === 0;
                    $g .= sprintf('<circle cx="%d" cy="%d" r="%d" class="%s"/>', $p[0], $p[1], $hot ? 11 : 8, $hot ? 'v-hi' : 'v-fg');
                }
                break;

            case 'hardware':
                for ($i = 0; $i < 14; $i++) {
                    $x = $r(0, $W); $y = $r(0, $H);
                    $x2 = $x + $r(-140, 140); $y2 = $y + $r(-60, 60);
                    $g .= sprintf('<polyline points="%d,%d %d,%d %d,%d" class="v-line" fill="none"/><circle cx="%d" cy="%d" r="4" class="v-fg"/>', $x, $y, $x2, $y, $x2, $y2, $x2, $y2);
                }
                $g .= '<rect x="240" y="80" width="160" height="100" rx="14" class="v-hi"/>';
                for ($i = 0; $i < 6; $i++) {
                    $g .= sprintf('<rect x="%d" y="70" width="8" height="12" class="v-fg"/><rect x="%d" y="178" width="8" height="12" class="v-fg"/>', 256 + $i * 24, 256 + $i * 24);
                }
                $g .= '<rect x="274" y="112" width="92" height="36" rx="8" class="v-bg" opacity="0.55"/>';
                break;

            case 'earth':
                $bands = 6;
                for ($i = 0; $i < $bands; $i++) {
                    $y = 70 + $i * 34; $amp = $r(8, 22); $ph = $r(0, 60);
                    $d = "M0 $H L0 $y";
                    for ($x = 0; $x <= $W; $x += 40) {
                        $d .= sprintf(' Q%d %d %d %d', $x + 20, $y + (($x / 40 + $ph) % 2 ? $amp : -$amp), $x + 40, $y);
                    }
                    $d .= " L$W $H Z";
                    $g .= sprintf('<path d="%s" class="v-band%d"/>', $d, $i % 3);
                }
                $g .= sprintf('<circle cx="%d" cy="58" r="26" class="v-hi"/>', $r(70, 560));
                break;

            case 'software':
                $g .= '<text x="320" y="180" text-anchor="middle" class="v-big">&lt;/&gt;</text>';
                for ($i = 0; $i < 7; $i++) {
                    $g .= sprintf('<rect x="%d" y="%d" width="%d" height="12" rx="6" class="%s" opacity="0.85"/>', 40 + $r(0, 2) * 30, 24 + $i * 32, $r(90, 300), $i % 3 === 1 ? 'v-hi' : 'v-fg');
                }
                break;

            case 'physics':
                $s1 = [$r(120, 220), $r(70, 190)]; $s2 = [$r(400, 520), $r(70, 190)];
                foreach ([$s1, $s2] as $s) for ($k = 1; $k <= 7; $k++) {
                    $g .= sprintf('<circle cx="%d" cy="%d" r="%d" class="v-line" fill="none" opacity="%.2f"/>', $s[0], $s[1], $k * 26, 0.7 - $k * 0.07);
                }
                $g .= sprintf('<circle cx="%d" cy="%d" r="9" class="v-hi"/><circle cx="%d" cy="%d" r="9" class="v-hi"/>', $s1[0], $s1[1], $s2[0], $s2[1]);
                break;

            case 'life':
                $d1 = 'M0 130'; $d2 = 'M0 130'; $rungs = '';
                for ($x = 0; $x <= $W; $x += 8) {
                    $y1 = 130 + sin(($x + $n % 90) / 46) * 62;
                    $y2 = 130 - sin(($x + $n % 90) / 46) * 62;
                    $d1 .= sprintf(' L%d %.1f', $x, $y1);
                    $d2 .= sprintf(' L%d %.1f', $x, $y2);
                    if ($x % 32 === 0) $rungs .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="v-line" opacity="0.5"/>', $x, $y1, $x, $y2);
                }
                $g .= $rungs . '<path d="' . $d1 . '" class="v-line thick" fill="none"/><path d="' . $d2 . '" class="v-hi-line thick" fill="none"/>';
                break;

            case 'math':
                for ($x = 0; $x <= $W; $x += 40) $g .= sprintf('<line x1="%d" y1="0" x2="%d" y2="%d" class="v-line" opacity="0.14"/>', $x, $x, $H);
                for ($y = 0; $y <= $H; $y += 40) $g .= sprintf('<line x1="0" y1="%d" x2="%d" y2="%d" class="v-line" opacity="0.14"/>', $y, $W, $y);
                $g .= sprintf('<line x1="0" y1="130" x2="%d" y2="130" class="v-line"/>', $W);
                $f = $r(18, 34) / 10; $amp = $r(50, 80); $d = '';
                for ($x = 0; $x <= $W; $x += 6) $d .= ($d ? ' L' : 'M') . sprintf('%d %.1f', $x, 130 - sin($x / (55 / ($f / 2))) * $amp);
                $g .= '<path d="' . $d . '" class="v-hi-line thick" fill="none"/>';
                for ($i = 0; $i < 6; $i++) $g .= sprintf('<circle cx="%d" cy="%d" r="5" class="v-fg"/>', $r(20, 620), $r(30, 230));
                break;

            default: // notes
                for ($i = 0; $i < 7; $i++) {
                    $g .= sprintf('<circle cx="%d" cy="%d" r="%d" class="%s" opacity="%.2f"/>', $r(0, $W), $r(0, $H), $r(30, 90), $i % 3 === 0 ? 'v-hi' : 'v-fg', $r(25, 60) / 100);
                }
        }

        return '<svg class="vis vis--' . htmlspecialchars($motif) . ($class !== '' ? ' ' . htmlspecialchars($class) : '')
            . '" viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">'
            . '<rect width="' . $W . '" height="' . $H . '" class="v-bg"/>' . $g . '</svg>';
    }
}
