/* =============================================================================
   Things that stand on the floor. They are drawn every frame, sorted by depth
   against Jell-omo, so it can walk behind a plant and in front of an armchair.

   Each `draw*` works in local units with the ground contact at (0, 0), and the
   caller scales it for its depth. Every one seeds its own wobble so nothing
   shivers between frames.
   ========================================================================== */

import { P, rng, shape, ell, rect, rrect, rrPts, line, clipped, groundShadow, ellipsePts } from './draw.js';
import { drawSteam } from './room.js';

const withLocal = (ctx, x, y, s, fn) => {
  ctx.save();
  ctx.translate(x, y);
  ctx.scale(s, s);
  fn();
  ctx.restore();
};

// ------------------------------------------------------------------ armchair
export function drawArmchair(ctx, x, y, s, body, light, dark) {
  withLocal(ctx, x, y, s, () => {
    const R = rng(Math.round(x * 7 + 11));
    groundShadow(ctx, 0, 2, 74, 15, 0.3);
    // Legs.
    for (const lx of [-46, 40]) rrect(ctx, lx, -16, 8, 16, 2, { fill: P.woodDark, line: 2.4, R });
    // Back.
    rrect(ctx, -52, -102, 104, 70, 24, { fill: body, line: 3.2, R });
    clipped(ctx, rrPts(-52, -102, 104, 70, 24), () => {
      line(ctx, [[-40, -92], [-16, -98]], { color: light, w: 6, alpha: 0.7, R });
      rect(ctx, -52, -50, 104, 20, { fill: dark, line: 0, R, alpha: 0.5 });
    });
    // Tufted buttons.
    for (const [bx, by] of [[-22, -78], [0, -84], [22, -78], [-11, -62], [11, -62]]) {
      ell(ctx, bx, by, 3, 3, { fill: dark, line: 1.8, R, n: 8 });
    }
    // Seat.
    rrect(ctx, -58, -44, 116, 34, 12, { fill: body, line: 3.2, R });
    rrect(ctx, -44, -56, 88, 24, 10, { fill: light, line: 3, R });
    line(ctx, [[-36, -50], [-8, -53]], { color: '#fff', w: 3, alpha: 0.35, R });
    // Arms.
    for (const ax of [-70, 40]) {
      rrect(ctx, ax, -76, 30, 62, 13, { fill: body, line: 3.2, R });
      line(ctx, [[ax + 6, -68], [ax + 8, -44]], { color: light, w: 5, alpha: 0.65, R });
    }
    // A throw cushion.
    rrect(ctx, -34, -66, 28, 26, 9, { fill: P.cream, line: 2.8, R });
    for (const dy of [-59, -52]) line(ctx, [[-28, dy], [-12, dy]], { color: P.jelly, w: 2.6, alpha: 0.9, R });
  });
}

// --------------------------------------------------------------------- table
export function drawTable(ctx, x, y, s, t) {
  withLocal(ctx, x, y, s, () => {
    const R = rng(Math.round(x * 3 + 5));
    groundShadow(ctx, 0, 2, 52, 11, 0.28);
    // Base and stem.
    ell(ctx, 0, -3, 28, 7, { fill: P.woodDark, line: 3, R, n: 16 });
    rect(ctx, -5, -56, 10, 52, { fill: P.wood, line: 2.8, R });
    line(ctx, [[-2, -52], [-2, -8]], { color: P.woodLight, w: 2, alpha: 0.8, R });
    // Top.
    shape(ctx, [...ellipsePts(0, -58, 50, 13, 24)], { fill: P.woodDark, line: 3.2, R, smooth: true, off: 0 });
    ell(ctx, 0, -62, 50, 13, { fill: P.woodLight, line: 3.2, R, n: 24 });
    ell(ctx, -8, -64, 26, 5, { fill: '#f6d29a', line: 0, R, alpha: 0.55 });
    // Two mugs and a cookie on a saucer.
    for (const [mx, my, col] of [[-22, -64, P.cup], [16, -60, '#ffd7b0']]) {
      ell(ctx, mx, my + 8, 13, 4, { fill: P.cream, line: 2, R, n: 12 });
      rrect(ctx, mx - 9, my - 12, 18, 18, 5, { fill: col, line: 2.6, R });
      line(ctx, [[mx + 9, my - 8], [mx + 15, my - 5], [mx + 9, my + 1]], { w: 2.4, R });
      ell(ctx, mx, my - 12, 9, 2.8, { fill: P.coffee, line: 2, R, n: 12 });
      line(ctx, [[mx - 6, my - 4], [mx + 6, my - 4]], { color: P.jelly, w: 2.4, alpha: 0.9, R });
    }
    ell(ctx, 38, -62, 8, 3, { fill: P.cream, line: 2, R, n: 12 });
    ell(ctx, 38, -64, 5.6, 2.6, { fill: '#d69a4c', line: 1.8, R, n: 12 });
    ell(ctx, 36.5, -64.6, 0.9, 0.7, { fill: P.coffee, line: 0, R });
    ell(ctx, 40, -63.6, 0.9, 0.7, { fill: P.coffee, line: 0, R });
    drawSteam(ctx, -22, -78, t, 0.7, 0.1);
    drawSteam(ctx, 16, -74, t, 0.7, 0.55);
  });
}

// --------------------------------------------------------------------- stool
export function drawStool(ctx, x, y, s) {
  withLocal(ctx, x, y, s, () => {
    const R = rng(Math.round(x * 5 + 3));
    groundShadow(ctx, 0, 2, 24, 6, 0.28);
    for (const [ax, bx] of [[-14, -20], [14, 20], [0, 6]]) line(ctx, [[ax * 0.5, -32], [bx, 0]], { w: 5, R, j: 0.4 });
    for (const [ax, bx] of [[-14, -20], [14, 20], [0, 6]]) line(ctx, [[ax * 0.5, -32], [bx, 0]], { color: P.wood, w: 2, R, j: 0.4 });
    ell(ctx, 0, -18, 16, 4, { fill: null, line: 2.4, R, n: 14 });
    ell(ctx, 0, -44, 20, 7, { fill: P.woodDark, line: 3, R, n: 16, off: 0 });
    ell(ctx, 0, -48, 20, 7, { fill: P.jelly, line: 3, R, n: 16 });
    ell(ctx, -6, -50, 9, 2.2, { fill: P.jellyLight, line: 0, R, alpha: 0.8 });
  });
}

// --------------------------------------------------------------------- plant
export function drawPlant(ctx, x, y, s, t, big = true) {
  withLocal(ctx, x, y, s, () => {
    const R = rng(Math.round(x * 2 + 9));
    const k = big ? 1 : 0.7;
    groundShadow(ctx, 0, 2, 40 * k, 9 * k, 0.3);
    // Leaves fan out from the pot.
    const leaves = [[-1.25, 60], [-0.85, 82], [-0.4, 96], [0.05, 104], [0.5, 94], [0.9, 80], [1.28, 58], [-0.62, 66], [0.68, 68]];
    leaves.forEach(([a, len], i) => {
      const sway = Math.sin(t * 0.9 + i * 1.3) * 0.025;
      const ang = a + sway;
      const L = len * k;
      const px = Math.sin(ang) * L;
      const py = -Math.cos(ang) * L - 34 * k;
      // Stem then blade.
      line(ctx, [[0, -34 * k], [px * 0.5, py * 0.6 - 14 * k], [px, py]], { color: P.leafDark, w: 2.6, R, j: 0.4 });
      const bx = px;
      const by = py;
      ctx.save();
      ctx.translate(bx, by);
      ctx.rotate(ang);
      const w = 17 * k;
      const h = 28 * k;
      const pts = [[0, -h * 0.5], [w * 0.8, -h * 0.15], [w, h * 0.35], [0, h * 0.5], [-w, h * 0.35], [-w * 0.8, -h * 0.15]];
      shape(ctx, pts, { fill: i % 2 ? P.leaf : P.leafLight, line: 2.6, R, smooth: true });
      line(ctx, [[0, -h * 0.4], [0, h * 0.4]], { color: P.leafDark, w: 1.8, R });
      for (const yy of [-0.1, 0.15]) {
        line(ctx, [[0, yy * h], [w * 0.7, yy * h + 5]], { color: P.leafDark, w: 1.2, alpha: 0.8, R });
        line(ctx, [[0, yy * h], [-w * 0.7, yy * h + 5]], { color: P.leafDark, w: 1.2, alpha: 0.8, R });
      }
      ctx.restore();
    });
    // Pot.
    const pot = [[-26 * k, -38 * k], [26 * k, -38 * k], [20 * k, 0], [-20 * k, 0]];
    shape(ctx, pot, { fill: P.cream, line: 3.2, R });
    clipped(ctx, pot, () => {
      rect(ctx, -30 * k, -30 * k, 60 * k, 8 * k, { fill: P.navy, line: 0, R });
      line(ctx, [[-20 * k, -36 * k], [-16 * k, -4 * k]], { color: '#fff', w: 4, alpha: 0.5, R });
      rect(ctx, -30 * k, -12 * k, 60 * k, 14 * k, { fill: P.creamShade, line: 0, R, alpha: 0.6 });
    });
    ell(ctx, 0, -38 * k, 27 * k, 5 * k, { fill: '#3d2414', line: 2.8, R, n: 14 });
  });
}

// ----------------------------------------------------------------------- cat
const ORANGE = '#e9a044';
const ORANGE_DARK = '#c47420';
const CUSHION_H = 22;

/** The round cushion the cat sleeps on. It stays put when the cat gets up. */
export function drawCushion(ctx, x, y, s) {
  withLocal(ctx, x, y, s, () => {
    const R = rng(4243);
    groundShadow(ctx, 0, 2, 50, 10, 0.3);
    ell(ctx, 0, -9, 42, 12, { fill: P.jellyDark, line: 3.2, R, n: 22 });
    ell(ctx, 0, -14, 42, 12, { fill: P.jelly, line: 3.2, R, n: 22 });
    ell(ctx, -12, -16, 20, 3.4, { fill: P.jellyLight, line: 0, R, alpha: 0.8 });
    for (const tx of [-40, 40]) {
      line(ctx, [[tx, -12], [tx * 1.1, -2]], { color: P.brass, w: 3.2, R, j: 0.3 });
    }
  });
}

// The cat's face, as seen from the front, drawn at (hx, hy).
function catHead(ctx, hx, hy, wake, eyesOpen, R) {
  ell(ctx, hx, hy, 14, 12.5, { fill: ORANGE, line: 3, R, n: 16 });
  for (const [ex, dir] of [[hx - 8, -1], [hx + 8, 1]]) {
    shape(ctx, [[ex - 4, hy - 8], [ex + dir * 3, hy - 20 - wake * 2], [ex + 6, hy - 8]], { fill: ORANGE, line: 2.6, R });
    shape(ctx, [[ex - 1, hy - 9], [ex + dir * 2, hy - 15], [ex + 3, hy - 9]], { fill: '#f5a3b5', line: 0, R });
  }
  ell(ctx, hx + 1, hy + 4, 5, 3.4, { fill: P.cream, line: 0, R, alpha: 0.95 });
  ell(ctx, hx + 1, hy + 2.4, 1.5, 1.1, { fill: '#e07a8c', line: 0, R });
  if (eyesOpen) {
    for (const ex of [hx - 5, hx + 6]) {
      ell(ctx, ex, hy - 1, 3, 3.6, { fill: '#9fe28a', line: 1.8, R, n: 10 });
      ell(ctx, ex, hy - 1, 1.1, 2.6, { fill: P.ink, line: 0, R, n: 8 });
    }
  } else {
    for (const ex of [hx - 5, hx + 6]) {
      line(ctx, [[ex - 3, hy - 1], [ex, hy + 1.6], [ex + 3, hy - 1]], { w: 1.8, R, j: 0.2 });
    }
  }
  for (const dir of [-1, 1]) {
    line(ctx, [[hx + dir * 4, hy + 4], [hx + dir * 15, hy + 3]], { w: 1, alpha: 0.7, R, j: 0.2 });
    line(ctx, [[hx + dir * 4, hy + 5.4], [hx + dir * 14, hy + 7]], { w: 1, alpha: 0.7, R, j: 0.2 });
  }
}

// A leg drawn as an inked stroke with a paw on the end.
function catLeg(ctx, x0, y0, x1, y1, col, R) {
  line(ctx, [[x0, y0], [x1, y1]], { color: P.ink, w: 8, R, j: 0.2 });
  line(ctx, [[x0, y0], [x1, y1]], { color: col, w: 4.4, R, j: 0.2 });
  ell(ctx, x1, y1, 4.4, 2.6, { fill: P.cream, line: 2, R, n: 8 });
}

// Curled up, asleep or just woken. `low` is how far it has sunk from cushion height.
function lyingCat(ctx, t, wake, low, R) {
  ctx.translate(0, low);
  const breathe = Math.sin(t * 1.7) * (1 - wake * 0.6);
  const by = -30 - breathe * 0.9;
  ell(ctx, 2, by, 30, 17 + breathe * 0.7, { fill: ORANGE, line: 3, R, n: 20 });
  clipped(ctx, ellipsePts(2, by, 30, 17, 20), () => {
    for (const sx of [-12, -2, 8, 18]) line(ctx, [[sx, by - 18], [sx + 2, by - 6]], { color: ORANGE_DARK, w: 4, R, j: 0.5, alpha: 0.9 });
    ell(ctx, 8, by + 10, 18, 7, { fill: P.cream, line: 0, R, alpha: 0.9 });
  });
  // Tail curled round the front.
  line(ctx, [[26, by + 6], [38, by + 10], [30, by + 18], [8, by + 19]], { color: P.ink, w: 9, R, j: 0.3 });
  line(ctx, [[26, by + 6], [38, by + 10], [30, by + 18], [8, by + 19]], { color: ORANGE, w: 5, R, j: 0.3 });
  const hx = -22;
  const hy = by - 2 - wake * 12;
  catHead(ctx, hx, hy, wake, wake > 0.3, R);
  ell(ctx, hx + 2, by + 8, 8, 4.4, { fill: P.cream, line: 2.4, R, n: 10 });
}

// Sitting up. With `groom` the head dips and a front paw comes up to be licked.
function sittingCat(ctx, t, groom, R) {
  const sway = Math.sin(t * 1.3) * 1.2;
  // Tail on the floor round the back.
  line(ctx, [[20, -6], [36, -3], [26, 1], [4, 1]], { color: P.ink, w: 9, R, j: 0.3 });
  line(ctx, [[20, -6], [36, -3], [26, 1], [4, 1]], { color: ORANGE, w: 5, R, j: 0.3 });
  // Haunch and chest.
  ell(ctx, 9, -15, 17, 14.5, { fill: ORANGE, line: 3, R, n: 16 });
  ell(ctx, -5, -27, 11.5, 18, { fill: ORANGE, line: 3, R, n: 16, rot: 0.1 });
  clipped(ctx, ellipsePts(-5, -27, 11.5, 18, 16), () => {
    ell(ctx, -9, -20, 7.5, 12, { fill: P.cream, line: 0, R, alpha: 0.9 });
    for (const sy of [-38, -32]) line(ctx, [[-2, sy], [8, sy + 4]], { color: ORANGE_DARK, w: 3.4, R, j: 0.4, alpha: 0.9 });
  });
  // Front legs.
  catLeg(ctx, -9, -16, -10, -1.5, ORANGE, R);
  if (groom) {
    const lick = Math.sin(t * 7) * 2.2;
    catLeg(ctx, -3, -22, -11 + lick, -37 + lick * 0.6, ORANGE, R);
    catHead(ctx, -8, -40 + sway * 0.3, 0, false, R);
  } else {
    catLeg(ctx, -2, -16, -3, -1.5, ORANGE, R);
    catHead(ctx, -9 + sway * 0.4, -49, 0.5, true, R);
  }
}

// Standing or walking, side on, facing left. `phase` is the stride, `gait` how brisk.
function standingCat(ctx, t, phase, gait, R) {
  const bob = Math.abs(Math.sin(phase)) * 1.4 * gait;
  const by = -25 - bob;
  const swing = 7 * gait;
  const leg = (off) => {
    const a = Math.sin(phase + off);
    const lift = Math.max(0, Math.cos(phase + off)) * 3.5 * gait;
    return [a * swing, -lift];
  };
  // Far-side legs first, darker, so the near ones read in front.
  const [ffx, ffy] = leg(Math.PI);
  const [rfx, rfy] = leg(0);
  catLeg(ctx, -12, by + 6, -13 + ffx, -2 + ffy, ORANGE_DARK, R);
  catLeg(ctx, 15, by + 6, 16 + rfx, -2 + rfy, ORANGE_DARK, R);
  // Tail up, with a curl at the tip.
  const tw = Math.sin(t * 2.2) * 3 + Math.sin(phase) * 2 * gait;
  const tail = [[23, by - 2], [34, by - 8], [37 + tw, by - 22], [32 + tw, by - 30]];
  line(ctx, tail, { color: P.ink, w: 9, R, j: 0.3 });
  line(ctx, tail, { color: ORANGE, w: 5, R, j: 0.3 });
  ell(ctx, 3, by, 25, 11.5, { fill: ORANGE, line: 3, R, n: 18 });
  clipped(ctx, ellipsePts(3, by, 25, 11.5, 18), () => {
    for (const sx of [-6, 2, 10, 18]) line(ctx, [[sx, by - 12], [sx + 2, by - 3]], { color: ORANGE_DARK, w: 3.4, R, j: 0.5, alpha: 0.9 });
    ell(ctx, -4, by + 8, 16, 5, { fill: P.cream, line: 0, R, alpha: 0.9 });
  });
  const [nfx, nfy] = leg(0);
  const [nrx, nry] = leg(Math.PI);
  catLeg(ctx, -12, by + 6, -12 + nfx, -2 + nfy, ORANGE, R);
  catLeg(ctx, 15, by + 6, 15 + nrx, -2 + nry, ORANGE, R);
  catHead(ctx, -26, by - 8 - bob * 0.4, 0.4, true, R);
}

/**
 * The cat. `o`:
 *   posture  'lie' | 'sit' | 'groom' | 'stand'
 *   wake     0..1, head up and eyes open while lying
 *   lift     0..1, how high it is: 1 on the cushion, 0 on the floor
 *   face     -1 looks left, 1 looks right
 *   phase    stride phase, radians
 *   gait     0..1 how briskly it walks
 *   squash   -1..1, a short spring when it changes posture
 */
export function drawCat(ctx, x, y, s, t, o) {
  const { posture, wake = 0, lift = 1, face = -1, phase = 0, gait = 0, squash = 0 } = o;
  const off = lift < 0.99;
  withLocal(ctx, x, y, s, () => {
    const R = rng(4242);
    if (off) groundShadow(ctx, 2, 2, posture === 'lie' ? 36 : 30, 7, 0.28);
    // The lying pose is drawn at cushion height already, so it sinks when there
    // is no cushion; the others are drawn on the floor and rise onto it.
    ctx.translate(0, posture === 'lie' ? (1 - lift) * CUSHION_H : -lift * CUSHION_H);
    ctx.scale(1 - squash * 0.06, 1 + squash * 0.1);
    if (face > 0 && posture !== 'lie') ctx.scale(-1, 1);
    switch (posture) {
      case 'lie': lyingCat(ctx, t, wake, 0, R); break;
      case 'groom': sittingCat(ctx, t, true, R); break;
      case 'sit': sittingCat(ctx, t, false, R); break;
      default: standingCat(ctx, t, phase, gait, R); break;
    }
  });
  if (posture === 'lie' && wake < 0.3) {
    // z z z
    for (let i = 0; i < 3; i++) {
      const p = ((t * 0.28 + i * 0.33) % 1);
      const zx = x - 8 * s + p * 20 * s + Math.sin(t + i) * 3;
      const zy = y - (72 - (1 - lift) * CUSHION_H) * s - p * 34 * s;
      ctx.save();
      ctx.globalAlpha = Math.sin(p * Math.PI) * 0.85;
      ctx.fillStyle = P.cream;
      ctx.strokeStyle = P.ink;
      ctx.lineWidth = 1.6;
      ctx.font = `700 ${(9 + i * 3) * s}px Fredoka, sans-serif`;
      ctx.textAlign = 'center';
      ctx.strokeText('z', zx, zy);
      ctx.fillText('z', zx, zy);
      ctx.restore();
    }
  }
}

/** A small "mew" that floats up from the cat. p goes 0..1. */
export function drawMew(ctx, x, y, p, s = 1) {
  ctx.save();
  ctx.globalAlpha = Math.sin(Math.min(1, p) * Math.PI) * 0.95;
  ctx.fillStyle = P.cream;
  ctx.strokeStyle = P.ink;
  ctx.lineWidth = 3;
  ctx.lineJoin = 'round';
  ctx.font = `700 ${15 * s}px Fredoka, sans-serif`;
  ctx.textAlign = 'center';
  const yy = y - p * 22 * s;
  ctx.strokeText('mew', x, yy);
  ctx.fillText('mew', x, yy);
  ctx.restore();
}

/** A drawn heart, for when the cat wakes. p goes 0..1. */
export function drawHeart(ctx, x, y, p, size = 1) {
  ctx.save();
  ctx.translate(x, y - p * 34);
  ctx.scale(size * (0.6 + p * 0.5), size * (0.6 + p * 0.5));
  ctx.globalAlpha = 1 - p * p;
  ctx.beginPath();
  ctx.moveTo(0, 6);
  ctx.bezierCurveTo(-13, -4, -8, -14, 0, -7);
  ctx.bezierCurveTo(8, -14, 13, -4, 0, 6);
  ctx.fillStyle = P.jelly;
  ctx.strokeStyle = P.ink;
  ctx.lineWidth = 2.4;
  ctx.lineJoin = 'round';
  ctx.fill();
  ctx.stroke();
  ctx.restore();
}

// ---------------------------------------------------------------------- bean
export function drawBean(ctx, x, y, s, t, seed) {
  const R = rng(seed * 991 + 13);
  const bob = Math.sin(t * 2.6) * 2.4;
  withLocal(ctx, x, y - 12 + bob, s, () => {
    // Shadow stays on the floor.
    ctx.save();
    ctx.translate(0, 12 - bob);
    ctx.fillStyle = 'rgba(58,30,14,0.28)';
    ctx.beginPath();
    ctx.ellipse(0, 0, 10 - bob * 0.3, 3.4, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
    ctx.rotate(Math.sin(t * 1.4 + seed) * 0.16 - 0.3);
    ell(ctx, 0, 0, 9.5, 12.5, { fill: '#8a5230', line: 3, R, n: 16 });
    ell(ctx, -2.6, -3.6, 3, 5.6, { fill: '#c98a57', line: 0, R, alpha: 0.85, rot: 0.25 });
    line(ctx, [[0.5, -11], [-2.6, -4], [2.6, 2], [-0.5, 11]], { color: '#2e1a0c', w: 2.6, R, j: 0.3 });
    // Glint.
    const g = 0.5 + 0.5 * Math.sin(t * 3.4 + seed * 2);
    ctx.strokeStyle = `rgba(255,244,200,${0.15 + g * 0.8})`;
    ctx.lineWidth = 1.6;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(11, -14 - g * 2);
    ctx.lineTo(11, -8 + g * 2);
    ctx.moveTo(8, -11);
    ctx.lineTo(14, -11);
    ctx.stroke();
  });
}
