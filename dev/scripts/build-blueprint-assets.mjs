#!/usr/bin/env node
/* Writes the small drafting-furniture SVG tiles used by assets/css/blueprint.css:
 *
 *   ruler-up.svg    160x14  ticks rising from a baseline, on the sky band's lower edge
 *   ruler-down.svg  160x14  the same, hanging from the footer's top edge, in the pale sky tint
 *   grid.svg        120x120 drafting grid: a minor line every 24 px, a major line every 120 px
 *   crosshair.svg   28x28   registration mark for the sheet corners
 *   arrow.svg       16x16   the button arrow, used as a CSS mask so it takes the label colour
 *
 * Tick spacing follows the scale on a steel rule: a short tick every 8 px, a taller one every 40 px
 * and the tallest at the tile edge (every 160 px). Colours are literal because SVG used as a CSS
 * background cannot read custom properties; they are the brand navy #262e4d and sky-soft #c9e0fc.
 * Run: node dev/scripts/build-blueprint-assets.mjs
 */
import { writeFileSync, mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const OUT = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "assets", "images", "blueprint");
mkdirSync(OUT, { recursive: true });
const NAVY = "#262e4d";
const PALE = "#c9e0fc";

function ruler(up, colour) {
  const W = 160;
  const H = 14;
  const base = up ? H - 0.75 : 0.75;
  const dir = up ? -1 : 1;
  const tick = (x, len, w, o) => `<path d="M${x} ${base}V${(base + dir * len).toFixed(2)}" stroke-width="${w}" opacity="${o}"/>`;
  const parts = [];
  for (let x = 8; x < W; x += 8) {
    if (x % 40 === 0) parts.push(tick(x + 0.5, 7, 1, up ? 0.55 : 0.7));
    else parts.push(tick(x + 0.5, 4, 1, up ? 0.4 : 0.5));
  }
  parts.push(tick(0.5, 12, 1.2, up ? 0.7 : 0.85));
  const line = `<path d="M0 ${base}H${W}" stroke-width="1.5" opacity="${up ? 0.6 : 0.8}"/>`;
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><g fill="none" stroke="${colour}" shape-rendering="crispEdges">${line}${parts.join("")}</g></svg>\n`;
}

function grid() {
  const S = 120;
  const lines = [];
  for (let i = 24; i < S; i += 24) {
    lines.push(`<path d="M${i + 0.5} 0V${S}M0 ${i + 0.5}H${S}" opacity=".07"/>`);
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${S}" height="${S}" viewBox="0 0 ${S} ${S}"><g fill="none" stroke="${NAVY}" stroke-width="1" shape-rendering="crispEdges">${lines.join("")}<path d="M0.5 0V${S}M0 0.5H${S}" opacity=".12"/></g></svg>\n`;
}

function crosshair() {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28"><g fill="none" stroke="${NAVY}" stroke-width="1.2" opacity=".42"><circle cx="14" cy="14" r="6.5"/><path d="M14 1v9M14 18v9M1 14h9M18 14h9" stroke-linecap="butt"/></g></svg>\n`;
}

function arrow() {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path d="M1.5 8h12M9 3.5 13.5 8 9 12.5" fill="none" stroke="#000" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>\n`;
}

writeFileSync(join(OUT, "ruler-up.svg"), ruler(true, NAVY));
writeFileSync(join(OUT, "ruler-down.svg"), ruler(false, PALE));
writeFileSync(join(OUT, "grid.svg"), grid());
writeFileSync(join(OUT, "crosshair.svg"), crosshair());
writeFileSync(join(OUT, "arrow.svg"), arrow());
console.log("wrote 5 tiles to", OUT);
