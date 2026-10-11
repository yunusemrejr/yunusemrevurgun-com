/* The site's skin for anatomy figures.
 *
 * The kit ships a neutral "light" token set (paper, ink, one lit accent). Figures on this site use the
 * same tokens remapped to the Jelly palette from assets/css/variables.css, so a figure reads as part
 * of the page instead of a pasted-in diagram:
 *
 *   paper / tops   cream  #fbf8f4 / white      the surfaces of the parts
 *   shades 0..3    navy-tinted greys           the four facing tones (three percent apart in luminance)
 *   hi, mid, lo    navy #262e4d, #586390, #8e9ac0, faint #c3cce4
 *   lit            jelly red #db1816           the one part the reader should watch
 *   halo           navy at 28 %                the soft shadow under the base
 *
 * Figures are written as standalone SVG files and embedded with <img>, so the CSS below travels inside
 * each file. Motion inside a figure is limited to discrete opacity steps (LED blinks), which repaint a
 * handful of times a second instead of every frame, and it stops under prefers-reduced-motion.
 */
import { ISO_CSS } from "./kit/iso-kit.mjs";

export const SITE_TOKENS = `
.iso[data-theme="light"]{
  --anatomy-paper:#fbf8f4;--anatomy-face:#ffffff;--anatomy-top:#ffffff;
  --anatomy-shade-0:#c9d2e8;--anatomy-shade-1:#d9e0f0;--anatomy-shade-2:#e7ebf5;--anatomy-shade-3:#f3f5fb;
  --anatomy-lit-top:#ffffff;--anatomy-lit-shade:#eef1f9;
  --anatomy-hi:#262e4d;--anatomy-mid:#586390;--anatomy-lo:#8e9ac0;--anatomy-faint:#c3cce4;--anatomy-lit:#db1816;
  --anatomy-halo:#1b2140;--anatomy-halo-opacity:.3;--anatomy-dot:#262e4d;
  --anatomy-red:#db1816;--anatomy-green:#1c7448;--anatomy-blue:#2a6fc0;
}
.iso-svg .iso-line{stroke-width:.7px}
.rk-led{stroke:none}
.rk-led--ok{fill:var(--anatomy-green)}
.rk-led--err{fill:var(--anatomy-red)}
.rk-led--dim{fill:var(--anatomy-faint)}
.rk-lcd{fill:var(--anatomy-hi);stroke:none}
.rk-lcd-bar{fill:none;stroke:var(--anatomy-paper);stroke-width:.9px;stroke-linecap:round;opacity:.92}
.rk-well{fill:var(--anatomy-shade-1);stroke:none}
.rk-slot{fill:var(--anatomy-hi);stroke:none;opacity:.85}
.rk-lens--red{--anatomy-paper:#f4cdcb;--anatomy-top:#fae3e1;--anatomy-shade-0:#e3a9a6;--anatomy-shade-1:#ebbcb9;--anatomy-shade-2:#f1cac8;--anatomy-shade-3:#f6d9d7}
.rk-lens--amber{--anatomy-paper:#f4e3b6;--anatomy-top:#faf0d2;--anatomy-shade-0:#e0c985;--anatomy-shade-1:#e9d6a0;--anatomy-shade-2:#f0dfb2;--anatomy-shade-3:#f6e9c8}
.rk-lens--green{--anatomy-paper:#cfe6d8;--anatomy-top:#e3f3ea;--anatomy-shade-0:#9fc7b0;--anatomy-shade-1:#b4d6c2;--anatomy-shade-2:#c5e0d1;--anatomy-shade-3:#d6eadf}
.rk-glow{fill:var(--anatomy-green);stroke:none;mix-blend-mode:multiply;opacity:.0}
.rk-glow--red{fill:var(--anatomy-red)}
@keyframes rk-blink{0%,100%{opacity:.28}8%,52%{opacity:1}}
@keyframes rk-glow{0%,100%{opacity:0}8%,52%{opacity:.55}}
@keyframes rk-blink-slow{0%,100%{opacity:.3}12%,44%{opacity:1}}
.rk-led[data-blink]{animation:rk-blink 1800ms step-end infinite;animation-delay:var(--at,0ms)}
.rk-glow[data-blink]{animation:rk-glow 1800ms step-end infinite;animation-delay:var(--at,0ms)}
.rk-led[data-blink="slow"]{animation:rk-blink-slow 2900ms step-end infinite}
@media (prefers-reduced-motion:reduce){.rk-led[data-blink],.rk-glow[data-blink]{animation:none}.rk-glow{opacity:.5}}
`;

export const SITE_CSS = ISO_CSS + SITE_TOKENS;
