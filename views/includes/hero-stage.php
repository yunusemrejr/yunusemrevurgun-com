<?php
/**
 * The homepage masthead's figure: Jell-omo on a drawing bench.
 *
 * Three layers share one 800 x 800 coordinate space (the mascot cutout's own pixels), so they
 * register exactly:
 *   1. the render (<img>, the LCP element, always in the DOM at its final size)
 *   2. the sketch: construction lines, ink linework and flat colour traced from the render
 *      (dev/scripts/build-jelly-sketch.py -> views/includes/jelly-sketch.html). On load it draws
 *      itself, fills with flat colour, then dissolves into the render (assets/css/hero.css).
 *   3. the instrument: a dial indicator on a stand whose probe rests on the cap's button. Poking the
 *      mascot drives a damped spring (assets/js/hero.js); the same spring moves the jelly, the probe
 *      tip and the needle, and writes the readout, so every number on the dial is the real motion.
 *
 * Without JavaScript the intro still plays (pure CSS) and the figure is a static, correct picture.
 * Everything here is decoration for assistive tech except the poke button and the replay button.
 */
$stageMascot = $mascotUrl ?? (FULL_BASE_PATH . 'assets/images/mascot-640.webp');
$sketchFile = __DIR__ . '/jelly-sketch.html';
?>
<noscript><style>.jx-replay,.jx-poke{display:none}</style></noscript>
<figure class="ui-masthead-figure jx-stage" data-stage data-intro="on">
    <div class="jx-frame">
        <div class="jx-ring" aria-hidden="true">
            <svg class="jx-ring-ticks" viewBox="-250 -250 500 500" focusable="false">
                <circle class="jx-rim" r="238"/>
                <circle class="jx-t5" r="230" pathLength="360"/>
                <circle class="jx-t30" r="226" pathLength="360"/>
                <circle class="jx-dash" r="210"/>
            </svg>
            <svg class="jx-ring-index" viewBox="-250 -250 500 500" focusable="false">
                <path d="M-7.5-248h15L0-231z"/>
            </svg>
        </div>

        <div class="jx-body">
            <img class="ui-mascot" src="<?= htmlspecialchars($stageMascot) ?>" width="640" height="640" decoding="async" fetchpriority="high"
                 alt="Jell-omo, the site's mascot: a glossy red jelly wearing a navy and cream baseball cap, eyes closed in a contented smile.">
        </div>

        <?php if (is_file($sketchFile)) readfile($sketchFile); ?>

        <!-- The instrument is two SVGs: the body never changes (so it is painted once), and the needle, probe
             and tip that the spring moves live in a small one of their own, which is all that repaints. -->
        <svg class="jx-gauge" viewBox="0 0 800 800" aria-hidden="true" focusable="false">
            <!-- stand: foot plate and post, standing on the bench line -->
            <path class="jg-bench" d="M-70 776H800"/>
            <rect class="jg-part" x="-40" y="760" width="100" height="16" rx="5"/>
            <rect class="jg-part" x="3" y="166" width="14" height="594" rx="3"/>
            <path class="jg-seam" d="M3 300h14M3 520h14"/>
            <g transform="translate(10 70)">
                <circle class="jg-case" r="98"/>
                <circle class="jg-knurl" r="85"/>
                <circle class="jg-face" r="73"/>
                <!-- scale: 240 degrees, -20 % to +20 % of height, a tick every 2 % and a numbered stop every 10 % -->
                <path class="jg-minor" pathLength="40" d="M-53.7 31A62 62 0 1 1 53.7 31"/>
                <path class="jg-major" d="M-45 26-60 34.6M-45-26-59-34M0-52V-68M45-26 59-34M45 26 60 34.6"/>
                <path class="jg-zero" d="M-8-90h16L0-76z"/>
                <path class="jg-glass" d="M-62-34A70 70 0 0 1-24-66"/>
            </g>
        </svg>
        <svg class="jx-gauge jx-dial" viewBox="0 0 800 800" aria-hidden="true" focusable="false">
            <g transform="translate(10 70)">
                <g class="jx-needle">
                    <path class="jg-needle" d="M0 16V-58"/>
                    <path class="jg-tail" d="M0 0V20"/>
                </g>
                <circle class="jg-hub" r="11"/>
                <circle class="jg-hub-dot" r="4"/>
            </g>
            <!-- probe: from the dial's socket to the cap button; the tip is moved by the spring -->
            <path class="jx-probe" d="M108 70 372 22"/>
            <circle class="jx-probe-tip" cx="372" cy="22" r="6"/>
        </svg>

        <button class="jx-poke" type="button" aria-label="Poke Jell-omo"></button>
    </div>

    <figcaption class="ui-masthead-caption">
        <span>Jell-omo, the mascot. Poke it.</span>
        <a class="ui-btn ui-btn-primary ui-cafe-btn" href="<?= FULL_BASE_PATH ?>jelloshop">
            <svg class="ui-cafe-btn-mug" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4.5 10h11v4.6a4.9 4.9 0 0 1-4.9 4.9h-1.2a4.9 4.9 0 0 1-4.9-4.9z"/><path d="M15.5 11.4h1.3a2.6 2.6 0 0 1 0 5.2h-1.3"/><path class="ui-cafe-btn-steam" d="M8.6 3.4c-1.2 1.1 1.2 2.1 0 3.5M12 3.4c-1.2 1.1 1.2 2.1 0 3.5"/><path d="M3.5 21.6h13"/></svg>
            <span>Come have a coffee</span>
        </a>
        <span class="jx-meta">
            <span class="jx-readout" data-readout>dial: height 0.0 % · 0 pokes</span>
            <button class="jx-replay" type="button" data-replay aria-label="Replay the sketch animation">Redraw</button>
        </span>
    </figcaption>
</figure>
