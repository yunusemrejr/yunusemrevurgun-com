/* =============================================================================
   The room. One cosy café, drawn by hand in a one-point-perspective box the way
   Club Penguin drew its rooms: a wide back wall, narrow side walls, a floor that
   opens towards you, warm lamps, thick dark outlines.

   `paintShell` bakes everything that never moves (walls, floor, rug, the bar,
   the window, the fireplace, the shelves) into one canvas. `paintTop` bakes the
   vignette that goes over the top of the whole scene. The live parts (rain on
   the glass, the fire, the lamp glow, steam, string lights) are the `draw*`
   functions below and run every frame.
   ========================================================================== */

import { P, rng, shape, ell, rect, rrect, rrPts, line, clipped, hatch, handText, groundShadow, lerp, clamp, ellipsePts } from './draw.js';

// ------------------------------------------------------------------ geometry
export const DW = 960;
export const DH = 600;
export const BACK = { x0: 130, y0: 44, x1: 830, y1: 292 };
export const FLOOR_Y = 292;
export const LANES = [340, 396, 456, 522];

export const scaleAt = (y) => 0.6 + ((y - FLOOR_Y) / (DH - FLOOR_Y)) * 0.5;
export const leftX = (y) => BACK.x0 - ((y - FLOOR_Y) / (DH - FLOOR_Y)) * 160;
export const rightX = (y) => BACK.x1 + ((y - FLOOR_Y) / (DH - FLOOR_Y)) * 160;

// Where things are, for the live effects and for the game.
export const WINDOW = { x: 244, y: 74, w: 156, h: 162 };
export const FIREBOX = { x: 712, y: 214, w: 64, h: 78 };
export const MACHINE = { spouts: [[531, 202], [555, 202]] };
export const PENDANTS = [[422, 150], [650, 150]];
export const CAT = { x: 752, y: 338 };
export const RUG = { cx: 480, cy: 462, rx: 262, ry: 66 };

// Wall planes for the two side walls: u runs from the back edge (0) to the
// viewer (1), v from ceiling (0) to floor (1).
function wallPt(side, u, v) {
  const x = side < 0 ? BACK.x0 - u * 130 : BACK.x1 + u * 130;
  const top = BACK.y0 - u * 44;
  const bot = FLOOR_Y + u * 250;
  return [x, lerp(top, bot, v)];
}
const wallQuad = (side, u0, u1, v0, v1) => [wallPt(side, u0, v0), wallPt(side, u1, v0), wallPt(side, u1, v1), wallPt(side, u0, v1)];

// ---------------------------------------------------------------------- shell
export function paintShell(ctx) {
  const R = rng(1957);
  const grain = rng(77);

  ctx.fillStyle = P.woodDeep;
  ctx.fillRect(0, 0, DW, DH);

  paintBackWall(ctx, R);
  paintCeiling(ctx, R);
  paintSideWalls(ctx, R);
  paintFloor(ctx, R);
  paintRug(ctx, R);

  paintDoor(ctx, R);
  paintWindow(ctx, R);
  paintWindowSeat(ctx, R);
  paintBar(ctx, R);
  paintFireplace(ctx, R);
  paintShelves(ctx, R);
  paintLeftWall(ctx, R);
  paintStringWire(ctx, R);

  // Paper grain, so the flats read as painted on something.
  for (let i = 0; i < 5200; i++) {
    const x = grain() * DW;
    const y = grain() * DH;
    ctx.fillStyle = grain() < 0.5 ? 'rgba(255,240,210,0.05)' : 'rgba(60,30,10,0.05)';
    ctx.fillRect(x, y, 1.6, 1.6);
  }
}

function brush(ctx, R, x, y, w, h, colors, n, len = 26) {
  ctx.save();
  ctx.beginPath();
  ctx.rect(x, y, w, h);
  ctx.clip();
  for (let i = 0; i < n; i++) {
    const bx = x + R() * w;
    const by = y + R() * h;
    ctx.strokeStyle = colors[Math.floor(R() * colors.length)];
    ctx.globalAlpha = 0.16 + R() * 0.14;
    ctx.lineWidth = 3 + R() * 6;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(bx, by);
    ctx.quadraticCurveTo(bx + len * 0.5, by + (R() - 0.5) * 6, bx + len * (0.6 + R() * 0.6), by + (R() - 0.5) * 8);
    ctx.stroke();
  }
  ctx.restore();
}

function paintBackWall(ctx, R) {
  const { x0, y0, x1, y1 } = BACK;
  // Plaster, warmest in the middle where the lamps are.
  const g = ctx.createLinearGradient(0, y0, 0, y1);
  g.addColorStop(0, '#e3b077');
  g.addColorStop(0.5, P.plaster);
  g.addColorStop(1, '#e8bd82');
  ctx.fillStyle = g;
  ctx.fillRect(x0, y0, x1 - x0, y1 - y0);
  brush(ctx, R, x0, y0, x1 - x0, y1 - y0, [P.plasterLight, P.plasterShade, '#f4cf98'], 220);

  // Spruce panelling with a wooden rail.
  const wy = 226;
  ctx.fillStyle = P.spruce;
  ctx.fillRect(x0, wy, x1 - x0, y1 - wy);
  brush(ctx, R, x0, wy, x1 - x0, y1 - wy, [P.spruceLight, P.spruceDark], 80, 20);
  for (let x = x0 + 8; x < x1 - 20; x += 62) {
    rrect(ctx, x, wy + 12, 50, y1 - wy - 22, 5, { fill: 'rgba(0,0,0,0)', line: 2.2, lineColor: P.spruceDark, R, j: 0.8 });
    line(ctx, [[x + 3, wy + 15], [x + 3, y1 - 14]], { color: P.spruceLight, w: 2, alpha: 0.7, R, j: 0.6 });
  }
  rect(ctx, x0, wy - 2, x1 - x0, 10, { fill: P.woodLight, line: 2.6, R });
  line(ctx, [[x0 + 2, wy + 1], [x1 - 2, wy + 1]], { color: P.brassLight, w: 1.6, alpha: 0.7, R });
  // Skirting.
  rect(ctx, x0, y1 - 8, x1 - x0, 8, { fill: P.woodDark, line: 2.6, R });

  // Outer wall edges.
  line(ctx, [[x0, y0], [x0, y1]], { w: 3.5, R, j: 0.6 });
  line(ctx, [[x1, y0], [x1, y1]], { w: 3.5, R, j: 0.6 });
}

function paintCeiling(ctx, R) {
  const { x0, y0, x1 } = BACK;
  const pts = [[0, 0], [DW, 0], [x1, y0], [x0, y0]];
  shape(ctx, pts, { fill: '#a86a3a', line: 3.4, R, j: 0.8 });
  clipped(ctx, pts, () => {
    // Boards running towards the back.
    for (let i = 0; i <= 14; i++) {
      const bx = x0 + (i / 14) * (x1 - x0);
      const fx = lerp(-40, DW + 40, i / 14);
      line(ctx, [[fx, 0], [bx, y0]], { color: P.woodDeep, w: 1.6, alpha: 0.5, R, j: 0.5 });
    }
    hatch(ctx, 0, 0, DW, y0, { angle: -0.2, gap: 11, alpha: 0.06, R });
  });
  // Three beams.
  for (const b of [220, 480, 740]) {
    const fb = 480 + (b - 480) * (DW / (x1 - x0));
    shape(ctx, [[b - 13, y0], [b + 13, y0], [fb + 22, -2], [fb - 22, -2]], { fill: P.woodDark, line: 3, R, j: 0.8 });
    line(ctx, [[b - 7, y0 - 2], [fb - 12, 2]], { color: P.woodLight, w: 2.4, alpha: 0.75, R });
  }
}

function paintSideWalls(ctx, R) {
  for (const side of [-1, 1]) {
    const pts = [wallPt(side, 0, 0), wallPt(side, 1, 0), wallPt(side, 1, 1), wallPt(side, 0, 1)];
    // The side walls sit in shade: the plaster is a step darker.
    const g = ctx.createLinearGradient(side < 0 ? 0 : DW, 0, side < 0 ? 130 : DW - 130, 0);
    g.addColorStop(0, '#c98d58');
    g.addColorStop(1, '#e0a86e');
    shape(ctx, pts, { fill: g, line: 3.4, R, j: 0.8, step: 60 });
    clipped(ctx, pts, () => {
      brush(ctx, R, side < 0 ? 0 : DW - 130, 0, 130, 560, ['#f0c48a', '#b8794a'], 90, 18);
      // Panelling.
      const wq = wallQuad(side, 0, 1, 0.74, 1);
      shape(ctx, wq, { fill: P.spruceDark, line: 2.6, R, j: 0.6, step: 60 });
      line(ctx, [wallPt(side, 0, 0.74), wallPt(side, 1, 0.74)], { color: P.woodLight, w: 6, R, j: 0.6 });
      line(ctx, [wallPt(side, 0, 0.74), wallPt(side, 1, 0.74)], { color: P.ink, w: 1.6, R, j: 0.6, alpha: 0.6 });
    });
  }
}

function paintFloor(ctx, R) {
  const fl = [[BACK.x0, FLOOR_Y], [BACK.x1, FLOOR_Y], [990, DH], [-30, DH]];
  ctx.fillStyle = P.floor;
  ctx.fill(new Path2D(`M${fl.map((p) => p.join(' ')).join(' L')} Z`));
  const cols = [P.floor, '#c58a50', '#d69c60', '#ca9055'];
  const n = 12;
  const bx = (i) => lerp(BACK.x0, BACK.x1, i / n);
  const fx = (i) => lerp(-30, 990, i / n);
  for (let i = 0; i < n; i++) {
    const quad = [[bx(i), FLOOR_Y], [bx(i + 1), FLOOR_Y], [fx(i + 1), DH], [fx(i), DH]];
    ctx.fillStyle = cols[i % cols.length];
    ctx.beginPath();
    ctx.moveTo(quad[0][0], quad[0][1]);
    for (let k = 1; k < 4; k++) ctx.lineTo(quad[k][0], quad[k][1]);
    ctx.closePath();
    ctx.fill();
    // Butt joints, at their own spacing in each plank.
    let t = R() * 0.12;
    while (t < 1) {
      t += 0.16 + R() * 0.26;
      const y = FLOOR_Y + (DH - FLOOR_Y) * Math.pow(t, 1.25);
      const u = (y - FLOOR_Y) / (DH - FLOOR_Y);
      const xa = lerp(bx(i), fx(i), u);
      const xb = lerp(bx(i + 1), fx(i + 1), u);
      if (y < DH) line(ctx, [[xa, y], [xb, y + 0.6]], { color: P.woodDeep, w: 1.6 + u * 1.4, alpha: 0.5, R, j: 0.5 });
    }
    // Grain.
    for (let k = 0; k < 3; k++) {
      const gu = R();
      const gv = 0.08 + R() * 0.5;
      const ga = [lerp(bx(i) + (bx(i + 1) - bx(i)) * gu, fx(i) + (fx(i + 1) - fx(i)) * gu, gv), FLOOR_Y + (DH - FLOOR_Y) * gv];
      const gb = [lerp(bx(i) + (bx(i + 1) - bx(i)) * gu, fx(i) + (fx(i + 1) - fx(i)) * gu, gv + 0.22), FLOOR_Y + (DH - FLOOR_Y) * (gv + 0.22)];
      line(ctx, [ga, gb], { color: P.floorDark, w: 1.4, alpha: 0.35, R, j: 1.4 });
    }
  }
  for (let i = 0; i <= n; i++) line(ctx, [[bx(i), FLOOR_Y], [fx(i), DH]], { color: P.woodDeep, w: 2.2, alpha: 0.6, R, j: 0.5, step: 60 });
  // Where the floor meets the walls.
  line(ctx, [[BACK.x0, FLOOR_Y], [-30, DH]], { w: 3.4, R, j: 0.6, step: 80 });
  line(ctx, [[BACK.x1, FLOOR_Y], [990, DH]], { w: 3.4, R, j: 0.6, step: 80 });
  line(ctx, [[BACK.x0, FLOOR_Y], [BACK.x1, FLOOR_Y]], { w: 3.2, R, j: 0.6, step: 80 });
}

function paintRug(ctx, R) {
  const { cx, cy, rx, ry } = RUG;
  groundShadow(ctx, cx, cy + 6, rx + 24, ry + 10, 0.2);
  const ring = (k, fill, lw = 3) => ell(ctx, cx, cy, rx * k, ry * k, { fill, line: lw, R, j: 1.4, n: 44, off: 0.8 });
  // Fringe.
  for (let i = 0; i < 90; i++) {
    const a = (i / 90) * Math.PI * 2;
    const x0 = cx + Math.cos(a) * rx * 0.985;
    const y0 = cy + Math.sin(a) * ry * 0.985;
    const x1 = cx + Math.cos(a) * (rx + 9);
    const y1 = cy + Math.sin(a) * (ry + 3.6);
    line(ctx, [[x0, y0], [x1, y1]], { color: P.creamShade, w: 2.2, R, j: 0.4, alpha: 0.95 });
  }
  ring(1, P.navy, 3.4);
  ring(0.9, P.cream, 2.4);
  ring(0.82, P.jelly, 2.4);
  ring(0.66, P.cream, 2.2);
  ring(0.6, P.navy, 2.4);
  // Scallops around the red ring: a little jelly-mould border.
  for (let i = 0; i < 36; i++) {
    const a = (i / 36) * Math.PI * 2;
    ell(ctx, cx + Math.cos(a) * rx * 0.74, cy + Math.sin(a) * ry * 0.74, 7.5, 3.2, { fill: P.jellyLight, line: 0, R, alpha: 0.55 });
  }
  // A star in the middle, sketched.
  const star = [];
  for (let i = 0; i < 16; i++) {
    const a = (i / 16) * Math.PI * 2 - Math.PI / 2;
    const r = i % 2 === 0 ? 1 : 0.42;
    star.push([cx + Math.cos(a) * rx * 0.36 * r, cy + Math.sin(a) * ry * 0.36 * r]);
  }
  shape(ctx, star, { fill: P.brass, line: 2.4, R, j: 0.8 });
  // Shadowed rim.
  ell(ctx, cx, cy, rx * 0.985, ry * 0.985, { fill: null, line: 1.6, lineColor: 'rgba(43,24,16,0.3)', R, n: 44 });
}

function paintDoor(ctx, R) {
  const x = 142;
  const y = 116;
  // Frame and leaf.
  rect(ctx, x - 4, y - 6, 92, 184, { fill: P.woodDark, line: 3.2, R });
  rect(ctx, x + 4, y + 2, 76, 174, { fill: '#b9773a', line: 2.6, R });
  // Little window.
  rect(ctx, x + 14, y + 14, 56, 44, { fill: P.night, line: 3, R });
  line(ctx, [[x + 42, y + 14], [x + 42, y + 58]], { w: 2.4, R });
  line(ctx, [[x + 14, y + 36], [x + 70, y + 36]], { w: 2.4, R });
  ell(ctx, x + 24, y + 50, 3, 3, { fill: P.lamp, line: 0, R, alpha: 0.85 });
  ell(ctx, x + 58, y + 24, 2.5, 2.5, { fill: P.lamp, line: 0, R, alpha: 0.7 });
  // Raised panels.
  for (const [px, py, pw, ph] of [[14, 70, 24, 40], [46, 70, 24, 40], [14, 118, 24, 46], [46, 118, 24, 46]]) {
    rect(ctx, x + px, y + py, pw, ph, { fill: '#c98a48', line: 2.2, R });
    line(ctx, [[x + px + 3, y + py + ph - 3], [x + px + 3, y + py + 3], [x + px + pw - 3, y + py + 3]], { color: P.woodLight, w: 2, alpha: 0.8, R });
  }
  // Brass handle.
  ell(ctx, x + 68, y + 96, 5.5, 5.5, { fill: P.brass, line: 2.4, R });
  ell(ctx, x + 66.6, y + 94.4, 1.8, 1.8, { fill: P.brassLight, line: 0, R });
  // CLOSED sign on a string.
  line(ctx, [[x + 42, y + 66], [x + 30, y + 88]], { w: 1.6, R, alpha: 0.8 });
  line(ctx, [[x + 42, y + 66], [x + 54, y + 88]], { w: 1.6, R, alpha: 0.8 });
  rrect(ctx, x + 12, y + 86, 60, 28, 5, { fill: P.cream, line: 2.6, R });
  handText(ctx, 'CLOSED', x + 42, y + 105, { size: 15, color: P.jellyDark, align: 'center', R, spacing: 0.4 });
  // Doormat.
  shape(ctx, [[x - 4, 298], [x + 84, 298], [x + 92, 316], [x - 12, 316]], { fill: P.navy, line: 2.8, R });
  shape(ctx, [[x + 2, 302], [x + 78, 302], [x + 83, 312], [x - 3, 312]], { fill: null, line: 1.8, lineColor: P.cream, R });
}

// The arch of the window as a path (also used to clip the rain).
export function windowPath(ctx, inset = 0) {
  const { x, y, w, h } = WINDOW;
  const r = w / 2 - inset;
  const cx = x + w / 2;
  ctx.beginPath();
  ctx.moveTo(x + inset, y + h - inset);
  ctx.lineTo(x + inset, y + r + inset + 6);
  ctx.arc(cx, y + r + inset + 6, r, Math.PI, 0);
  ctx.lineTo(x + w - inset, y + h - inset);
  ctx.closePath();
}

function windowPts(inset) {
  const { x, y, w, h } = WINDOW;
  const r = w / 2 - inset;
  const cx = x + w / 2;
  const cyArc = y + r + inset + 6;
  const pts = [[x + inset, y + h - inset], [x + inset, cyArc]];
  for (let i = 1; i < 14; i++) {
    const a = Math.PI + (i / 14) * Math.PI;
    pts.push([cx + Math.cos(a) * r, cyArc + Math.sin(a) * r]);
  }
  pts.push([x + w - inset, cyArc], [x + w - inset, y + h - inset]);
  return pts;
}

function paintWindow(ctx, R) {
  const { x, y, w, h } = WINDOW;
  shape(ctx, windowPts(0), { fill: P.woodDeep, line: 3.6, R, j: 0.8, step: 22 });
  const glass = windowPts(9);
  shape(ctx, glass, { fill: null, line: 0 });
  ctx.save();
  windowPath(ctx, 9);
  ctx.clip();
  const g = ctx.createLinearGradient(0, y, 0, y + h);
  g.addColorStop(0, '#10183f');
  g.addColorStop(0.55, '#26397a');
  g.addColorStop(1, '#4b62ad');
  ctx.fillStyle = g;
  ctx.fillRect(x, y, w, h);
  // Far buildings.
  const bl = [[10, 138, 22, 60], [30, 120, 26, 90], [58, 132, 18, 70], [78, 112, 28, 100], [104, 128, 20, 80], [124, 118, 30, 90]];
  for (const [bx, by, bw, bh] of bl) {
    ctx.fillStyle = '#141d46';
    ctx.fillRect(x + bx, y + by, bw, bh);
    for (let wy = by + 8; wy < by + 62; wy += 12) {
      for (let wx = bx + 5; wx < bx + bw - 5; wx += 9) {
        if (R() < 0.42) {
          ctx.fillStyle = R() < 0.7 ? '#ffd57a' : '#ff9d6a';
          ctx.fillRect(x + wx, y + wy, 4, 5);
        }
      }
    }
  }
  // Street glow, out of focus.
  for (let i = 0; i < 16; i++) {
    const bx = x + 10 + R() * (w - 20);
    const by = y + h - 30 + R() * 26;
    const rr = 5 + R() * 9;
    const gg = ctx.createRadialGradient(bx, by, 0, bx, by, rr);
    const c = R() < 0.5 ? '255,214,140' : R() < 0.5 ? '255,140,110' : '150,190,255';
    gg.addColorStop(0, `rgba(${c},0.75)`);
    gg.addColorStop(1, `rgba(${c},0)`);
    ctx.fillStyle = gg;
    ctx.beginPath();
    ctx.arc(bx, by, rr, 0, Math.PI * 2);
    ctx.fill();
  }
  // A moon behind cloud.
  const mg = ctx.createRadialGradient(x + 112, y + 38, 2, x + 112, y + 38, 26);
  mg.addColorStop(0, 'rgba(255,248,220,0.9)');
  mg.addColorStop(0.35, 'rgba(255,248,220,0.35)');
  mg.addColorStop(1, 'rgba(255,248,220,0)');
  ctx.fillStyle = mg;
  ctx.beginPath();
  ctx.arc(x + 112, y + 38, 26, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();
  // Bars.
  const cx = x + w / 2;
  line(ctx, [[cx, y + 4], [cx, y + h - 8]], { w: 5, color: P.woodDeep, R, j: 0.5 });
  line(ctx, [[cx, y + 4], [cx, y + h - 8]], { w: 1.5, color: P.woodLight, R, j: 0.5, alpha: 0.8 });
  line(ctx, [[x + 8, y + 108], [x + w - 8, y + 108]], { w: 5, color: P.woodDeep, R, j: 0.5 });
  line(ctx, [[x + 8, y + 108], [x + w - 8, y + 108]], { w: 1.5, color: P.woodLight, R, j: 0.5, alpha: 0.8 });
  shape(ctx, windowPts(9), { fill: null, line: 2.6, R, j: 0.5, step: 22 });
  // Sill.
  rect(ctx, x - 12, y + h - 4, w + 24, 12, { fill: P.woodLight, line: 2.8, R });

  // Curtains, tied back.
  for (const side of [-1, 1]) {
    const bx = side < 0 ? x - 8 : x + w + 8;
    const dir = side;
    const pts = [[bx, y - 4], [bx + dir * 34, y - 4], [bx + dir * 30, y + 96], [bx + dir * 16, y + 108], [bx + dir * 32, y + 160], [bx + dir * 4, y + 160], [bx - dir * 6, y + 100]];
    shape(ctx, side < 0 ? pts : pts, { fill: '#c7332d', line: 3, R, j: 1, smooth: false });
    clipped(ctx, pts, () => {
      for (let k = 1; k < 4; k++) {
        line(ctx, [[bx + dir * (k * 8), y], [bx + dir * (k * 7 + 2), y + 158]], { color: '#8f1c1f', w: 2.6, alpha: 0.75, R, j: 1.2 });
        line(ctx, [[bx + dir * (k * 8 - 4), y + 4], [bx + dir * (k * 7 - 2), y + 150]], { color: '#ee6a5a', w: 2, alpha: 0.5, R, j: 1.2 });
      }
    });
    // Tie-back.
    line(ctx, [[bx + dir * 3, y + 100], [bx + dir * 28, y + 102]], { color: P.brass, w: 6, R, j: 0.4 });
    line(ctx, [[bx + dir * 3, y + 100], [bx + dir * 28, y + 102]], { color: P.ink, w: 1.4, R, j: 0.4, alpha: 0.7 });
  }
  // Rod.
  line(ctx, [[x - 22, y - 6], [x + w + 22, y - 6]], { w: 5.5, color: P.brassDark, R, j: 0.5 });
  ell(ctx, x - 24, y - 6, 5, 5, { fill: P.brass, line: 2.4, R });
  ell(ctx, x + w + 24, y - 6, 5, 5, { fill: P.brass, line: 2.4, R });
}

function paintWindowSeat(ctx, R) {
  // Bench.
  rect(ctx, 236, 248, 176, 46, { fill: P.woodLight, line: 3, R });
  for (const px of [244, 296, 348]) rrect(ctx, px, 256, 46, 30, 4, { fill: P.wood, line: 2.2, R });
  line(ctx, [[240, 251], [408, 251]], { color: '#f4c98e', w: 2.4, alpha: 0.8, R });
  // Cushion.
  rrect(ctx, 240, 232, 168, 22, 9, { fill: P.navy, line: 3, R });
  for (let sx = 262; sx < 400; sx += 24) line(ctx, [[sx, 236], [sx - 4, 250]], { color: P.cream, w: 2.4, alpha: 0.85, R, j: 0.4 });
  // Pillows.
  rrect(ctx, 250, 204, 38, 34, 12, { fill: P.cream, line: 3, R });
  ell(ctx, 269, 221, 8, 8, { fill: P.jelly, line: 2, R });
  rrect(ctx, 356, 208, 40, 30, 12, { fill: P.jelly, line: 3, R });
  for (const dy of [0, 8]) line(ctx, [[364, 217 + dy], [388, 217 + dy]], { color: P.cream, w: 2.6, R, alpha: 0.9 });
  // A book left open on the seat.
  shape(ctx, [[305, 240], [322, 233], [340, 240], [322, 246]], { fill: P.cream, line: 2.4, R });
  line(ctx, [[322, 233], [322, 246]], { w: 1.6, R });
}

function paintBar(ctx, R) {
  const x = 424;
  // Chalkboard menu.
  rrect(ctx, 438, 72, 196, 78, 8, { fill: P.woodDeep, line: 3.4, R });
  rrect(ctx, 446, 79, 180, 64, 4, { fill: '#243429', line: 2.6, R });
  brush(ctx, R, 446, 79, 180, 64, ['#33473a', '#1c2a20'], 40, 30);
  handText(ctx, "JELL-OMO'S CAFE", 536, 97, { size: 15, color: '#ffe9b8', align: 'center', R, spacing: 1 });
  line(ctx, [[478, 102], [500, 104], [536, 101], [572, 104], [594, 102]], { color: '#ffe9b8', w: 1.6, alpha: 0.8, R });
  const rows = [['espresso', '2'], ['latte', '3'], ['hot cocoa', '3'], ['jell-o', 'not for sale']];
  rows.forEach(([n, p], i) => {
    const yy = 113 + i * 8.4;
    handText(ctx, n, 458, yy, { size: 9.5, color: '#f4ecdb', R, weight: 500, wobble: 0.4 });
    line(ctx, [[500, yy - 1], [p.length > 2 ? 560 : 590, yy - 1]], { color: '#f4ecdb', w: 1, alpha: 0.35, R, j: 0.3 });
    handText(ctx, p.length > 2 ? p : p + ' beans', 612, yy, { size: 9.5, color: p.length > 2 ? '#ff9c8e' : '#ffd66e', R, weight: 500, align: 'right', wobble: 0.4 });
  });

  // Counter front.
  rect(ctx, x, 214, 218, 78, { fill: P.spruce, line: 3.2, R });
  brush(ctx, R, x, 214, 218, 78, [P.spruceLight, P.spruceDark], 40, 18);
  for (let px = x + 8; px < x + 210; px += 52) {
    rrect(ctx, px, 224, 44, 58, 5, { fill: null, line: 2.2, lineColor: P.spruceDark, R });
    line(ctx, [[px + 4, 228], [px + 4, 278]], { color: P.spruceLight, w: 2, alpha: 0.7, R });
  }
  rect(ctx, x, 284, 218, 8, { fill: P.brassDark, line: 2.4, R });
  // Counter top.
  rect(ctx, x - 10, 202, 238, 14, { fill: P.woodLight, line: 3.2, R });
  line(ctx, [[x - 6, 205], [x + 224, 205]], { color: '#f6d29a', w: 2.6, alpha: 0.9, R });
  hatch(ctx, x - 10, 208, 238, 8, { angle: 0, gap: 7, alpha: 0.12, R });

  // Espresso machine.
  rrect(ctx, 500, 156, 76, 46, 7, { fill: P.jelly, line: 3.2, R });
  clipped(ctx, rrPts(500, 156, 76, 46, 7), () => {
    line(ctx, [[506, 162], [506, 198]], { color: P.jellyLight, w: 6, alpha: 0.75, R });
    rect(ctx, 500, 190, 76, 12, { fill: P.jellyDark, line: 0, R });
  });
  rrect(ctx, 494, 150, 88, 10, 5, { fill: '#cbd3de', line: 3, R });
  line(ctx, [[500, 153], [572, 153]], { color: '#fff', w: 2, alpha: 0.9, R });
  ell(ctx, 538, 172, 8, 8, { fill: P.cream, line: 2.4, R });
  line(ctx, [[538, 172], [542, 168]], { w: 1.6, R });
  for (const sx of [531, 555]) {
    rect(ctx, sx - 6, 190, 12, 8, { fill: '#9aa3b2', line: 2.2, R });
    rect(ctx, sx - 2, 198, 4, 4, { fill: '#cbd3de', line: 1.6, R });
    // Cups under the spouts.
    rrect(ctx, sx - 8, 190, 16, 12, 3, { fill: P.cup, line: 0, R, alpha: 0 });
  }
  rect(ctx, 504, 201, 68, 5, { fill: '#8a93a3', line: 2.4, R });
  line(ctx, [[578, 172], [590, 184], [590, 196]], { w: 3, R });
  line(ctx, [[578, 172], [590, 184], [590, 196]], { color: '#cbd3de', w: 1.4, R });

  // Pastry case.
  rrect(ctx, 434, 194, 52, 8, 3, { fill: P.cream, line: 2.6, R });
  const dome = [[438, 194], [438, 182]];
  for (let i = 0; i <= 10; i++) {
    const a = Math.PI + (i / 10) * Math.PI;
    dome.push([460 + Math.cos(a) * 22, 182 + Math.sin(a) * 20]);
  }
  dome.push([482, 182], [482, 194]);
  shape(ctx, dome, { fill: 'rgba(200,230,240,0.35)', line: 2.4, R, smooth: true, off: 0 });
  line(ctx, [[444, 176], [449, 166]], { color: '#fff', w: 2.4, alpha: 0.9, R });
  // A croissant and a doughnut inside.
  shape(ctx, [[446, 192], [450, 184], [458, 182], [466, 184], [470, 192]], { fill: '#e6a850', line: 2.2, R, smooth: true });
  line(ctx, [[452, 187], [455, 192]], { color: P.woodDark, w: 1.4, R });
  line(ctx, [[459, 185], [460, 192]], { color: P.woodDark, w: 1.4, R });
  line(ctx, [[465, 187], [464, 192]], { color: P.woodDark, w: 1.4, R });
  ell(ctx, 476, 188, 6, 4, { fill: '#f5a3b5', line: 2, R });
  ell(ctx, 476, 188, 1.8, 1.2, { fill: '#243429', line: 0, R });

  // Coffee bean jar.
  rrect(ctx, 598, 176, 24, 26, 5, { fill: 'rgba(210,235,240,0.45)', line: 2.6, R });
  clipped(ctx, rrPts(598, 176, 24, 26, 5), () => {
    for (let i = 0; i < 18; i++) ell(ctx, 601 + R() * 18, 186 + R() * 15, 3.4, 2.4, { fill: P.coffee, line: 1, R, rot: R() * 3, n: 10 });
  });
  rrect(ctx, 596, 170, 28, 7, 3, { fill: P.brass, line: 2.4, R });
  // Stacked mugs.
  for (const [mx, my] of [[634, 190], [634, 176]]) {
    rrect(ctx, mx - 9, my, 18, 14, 4, { fill: P.cup, line: 2.4, R });
    line(ctx, [[mx + 9, my + 3], [mx + 14, my + 5], [mx + 9, my + 10]], { w: 2.2, R });
    line(ctx, [[mx - 7, my + 6], [mx + 7, my + 6]], { color: P.jelly, w: 2.2, R, alpha: 0.9 });
  }
  // Pendant lamps.
  for (const [px, py] of PENDANTS) {
    line(ctx, [[px, BACK.y0], [px, py - 22]], { w: 2, R, j: 0.4 });
    shape(ctx, [[px - 8, py - 22], [px + 8, py - 22], [px + 24, py - 2], [px - 24, py - 2]], { fill: P.navy, line: 3, R });
    line(ctx, [[px - 6, py - 19], [px - 18, py - 5]], { color: P.navyLight, w: 3, alpha: 0.85, R });
    rect(ctx, px - 24, py - 4, 48, 5, { fill: P.brass, line: 2.4, R });
    ell(ctx, px, py + 3, 7, 4.5, { fill: P.lamp, line: 1.6, R });
  }
}

function paintFireplace(ctx, R) {
  const x = 684;
  // Stone surround.
  rect(ctx, x, 158, 120, 134, { fill: P.stone, line: 3.4, R });
  clipped(ctx, [[x, 158], [x + 120, 158], [x + 120, 292], [x, 292]], () => {
    let row = 0;
    for (let by = 158; by < 292; by += 17) {
      const off = row % 2 ? 0 : 14;
      for (let bx = x - 14 + off; bx < x + 122; bx += 28) {
        const tone = R();
        ctx.fillStyle = tone < 0.33 ? P.stoneLight : tone < 0.66 ? P.stone : '#9b8f7d';
        ctx.fillRect(bx + 1, by + 1, 26, 15);
        line(ctx, [[bx, by], [bx + 28, by]], { color: P.stoneDark, w: 1.8, R, j: 0.5, alpha: 0.9 });
        line(ctx, [[bx, by], [bx, by + 17]], { color: P.stoneDark, w: 1.8, R, j: 0.5, alpha: 0.9 });
      }
      row++;
    }
  });
  rect(ctx, x, 158, 120, 134, { fill: null, line: 3.4, R });
  // Mantel.
  rect(ctx, x - 12, 146, 144, 14, { fill: P.woodLight, line: 3.2, R });
  line(ctx, [[x - 8, 149], [x + 124, 149]], { color: '#f6d29a', w: 2.6, alpha: 0.9, R });
  // Firebox.
  const f = FIREBOX;
  const arch = [[f.x, f.y + f.h], [f.x, f.y + 14]];
  for (let i = 1; i < 10; i++) {
    const a = Math.PI + (i / 10) * Math.PI;
    arch.push([f.x + f.w / 2 + Math.cos(a) * f.w / 2, f.y + 16 + Math.sin(a) * 18]);
  }
  arch.push([f.x + f.w, f.y + 14], [f.x + f.w, f.y + f.h]);
  shape(ctx, arch, { fill: '#241209', line: 3.6, R, smooth: false });
  // Hearth slab and the log rack.
  shape(ctx, [[x - 14, 292], [x + 134, 292], [x + 142, 306], [x - 22, 306]], { fill: P.stoneLight, line: 3, R });
  line(ctx, [[x - 10, 295], [x + 130, 295]], { color: '#fff', w: 2, alpha: 0.45, R });
  // On the mantel.
  // Potted succulent.
  rrect(ctx, x + 2, 130, 20, 16, 3, { fill: P.cream, line: 2.6, R });
  for (let i = 0; i < 5; i++) {
    const a = -Math.PI / 2 + (i - 2) * 0.55;
    ell(ctx, x + 12 + Math.cos(a) * 8, 128 + Math.sin(a) * 8, 3.6, 8, { fill: i % 2 ? P.leaf : P.leafLight, line: 2, R, rot: a + Math.PI / 2 });
  }
  // Candle.
  rrect(ctx, x + 108, 128, 12, 18, 3, { fill: P.cream, line: 2.6, R });
  line(ctx, [[x + 114, 128], [x + 114, 123]], { w: 1.6, R });
  // Portrait of the jelly over the fireplace.
  rrect(ctx, x + 32, 62, 56, 68, 5, { fill: P.brass, line: 3.2, R });
  rrect(ctx, x + 38, 68, 44, 56, 3, { fill: P.cream, line: 2.2, R });
  // Jell-omo in the frame.
  shape(ctx, [[x + 46, 116], [x + 48, 96], [x + 60, 90], [x + 72, 96], [x + 74, 116]], { fill: P.jelly, line: 2.2, R, smooth: true });
  line(ctx, [[x + 53, 100], [x + 52, 114]], { color: P.jellyLight, w: 2, R });
  shape(ctx, [[x + 46, 96], [x + 48, 88], [x + 60, 83], [x + 72, 88], [x + 74, 96], [x + 60, 93]], { fill: P.navy, line: 2, R, smooth: true });
  shape(ctx, [[x + 52, 88], [x + 60, 84], [x + 66, 88], [x + 60, 92]], { fill: P.cream, line: 1.4, R });
  line(ctx, [[x + 52, 104], [x + 55, 101], [x + 58, 104]], { w: 1.4, R });
  line(ctx, [[x + 62, 104], [x + 65, 101], [x + 68, 104]], { w: 1.4, R });
}

function paintShelves(ctx, R) {
  const side = 1;
  // Shelving unit on the right wall.
  const u0 = 0.1;
  const u1 = 0.86;
  const v0 = 0.14;
  const v1 = 0.72;
  shape(ctx, wallQuad(side, u0, u1, v0, v1), { fill: P.woodDark, line: 3, R, j: 0.6, step: 60 });
  const rows = 3;
  const colors = [P.jelly, P.navy, P.cream, P.spruce, P.brass, '#7a4a7a', P.navyLight, '#e07a3c', P.leaf];
  for (let r = 0; r < rows; r++) {
    const va = lerp(v0, v1, r / rows) + 0.012;
    const vb = lerp(v0, v1, (r + 1) / rows) - 0.034;
    shape(ctx, wallQuad(side, u0 + 0.02, u1 - 0.02, va, vb), { fill: '#3b2416', line: 0, R, j: 0.3, step: 60 });
    let u = u0 + 0.035;
    let ci = r * 3;
    while (u < u1 - 0.07) {
      const bw = 0.028 + R() * 0.03;
      const tall = 0.45 + R() * 0.5;
      const bv = vb - (vb - va) * tall;
      const c = colors[(ci++ + Math.floor(R() * 3)) % colors.length];
      shape(ctx, wallQuad(side, u, u + bw, bv, vb), { fill: c, line: 2, R, j: 0.35, off: 0.6, step: 60 });
      if (R() < 0.5) line(ctx, [wallPt(side, u + bw * 0.5, bv + (vb - bv) * 0.3), wallPt(side, u + bw * 0.5, bv + (vb - bv) * 0.4)], { color: P.cream, w: 1.4, alpha: 0.8, R });
      u += bw + 0.004;
      // A gap now and then, with a little object.
      if (R() < 0.12 && u < u1 - 0.18) u += 0.05;
    }
    shape(ctx, wallQuad(side, u0 - 0.01, u1 + 0.01, vb, vb + 0.03), { fill: P.woodLight, line: 2.4, R, j: 0.4, step: 60 });
  }
}

function paintLeftWall(ctx, R) {
  const side = -1;
  // Two framed prints.
  const frames = [[0.28, 0.56, 0.16, 0.42], [0.62, 0.86, 0.2, 0.36]];
  frames.forEach(([ua, ub, va, vb], k) => {
    shape(ctx, wallQuad(side, ua, ub, va, vb), { fill: P.brass, line: 3, R, j: 0.6, step: 60 });
    const iua = ua + (ub - ua) * 0.14;
    const iub = ub - (ub - ua) * 0.14;
    const iva = va + (vb - va) * 0.14;
    const ivb = vb - (vb - va) * 0.14;
    shape(ctx, wallQuad(side, iua, iub, iva, ivb), { fill: k ? '#bfdcd2' : '#f6e0b0', line: 2, R, j: 0.4, step: 60 });
    // A little hill and a sun.
    const hill = [wallPt(side, iua, ivb), wallPt(side, iua, lerp(iva, ivb, 0.5)), wallPt(side, lerp(iua, iub, 0.5), lerp(iva, ivb, 0.35)), wallPt(side, iub, lerp(iva, ivb, 0.6)), wallPt(side, iub, ivb)];
    shape(ctx, hill, { fill: k ? P.leaf : P.spruce, line: 1.8, R, j: 0.3, smooth: true });
    const sp = wallPt(side, lerp(iua, iub, 0.7), lerp(iva, ivb, 0.28));
    ell(ctx, sp[0], sp[1], 5, 5.6, { fill: k ? P.brassLight : P.jelly, line: 1.6, R });
  });
  // A sconce lamp.
  const sp = wallPt(side, 0.5, 0.56);
  rect(ctx, sp[0] - 4, sp[1], 8, 14, { fill: P.brass, line: 2.2, R });
  shape(ctx, [[sp[0] - 11, sp[1] - 2], [sp[0] + 11, sp[1] - 2], [sp[0] + 8, sp[1] - 18], [sp[0] - 8, sp[1] - 18]], { fill: P.cream, line: 2.6, R });
}

// The wire the string lights hang from. The bulbs themselves twinkle in `drawBulbs`.
export const BULBS = (() => {
  const out = [];
  const n = 22;
  for (let i = 0; i < n; i++) {
    const t = i / (n - 1);
    // Three swags along the back wall.
    const sw = (t * 3) % 1;
    out.push({ x: lerp(146, 814, t), y: 49 + Math.sin(sw * Math.PI) * 9, hue: i % 4 });
  }
  return out;
})();

function paintStringWire(ctx, R) {
  ctx.save();
  for (let s = 0; s < 3; s++) {
    const xa = 146 + s * (668 / 3);
    const xb = xa + 668 / 3;
    const pts = [];
    for (let i = 0; i <= 12; i++) {
      const t = i / 12;
      pts.push([lerp(xa, xb, t), 49 + Math.sin(t * Math.PI) * 9]);
    }
    line(ctx, pts, { w: 2, R, j: 0.3, alpha: 0.9 });
    ell(ctx, xa, 49, 3, 3, { fill: P.brassDark, line: 1.6, R });
  }
  ctx.restore();
  // Bulb sockets.
  for (const b of BULBS) {
    rect(ctx, b.x - 2, b.y - 1, 4, 5, { fill: P.brassDark, line: 1.4, R });
  }
}

// -------------------------------------------------------------------- overlay
/** The vignette that goes over everything, so the room falls off warm and dark at the edges. */
export function paintTop(ctx) {
  const g = ctx.createRadialGradient(DW / 2, DH * 0.5, DH * 0.32, DW / 2, DH * 0.5, DW * 0.68);
  g.addColorStop(0, 'rgba(50,14,4,0)');
  g.addColorStop(0.65, 'rgba(50,14,4,0.16)');
  g.addColorStop(1, 'rgba(30,8,4,0.55)');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, DW, DH);
  // A warm wash from the top.
  const w = ctx.createLinearGradient(0, 0, 0, 260);
  w.addColorStop(0, 'rgba(255,190,110,0.12)');
  w.addColorStop(1, 'rgba(255,190,110,0)');
  ctx.fillStyle = w;
  ctx.fillRect(0, 0, DW, 260);
}

// ------------------------------------------------------------------- dynamics
const pseudo = (n) => {
  const s = Math.sin(n * 127.1 + 311.7) * 43758.5453;
  return s - Math.floor(s);
};

/**
 * Rain on the glass, and drops that run down it. `heavy` (0..1) is how hard it
 * is raining: more streaks, faster, brighter.
 */
export function drawWindowRain(ctx, t, heavy = 0.3) {
  const { x, y, w, h } = WINDOW;
  ctx.save();
  windowPath(ctx, 9);
  ctx.clip();
  ctx.lineCap = 'round';
  // Falling streaks.
  const streaks = Math.round(34 + heavy * 70);
  for (let i = 0; i < streaks; i++) {
    const sx = x + 6 + pseudo(i * 3.1) * (w - 12);
    const speed = (150 + pseudo(i * 5.7) * 150) * (0.85 + heavy * 0.7);
    const len = 7 + pseudo(i * 2.3) * 12;
    const phase = pseudo(i * 9.1) * 400;
    const yy = y - 20 + ((t * speed + phase) % (h + 40));
    ctx.strokeStyle = `rgba(200,222,255,${0.18 + heavy * 0.14 + pseudo(i) * 0.22})`;
    ctx.lineWidth = 1.2;
    ctx.beginPath();
    ctx.moveTo(sx, yy);
    ctx.lineTo(sx - 1.6, yy + len);
    ctx.stroke();
  }
  // Slow drops sliding down the glass.
  for (let i = 0; i < 9; i++) {
    const sx = x + 14 + pseudo(i * 7.3) * (w - 28);
    const cyc = (5 + pseudo(i * 4.1) * 5) * (1.2 - heavy * 0.5);
    const p = ((t + pseudo(i * 6.2) * cyc) % cyc) / cyc;
    const yy = y + 20 + p * (h - 30);
    ctx.fillStyle = 'rgba(220,235,255,0.55)';
    ctx.beginPath();
    ctx.ellipse(sx, yy, 2.1, 3.1, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = 'rgba(220,235,255,0.22)';
    ctx.lineWidth = 1.1;
    ctx.beginPath();
    ctx.moveTo(sx, yy - 3);
    ctx.lineTo(sx, yy - 3 - p * 26);
    ctx.stroke();
  }
  ctx.restore();
}

/** The fire: three flame layers over a pair of logs, plus a few sparks. */
export function drawFire(ctx, t) {
  const f = FIREBOX;
  const R = rng(9);
  ctx.save();
  ctx.beginPath();
  ctx.rect(f.x + 3, f.y + 2, f.w - 6, f.h - 2);
  ctx.clip();
  // Ember bed glow.
  const bed = ctx.createRadialGradient(f.x + f.w / 2, f.y + f.h - 6, 2, f.x + f.w / 2, f.y + f.h - 6, 40);
  bed.addColorStop(0, 'rgba(255,150,40,0.85)');
  bed.addColorStop(1, 'rgba(255,90,20,0)');
  ctx.fillStyle = bed;
  ctx.fillRect(f.x, f.y, f.w, f.h);
  // Logs.
  ell(ctx, f.x + 22, f.y + f.h - 8, 18, 6, { fill: '#5a3418', line: 2.4, R, rot: -0.12, n: 14 });
  ell(ctx, f.x + 44, f.y + f.h - 8, 18, 6, { fill: '#6b3f1c', line: 2.4, R, rot: 0.14, n: 14 });
  ell(ctx, f.x + 33, f.y + f.h - 13, 20, 6, { fill: '#7a4a22', line: 2.4, R, rot: 0.02, n: 14 });
  // Flames.
  const flame = (cx, base, hgt, wid, col, seed, alpha) => {
    const sway = Math.sin(t * 5 + seed) * 4 + Math.sin(t * 9.3 + seed * 2) * 2;
    const flick = 1 + Math.sin(t * 7.1 + seed * 3) * 0.12 + Math.sin(t * 13 + seed) * 0.05;
    const H = hgt * flick;
    ctx.beginPath();
    ctx.moveTo(cx - wid, base);
    ctx.bezierCurveTo(cx - wid * 1.1, base - H * 0.4, cx - wid * 0.4 + sway * 0.5, base - H * 0.65, cx + sway, base - H);
    ctx.bezierCurveTo(cx + wid * 0.4 + sway * 0.5, base - H * 0.65, cx + wid * 1.1, base - H * 0.4, cx + wid, base);
    ctx.closePath();
    ctx.fillStyle = col;
    ctx.globalAlpha = alpha;
    ctx.fill();
    ctx.globalAlpha = 1;
  };
  const base = f.y + f.h - 8;
  flame(f.x + 20, base, 38, 10, '#e2551c', 1, 0.95);
  flame(f.x + 44, base, 42, 11, '#e2551c', 2.4, 0.95);
  flame(f.x + 32, base, 56, 14, P.fire, 3.1, 1);
  flame(f.x + 32, base, 36, 9, P.fireLight, 4.6, 1);
  flame(f.x + 26, base, 22, 5, '#fff2b0', 5.2, 0.95);
  // Sparks.
  for (let i = 0; i < 5; i++) {
    const p = ((t * 0.6 + i * 0.21) % 1);
    const sx = f.x + 20 + pseudo(i * 3.3 + Math.floor(t * 0.6 + i * 0.21)) * 26 + Math.sin(t * 3 + i) * 4;
    const sy = base - 20 - p * 40;
    ctx.fillStyle = `rgba(255,220,120,${(1 - p) * 0.9})`;
    ctx.fillRect(sx, sy, 2, 2);
  }
  ctx.restore();
  // Re-ink the firebox rim over the flames.
  ctx.save();
  ctx.strokeStyle = P.ink;
  ctx.lineWidth = 3.4;
  ctx.lineJoin = 'round';
  ctx.beginPath();
  ctx.moveTo(f.x, f.y + f.h);
  ctx.lineTo(f.x, f.y + 14);
  for (let i = 1; i < 10; i++) {
    const a = Math.PI + (i / 10) * Math.PI;
    ctx.lineTo(f.x + f.w / 2 + Math.cos(a) * f.w / 2, f.y + 16 + Math.sin(a) * 18);
  }
  ctx.lineTo(f.x + f.w, f.y + 14);
  ctx.lineTo(f.x + f.w, f.y + f.h);
  ctx.stroke();
  ctx.restore();
}

/** Pools of warm light: the fire on the floor, the pendants, a bluish wash from the window. */
export function drawGlow(ctx, t) {
  ctx.save();
  ctx.globalCompositeOperation = 'lighter';
  const flick = 0.85 + Math.sin(t * 6.2) * 0.07 + Math.sin(t * 11.3) * 0.05;
  // Fire on the floor and the hearth.
  let g = ctx.createRadialGradient(748, 330, 6, 748, 330, 190);
  g.addColorStop(0, `rgba(255,140,50,${0.34 * flick})`);
  g.addColorStop(1, 'rgba(255,120,40,0)');
  ctx.fillStyle = g;
  ctx.save();
  ctx.translate(748, 330);
  ctx.scale(1, 0.55);
  ctx.translate(-748, -330);
  ctx.beginPath();
  ctx.arc(748, 330, 190, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();
  // The wall around the fireplace.
  g = ctx.createRadialGradient(744, 210, 4, 744, 210, 130);
  g.addColorStop(0, `rgba(255,150,60,${0.2 * flick})`);
  g.addColorStop(1, 'rgba(255,150,60,0)');
  ctx.fillStyle = g;
  ctx.fillRect(600, 60, 240, 240);
  // Pendants.
  PENDANTS.forEach(([px, py], i) => {
    const k = 0.92 + Math.sin(t * 0.8 + i * 2) * 0.04;
    const gp = ctx.createRadialGradient(px, py + 4, 2, px, py + 4, 110);
    gp.addColorStop(0, `rgba(255,214,150,${0.5 * k})`);
    gp.addColorStop(0.5, `rgba(255,190,110,${0.14 * k})`);
    gp.addColorStop(1, 'rgba(255,190,110,0)');
    ctx.fillStyle = gp;
    ctx.beginPath();
    ctx.arc(px, py + 4, 110, 0, Math.PI * 2);
    ctx.fill();
  });
  // Cool light through the window onto the seat and the floor.
  const wg = ctx.createRadialGradient(322, 250, 8, 322, 250, 150);
  wg.addColorStop(0, 'rgba(120,160,255,0.16)');
  wg.addColorStop(1, 'rgba(120,160,255,0)');
  ctx.fillStyle = wg;
  ctx.fillRect(160, 120, 340, 260);
  ctx.restore();
}

/** The string lights, each bulb on its own slow twinkle. */
export function drawBulbs(ctx, t) {
  const cols = ['255,214,140', '255,150,120', '255,236,180', '160,206,255'];
  ctx.save();
  BULBS.forEach((b, i) => {
    const k = 0.7 + Math.sin(t * (1.1 + (i % 5) * 0.23) + i * 1.7) * 0.3;
    const c = cols[b.hue];
    const g = ctx.createRadialGradient(b.x, b.y + 6, 0, b.x, b.y + 6, 15);
    g.addColorStop(0, `rgba(${c},${0.55 * k})`);
    g.addColorStop(1, `rgba(${c},0)`);
    ctx.globalCompositeOperation = 'lighter';
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(b.x, b.y + 6, 15, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = `rgba(${c},1)`;
    ctx.strokeStyle = P.ink;
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.ellipse(b.x, b.y + 7, 3.2, 4.4, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,0.75)';
    ctx.fillRect(b.x - 1.4, b.y + 4.6, 1.5, 2);
  });
  ctx.restore();
}

/**
 * Steam rising from a point. `puffs` is how many are in the air at once, so a
 * busier machine gives a thicker plume.
 */
export function drawSteam(ctx, x, y, t, size = 1, seed = 0, puffs = 3, rise = 0.32) {
  ctx.save();
  const gap = 1 / puffs;
  const soft = 3 / puffs; // keep a thick plume from turning solid white
  for (let i = 0; i < puffs; i++) {
    const p = (t * rise + i * gap + seed) % 1;
    const yy = y - p * 46 * size;
    const a = Math.sin(p * Math.PI) * 0.34 * Math.min(1, 0.55 + soft * 0.45);
    const xx = x + Math.sin(t * 1.4 + i * 2 + seed * 5) * 6 * p * size;
    ctx.fillStyle = `rgba(255,252,244,${a})`;
    ctx.beginPath();
    ctx.ellipse(xx, yy, (5 + p * 9) * size, (6 + p * 10) * size, 0, 0, Math.PI * 2);
    ctx.fill();
  }
  ctx.restore();
}

// ------------------------------------------------------------------ lightning
/** A jagged bolt from the top of the window down, with a couple of forks. */
export function makeBolt(seed) {
  const R = rng(seed);
  const { x, y, w, h } = WINDOW;
  const pts = [];
  let bx = x + w * (0.25 + R() * 0.5);
  let by = y - 4;
  pts.push([bx, by]);
  const forks = [];
  while (by < y + h * (0.55 + R() * 0.3)) {
    by += 12 + R() * 16;
    bx += (R() - 0.5) * 34;
    pts.push([bx, by]);
    if (R() < 0.3) {
      const f = [[bx, by]];
      let fx = bx;
      let fy = by;
      const dir = R() < 0.5 ? -1 : 1;
      for (let k = 0; k < 3; k++) {
        fy += 8 + R() * 10;
        fx += dir * (6 + R() * 14);
        f.push([fx, fy]);
      }
      forks.push(f);
    }
  }
  return { pts, forks };
}

/**
 * Lightning seen through the window: the sky whitens and the bolt shows.
 * `flash` is 0..1 brightness right now.
 */
export function drawLightning(ctx, flash, bolt) {
  if (flash <= 0.01) return;
  ctx.save();
  windowPath(ctx, 7);
  ctx.clip();
  ctx.fillStyle = `rgba(128,158,236,${Math.min(0.85, flash * 0.85)})`; // stormy blue, so the white bolt shows in it
  ctx.fillRect(WINDOW.x, WINDOW.y - 20, WINDOW.w, WINDOW.h + 40);
  if (bolt) {
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    const trace = (pts) => {
      ctx.beginPath();
      pts.forEach(([px, py], i) => (i ? ctx.lineTo(px, py) : ctx.moveTo(px, py)));
      ctx.stroke();
    };
    ctx.strokeStyle = `rgba(200,220,255,${0.6 * flash})`;
    ctx.lineWidth = 8;
    trace(bolt.pts);
    ctx.strokeStyle = `rgba(255,255,255,${Math.min(1, flash * 1.4)})`;
    ctx.lineWidth = 2.8;
    trace(bolt.pts);
    ctx.lineWidth = 1.6;
    for (const f of bolt.forks) trace(f);
  }
  ctx.restore();
}

/** The whole room going cold-bright for an instant. */
export function drawFlash(ctx, flash) {
  if (flash <= 0.01) return;
  ctx.save();
  ctx.globalCompositeOperation = 'lighter';
  // Strongest around the window, but it lights the whole room.
  const g = ctx.createRadialGradient(322, 160, 10, 322, 220, 720);
  g.addColorStop(0, `rgba(150,180,255,${0.34 * flash})`);
  g.addColorStop(1, `rgba(110,140,235,${0.1 * flash})`);
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, DW, DH);
  ctx.restore();
}
