/* =============================================================================
   Drawing toolkit for the shop's hand-drawn look.

   The look is borrowed from the way Club Penguin painted its rooms: flat,
   saturated colour blocks with one soft shade and one highlight, dark warm
   outlines that wobble a little, and fills that sit a hair off the outline like
   a cheap print. Everything here is deterministic, seeded, so a baked room
   never shimmers.
   ========================================================================== */

export const P = {
  ink: '#2b1810',
  inkSoft: '#5a3a26',
  plaster: '#f0c98e',
  plasterLight: '#f8dfae',
  plasterShade: '#d8a468',
  spruce: '#2f6a5a',
  spruceLight: '#458a72',
  spruceDark: '#1f4a3f',
  wood: '#c58a4f',
  woodLight: '#e0a86a',
  woodDark: '#8f5a30',
  woodDeep: '#5e3a1f',
  floor: '#cf9459',
  floorDark: '#a96f3d',
  floorLight: '#e4ad70',
  brass: '#e2aa3e',
  brassLight: '#ffdc82',
  brassDark: '#a87420',
  jelly: '#dc1b1b',
  jellyDark: '#9e0e14',
  jellyLight: '#ff5a4a',
  navy: '#252e56',
  navyLight: '#3d4a86',
  cream: '#f6e6c8',
  creamShade: '#dcc79f',
  stone: '#aa9d8b',
  stoneLight: '#cabfad',
  stoneDark: '#786b5d',
  night: '#1b2758',
  nightLight: '#3c5396',
  leaf: '#4f9c5b',
  leafDark: '#2d6b3e',
  leafLight: '#82c66f',
  cup: '#f4ead5',
  coffee: '#5a331b',
  lamp: '#ffd89a',
  fire: '#ff8a1f',
  fireLight: '#ffd24a',
};

export const clamp = (v, lo, hi) => (v < lo ? lo : v > hi ? hi : v);
export const lerp = (a, b, t) => a + (b - a) * t;

export function rng(seed) {
  let s = (seed >>> 0) || 1;
  return () => {
    s ^= s << 13; s >>>= 0;
    s ^= s >>> 17;
    s ^= s << 5; s >>>= 0;
    return s / 4294967296;
  };
}

// Mix two #rrggbb colours.
export function mix(a, b, t) {
  const pa = parseInt(a.slice(1), 16);
  const pb = parseInt(b.slice(1), 16);
  const c = (sh) => Math.round(lerp((pa >> sh) & 255, (pb >> sh) & 255, t));
  return `rgb(${c(16)},${c(8)},${c(0)})`;
}

const noise = () => Math.random();

// Insert points along long edges so the wobble bends them instead of just
// shifting the corners.
function densify(pts, closed, step) {
  const out = [];
  const n = pts.length;
  const last = closed ? n : n - 1;
  for (let i = 0; i < last; i++) {
    const a = pts[i];
    const b = pts[(i + 1) % n];
    const len = Math.hypot(b[0] - a[0], b[1] - a[1]);
    const k = Math.max(1, Math.round(len / step));
    for (let j = 0; j < k; j++) out.push([lerp(a[0], b[0], j / k), lerp(a[1], b[1], j / k)]);
  }
  if (!closed) out.push(pts[n - 1]);
  return out;
}

function tracePath(ctx, q, closed, smooth) {
  ctx.beginPath();
  if (smooth && q.length > 2) {
    const n = q.length;
    if (closed) {
      const mid = (i) => [(q[i][0] + q[(i + 1) % n][0]) / 2, (q[i][1] + q[(i + 1) % n][1]) / 2];
      const m0 = mid(n - 1);
      ctx.moveTo(m0[0], m0[1]);
      for (let i = 0; i < n; i++) {
        const m = mid(i);
        ctx.quadraticCurveTo(q[i][0], q[i][1], m[0], m[1]);
      }
      ctx.closePath();
    } else {
      ctx.moveTo(q[0][0], q[0][1]);
      for (let i = 1; i < n - 1; i++) {
        const mx = (q[i][0] + q[i + 1][0]) / 2;
        const my = (q[i][1] + q[i + 1][1]) / 2;
        ctx.quadraticCurveTo(q[i][0], q[i][1], mx, my);
      }
      ctx.lineTo(q[n - 1][0], q[n - 1][1]);
    }
  } else {
    ctx.moveTo(q[0][0], q[0][1]);
    for (let i = 1; i < q.length; i++) ctx.lineTo(q[i][0], q[i][1]);
    if (closed) ctx.closePath();
  }
}

/**
 * A hand-drawn shape: a flat fill sitting slightly off a wobbling ink outline.
 * o: fill, line (width; 0 for none), lineColor, j (wobble px), R (rng), off,
 *    smooth, closed, alpha.
 */
export function shape(ctx, pts, o = {}) {
  const { fill, line = 3, lineColor = P.ink, j = 1.1, R = noise, off = 1.2, smooth = false, closed = true, alpha = 1, step = 30 } = o;
  const base = densify(pts, closed, step);
  const q = base.map(([x, y]) => [x + (R() * 2 - 1) * j, y + (R() * 2 - 1) * j]);
  ctx.save();
  ctx.globalAlpha = alpha;
  if (fill) {
    ctx.save();
    ctx.translate(off, off * 0.8);
    tracePath(ctx, q, closed, smooth);
    ctx.fillStyle = fill;
    ctx.fill();
    ctx.restore();
  }
  if (line > 0) {
    tracePath(ctx, q, closed, smooth);
    ctx.strokeStyle = lineColor;
    ctx.lineWidth = line;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();
  }
  ctx.restore();
  return q;
}

export function ellipsePts(cx, cy, rx, ry, n = 22, rot = 0) {
  const pts = [];
  const c = Math.cos(rot);
  const s = Math.sin(rot);
  for (let i = 0; i < n; i++) {
    const a = (i / n) * Math.PI * 2;
    const x = Math.cos(a) * rx;
    const y = Math.sin(a) * ry;
    pts.push([cx + x * c - y * s, cy + x * s + y * c]);
  }
  return pts;
}

export function ell(ctx, cx, cy, rx, ry, o = {}) {
  return shape(ctx, ellipsePts(cx, cy, rx, ry, o.n || 22, o.rot || 0), { smooth: true, ...o });
}

export function rect(ctx, x, y, w, h, o = {}) {
  return shape(ctx, [[x, y], [x + w, y], [x + w, y + h], [x, y + h]], o);
}

// A rounded rectangle as points, for cushions, cups and signs.
export function rrPts(x, y, w, h, r) {
  const k = 4;
  const pts = [];
  const arc = (cx, cy, a0) => {
    for (let i = 0; i <= k; i++) {
      const a = a0 + (i / k) * (Math.PI / 2);
      pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
    }
  };
  arc(x + w - r, y + r, -Math.PI / 2);
  arc(x + w - r, y + h - r, 0);
  arc(x + r, y + h - r, Math.PI / 2);
  arc(x + r, y + r, Math.PI);
  return pts;
}

export function rrect(ctx, x, y, w, h, r, o = {}) {
  return shape(ctx, rrPts(x, y, w, h, r), { smooth: false, step: 40, ...o });
}

/** A single inked line. */
export function line(ctx, pts, o = {}) {
  const { color = P.ink, w = 2.5, j = 0.9, R = noise, alpha = 1, smooth = true, step = 26 } = o;
  const q = densify(pts, false, step).map(([x, y]) => [x + (R() * 2 - 1) * j, y + (R() * 2 - 1) * j]);
  ctx.save();
  ctx.globalAlpha = alpha;
  tracePath(ctx, q, false, smooth);
  ctx.strokeStyle = color;
  ctx.lineWidth = w;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.stroke();
  ctx.restore();
}

/** Run `fn` clipped to a polygon, for shading bands and highlights that stay in the shape. */
export function clipped(ctx, pts, fn) {
  ctx.save();
  ctx.beginPath();
  ctx.moveTo(pts[0][0], pts[0][1]);
  for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
  ctx.closePath();
  ctx.clip();
  fn();
  ctx.restore();
}

/** Pencil hatching across a box. */
export function hatch(ctx, x, y, w, h, o = {}) {
  const { angle = -0.9, gap = 6, color = P.ink, alpha = 0.16, width = 1.4, R = noise } = o;
  ctx.save();
  ctx.beginPath();
  ctx.rect(x, y, w, h);
  ctx.clip();
  ctx.strokeStyle = color;
  ctx.globalAlpha = alpha;
  ctx.lineWidth = width;
  ctx.lineCap = 'round';
  const d = Math.hypot(w, h);
  const cx = x + w / 2;
  const cy = y + h / 2;
  const dx = Math.cos(angle);
  const dy = Math.sin(angle);
  const nx = -dy;
  const ny = dx;
  for (let t = -d / 2; t <= d / 2; t += gap) {
    const ox = cx + nx * t;
    const oy = cy + ny * t;
    const len = d * (0.5 + R() * 0.0);
    ctx.beginPath();
    ctx.moveTo(ox - dx * len + (R() - 0.5) * 2, oy - dy * len);
    ctx.lineTo(ox + dx * len, oy + dy * len + (R() - 0.5) * 2);
    ctx.stroke();
  }
  ctx.restore();
}

/** Soft blob shadow on the floor. */
export function groundShadow(ctx, x, y, rx, ry, alpha = 0.28) {
  ctx.save();
  const g = ctx.createRadialGradient(x, y, 1, x, y, rx);
  g.addColorStop(0, `rgba(58,30,14,${alpha})`);
  g.addColorStop(0.6, `rgba(58,30,14,${alpha * 0.55})`);
  g.addColorStop(1, 'rgba(58,30,14,0)');
  ctx.fillStyle = g;
  ctx.translate(x, y);
  ctx.scale(1, ry / rx);
  ctx.beginPath();
  ctx.arc(0, 0, rx, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();
}

/** Hand-lettered text, slightly off-baseline per glyph. */
export function handText(ctx, text, x, y, o = {}) {
  const { size = 20, color = P.cream, font = 'Fredoka, "Atkinson Hyperlegible Next", sans-serif', weight = 600, align = 'left', R = noise, alpha = 1, wobble = 1.1, spacing = 0 } = o;
  ctx.save();
  ctx.globalAlpha = alpha;
  ctx.font = `${weight} ${size}px ${font}`;
  ctx.fillStyle = color;
  ctx.textBaseline = 'alphabetic';
  const widths = [...text].map((ch) => ctx.measureText(ch).width + spacing);
  const total = widths.reduce((a, b) => a + b, 0);
  let cx = align === 'center' ? x - total / 2 : align === 'right' ? x - total : x;
  let i = 0;
  for (const ch of text) {
    const rot = (R() - 0.5) * 0.09;
    ctx.save();
    ctx.translate(cx + widths[i] / 2, y + (R() - 0.5) * wobble * 2);
    ctx.rotate(rot);
    ctx.textAlign = 'center';
    ctx.fillText(ch, 0, 0);
    ctx.restore();
    cx += widths[i];
    i++;
  }
  ctx.restore();
}
