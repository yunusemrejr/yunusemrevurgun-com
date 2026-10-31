<?php
/**
 * The homepage masthead's figure: the owner, photographed in front of a pine stand with one birch
 * gone gold, mounted as a field plate.
 *
 * The photograph is the LCP element: one 738 px WebP (JPEG fallback through <picture>), never lazy,
 * painted at full opacity from the first frame. One file, not a srcset, so the Early Hints preload
 * (which cannot carry a srcset) names exactly the file the <img> uses: 738 px covers a 400 px slot
 * at 1.8x and the 320 px phone slot at 2.3x. Everything around it is
 * drafting furniture drawn in CSS (assets/css/hero.css): the plate, a scale down its left edge and
 * three callouts whose leader lines draw themselves once (CSS only, skipped under reduced motion
 * and on data-motion="lite" devices). The callouts name what is in the picture; they carry no
 * invented measurements. Crops are built by dev/scripts/build-field-assets.py.
 */
$portraitBase = FULL_BASE_PATH . 'assets/images/field/';
$portraitV = static fn(string $f): string => $portraitBase . $f . '?v=' . (@filemtime(dirname(__DIR__, 2) . '/assets/images/field/' . $f) ?: 1);
?>
<figure class="ui-masthead-figure fp-plate">
    <span class="fp-corner" data-corner="fig" aria-hidden="true">Fig. 0</span>
    <span class="fp-corner" data-corner="title" aria-hidden="true">Field portrait</span>
    <div class="fp-frame">
        <span class="fp-scale" aria-hidden="true"></span>
        <picture>
            <source type="image/webp" srcset="<?= $portraitV('portrait-738.webp') ?>">
            <img class="fp-photo" src="<?= $portraitV('portrait-738.jpg') ?>" width="738" height="1107" decoding="async" fetchpriority="high"
                 alt="Yunus Emre Vurgun standing on a gravel verge in a grey hoodie, jeans and a white cap, in front of a dark stand of pines with one birch turned gold above them, under an overcast sky.">
        </picture>
        <div class="fp-callouts" aria-hidden="true">
            <span class="fp-callout" data-side="right" style="--x:30%;--y:5%;--len:34px"><b>Birch, autumn</b></span>
            <span class="fp-callout" data-side="left" style="--x:78%;--y:27%;--len:30px"><b>Pine stand</b></span>
            <span class="fp-callout" data-side="right" style="--x:55%;--y:46%;--len:44px"><b>Y. E. Vurgun</b></span>
        </div>
    </div>
    <figcaption class="fp-caption">A pine stand, one birch in its autumn colour, an overcast sky.</figcaption>
</figure>
