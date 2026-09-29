// Jelly piece artwork.
//
// Every piece is built the same way so the set reads as one mould:
//   1. a shared plinth (BASE) on a shared baseline (y = 94) — only the body above it differs;
//   2. an outline underlay: each part stroked wide in the outline colour, so only the outer
//      silhouette of the union is outlined (no lines where the plinth meets the body);
//   3. the jelly body: each part filled AND stroked with the same gradient, which rounds every
//      corner and keeps the lighting continuous across parts (the gradients use
//      userSpaceOnUse, so one light source spans the whole 100x100 piece);
//   4. one multiply-blended shade group (opaque paths inside a group, so overlaps never
//      double the darkening) that makes the bottom of the jelly denser than the top;
//   5. seams, a bounce-light line on the plinth, and the specular highlights.
//
// Paths are inlined into every piece SVG with explicit attributes rather than <use>: a <use>
// clone sits in a shadow tree where document CSS does not match, which once rendered invisible
// pieces. The gradients are injected once by ensureDefs(), so a page needs no sprite markup.

const SVG_NS = "http://www.w3.org/2000/svg";
const DEFS_ID = "ck-jelly-defs";

export const PIECE_TYPES = ["p", "n", "b", "r", "q", "k"];
export const PROMOTION_TYPES = ["q", "r", "b", "n"];

const NAMES = {
  p: "pawn",
  n: "knight",
  b: "bishop",
  r: "rook",
  q: "queen",
  k: "king",
};
const FLAVOURS = { w: "milk jelly", b: "blackcurrant jelly" };

/** Per-flavour paint. Outlines are dark plum on both sides so pieces separate from lime/berry squares. */
const PAINT = {
  w: {
    fill: "url(#ckMilk)",
    outline: "#5b2b52",
    shade: 0.85,
    gloss: 0.9,
    glossSoft: 0.55,
    bounce: "#ffe9f0",
    bounceOpacity: 0.75,
    seam: "#8a4a78",
    seamOpacity: 0.38,
  },
  b: {
    fill: "url(#ckCurrant)",
    outline: "#16061a",
    shade: 0.6,
    gloss: 0.62,
    glossSoft: 0.38,
    bounce: "#d59ccc",
    bounceOpacity: 0.7,
    seam: "#0f0412",
    seamOpacity: 0.55,
  },
};

const OUTLINE_WIDTH = 7.5; // silhouette outline: outline colour, drawn under the jelly
const JELLY_STROKE = 4; // same-gradient stroke that rounds the corners of every part

// The plinth every piece stands on: x 21..79, y 80..94.
const BASE = "M21 87c0-4 3-7 7-7h44c4 0 7 3 7 7s-3 7-7 7H28c-4 0-7-3-7-7z";
const BASE_SEAM = "M25 83c10-2 40-2 50 0";
const BASE_BOUNCE = "M28 91.2c9 2 35 2 44 0";

// A collar is the narrow ring under a head (pawn, bishop, queen); a skirt is the flared body
// under it. They are shared so all three pieces have the same lower half.
const collar = (half) =>
  `M${50 - half} 46h${half * 2}c2 0 3 1 3 3s-1 3-3 3H${50 - half}c-2 0-3-1-3-3s1-3 3-3z`;
/** A full circle as path data (two half-arcs; a single arc cannot close on itself). */
const dot = (cx, cy, r) =>
  `M${cx - r} ${cy}a${r} ${r} 0 1 0 ${r * 2} 0a${r} ${r} 0 1 0-${r * 2} 0z`;

/**
 * Body parts per piece, back to front. `seams` are thin creases, `gloss` are specular ellipses
 * (cx, cy, rx, ry, rot), `eyes` are dark dots with their own glint (knight only).
 */
const SHAPES = {
  p: {
    parts: [
      dot(50, 32, 12),
      collar(15),
      "M42 52h16c1 9 5 17 11 23 2 2 1 5-3 5H34c-4 0-5-3-3-5 6-6 10-14 11-23z",
    ],
    seams: ["M36 51.5h28"],
    gloss: [
      [44, 28, 5.2, 3.4, -30],
      [40, 34.5, 1.6, 1.6, 0],
    ],
  },
  r: {
    parts: [
      "M29 16h10v9h6v-9h10v9h6v-9h10v22H29z",
      "M34 36h32l2 32H32z",
      "M27 66h46c2 0 3 1 3 3v9c0 1-1 2-2 2H26c-1 0-2-1-2-2v-9c0-2 1-3 3-3z",
    ],
    seams: ["M31 37.5h38", "M27 68h46"],
    gloss: [
      [36, 25, 3.2, 4.6, 0],
      [39.5, 51, 2.6, 9, 4],
    ],
  },
  n: {
    parts: [
      "M31 80C31 70 29 62 36 55C40 51 45 50 46 46C38 48 30 52 24 51C19 50 16 45 19 41C22 36 30 29 35 22L36 9L44 14C52 8 63 12 68 24C75 40 74 60 71 80Z",
      "M27 70h46c2 0 3 1 3 3v5c0 1-1 2-2 2H26c-1 0-2-1-2-2v-5c0-2 1-3 3-3z",
    ],
    seams: ["M52 21c8 4 13 14 13 28", "M27 72h46"],
    gloss: [
      [40, 20, 3.6, 5, 28],
      [50, 30, 2.8, 2.8, 0],
    ],
    eyes: [{ cx: 36, cy: 31, r: 2.5 }],
    nostril: { cx: 21.5, cy: 44 },
  },
  b: {
    parts: [
      dot(50, 11.5, 5.5),
      "M50 14c9 8 16 17 16 27 0 6-3 11-7 13H41c-4-2-7-7-7-13 0-10 7-19 16-27z",
      "M35 52h30c2 0 3 1 3 3s-1 3-3 3H35c-2 0-3-1-3-3s1-3 3-3z",
      "M42 58h16c1 8 5 14 11 19 3 3 1 5-3 5H34c-4 0-6-2-3-5 6-5 10-11 11-19z",
    ],
    seams: ["M53 25l-8 13", "M35 53h30"],
    gloss: [
      [43.5, 34, 3.6, 7.5, 16],
      [45.5, 22, 1.7, 1.7, 0],
    ],
  },
  q: {
    parts: [
      "M27 28L35 52H65L73 28L64 38L61.5 20L56 38L50 18L44 38L38.5 20L36 38Z",
      [[27, 25.5], [38.5, 16.5], [50, 12.5], [61.5, 16.5], [73, 25.5]].map(([x, y]) => dot(x, y, 4)).join(""),
      "M33 52h34c2 0 3 1 3 3s-1 3-3 3H33c-2 0-3-1-3-3s1-3 3-3z",
      "M40 58h20c1 8 6 14 12 19 3 3 1 5-3 5H31c-4 0-6-2-3-5 6-5 11-11 12-19z",
    ],
    seams: ["M33 53h34"],
    gloss: [
      [43, 42, 2.6, 6.5, 14],
      [37.5, 15.5, 1.5, 1.5, 0],
    ],
  },
  k: {
    parts: [
      "M46 4h8v7h7v8h-7v7h-8v-7h-7v-8h7z",
      "M50 24c-13 0-23 8-23 20 0 4 2 7 4 10h38c2-3 4-6 4-10 0-12-10-20-23-20z",
      "M33 52h34c2 0 3 1 3 3s-1 3-3 3H33c-2 0-3-1-3-3s1-3 3-3z",
      "M40 58h20c1 8 6 14 12 19 3 3 1 5-3 5H31c-4 0-6-2-3-5 6-5 11-11 12-19z",
    ],
    seams: ["M33 53h34"],
    gloss: [
      [40, 38, 3.6, 7, 22],
      [43, 10, 1.6, 2.2, 0],
    ],
  },
};

function assertPiece(color, type) {
  if (!Object.hasOwn(FLAVOURS, color))
    throw new Error(`unknown piece colour: ${color}`);
  if (!Object.hasOwn(SHAPES, type))
    throw new Error(`unknown piece type: ${type}`);
}

export function pieceName(color, type) {
  assertPiece(color, type);
  return `${color === "w" ? "white" : "black"} ${NAMES[type]}`;
}

export function pieceFlavour(color) {
  return FLAVOURS[color] || FLAVOURS.w;
}

function el(doc, name, attrs = {}, parent = null) {
  const node = doc.createElementNS(SVG_NS, name);
  for (const [key, value] of Object.entries(attrs))
    if (value !== null && value !== undefined)
      node.setAttribute(key, String(value));
  if (parent) parent.appendChild(node);
  return node;
}

/** Inject the shared gradients once. All are userSpaceOnUse: one light source per 100x100 piece. */
function ensureDefs(doc) {
  if (!doc || doc.getElementById(DEFS_ID)) return;
  const host = el(doc, "svg", {
    id: DEFS_ID,
    "aria-hidden": "true",
    focusable: "false",
    width: 0,
    height: 0,
    style: "position:absolute;width:0;height:0;overflow:hidden",
  });
  const defs = el(doc, "defs", {}, host);

  const milk = el(
    doc,
    "radialGradient",
    { id: "ckMilk", gradientUnits: "userSpaceOnUse", cx: 36, cy: 24, r: 92 },
    defs,
  );
  [
    ["0", "#ffffff"],
    ["0.34", "#fff4ec"],
    ["0.7", "#f9dccb"],
    ["1", "#ebbfae"],
  ].forEach(([offset, color]) =>
    el(doc, "stop", { offset, "stop-color": color }, milk),
  );

  const currant = el(
    doc,
    "radialGradient",
    { id: "ckCurrant", gradientUnits: "userSpaceOnUse", cx: 36, cy: 24, r: 92 },
    defs,
  );
  [
    ["0", "#a5629f"],
    ["0.3", "#6e3069"],
    ["0.66", "#45193f"],
    ["1", "#2b0f2c"],
  ].forEach(([offset, color]) =>
    el(doc, "stop", { offset, "stop-color": color }, currant),
  );

  // Opaque on purpose: it is composited with mix-blend-mode: multiply as one group.
  const shade = el(
    doc,
    "linearGradient",
    {
      id: "ckShade",
      gradientUnits: "userSpaceOnUse",
      x1: 0,
      y1: 14,
      x2: 0,
      y2: 96,
    },
    defs,
  );
  [
    ["0", "#ffffff"],
    ["0.4", "#ffffff"],
    ["1", "#b98db0"],
  ].forEach(([offset, color]) =>
    el(doc, "stop", { offset, "stop-color": color }, shade),
  );

  doc.body.appendChild(host);
}

function paintParts(doc, parent, parts, attrs) {
  for (const d of parts) el(doc, "path", { d, ...attrs }, parent);
}

/** A jelly piece as a DOM node. Paint order is documented at the top of this file. */
export function pieceElement(color, type, documentRef = globalThis.document) {
  assertPiece(color, type);
  ensureDefs(documentRef);
  const shape = SHAPES[type];
  const paint = PAINT[color];
  const parts = [...shape.parts, BASE];

  const svg = el(documentRef, "svg", {
    class: `piece ${color}`,
    viewBox: "0 0 100 100",
    role: "img",
    "aria-label": pieceName(color, type),
  });

  // 2. silhouette outline
  const outline = el(documentRef, "g", {}, svg);
  paintParts(documentRef, outline, parts, {
    fill: paint.outline,
    stroke: paint.outline,
    "stroke-width": OUTLINE_WIDTH,
    "stroke-linejoin": "round",
  });

  // 3. jelly body
  const body = el(documentRef, "g", {}, svg);
  paintParts(documentRef, body, parts, {
    fill: paint.fill,
    stroke: paint.fill,
    "stroke-width": JELLY_STROKE,
    "stroke-linejoin": "round",
  });

  // 4. density: one multiply group so overlapping parts are shaded exactly once
  const shade = el(
    documentRef,
    "g",
    { opacity: paint.shade, style: "mix-blend-mode:multiply" },
    svg,
  );
  paintParts(documentRef, shade, parts, {
    fill: "url(#ckShade)",
    stroke: "url(#ckShade)",
    "stroke-width": JELLY_STROKE,
    "stroke-linejoin": "round",
  });

  // 5. seams + bounce light (light that passed through the jelly pools at the bottom edge)
  const details = el(documentRef, "g", { fill: "none", "stroke-linecap": "round" }, svg);
  for (const d of [...shape.seams, BASE_SEAM])
    el(
      documentRef,
      "path",
      {
        d,
        stroke: paint.seam,
        "stroke-opacity": paint.seamOpacity,
        "stroke-width": 1.4,
      },
      details,
    );
  el(
    documentRef,
    "path",
    {
      d: BASE_BOUNCE,
      stroke: paint.bounce,
      "stroke-opacity": paint.bounceOpacity,
      "stroke-width": 2.2,
    },
    details,
  );

  if (shape.eyes) {
    for (const eye of shape.eyes) {
      el(
        documentRef,
        "circle",
        { cx: eye.cx, cy: eye.cy, r: eye.r, fill: paint.outline },
        svg,
      );
      el(
        documentRef,
        "circle",
        { cx: eye.cx - 0.8, cy: eye.cy - 0.9, r: 0.8, fill: "#fff" },
        svg,
      );
    }
    if (shape.nostril)
      el(
        documentRef,
        "ellipse",
        {
          cx: shape.nostril.cx,
          cy: shape.nostril.cy,
          rx: 1.3,
          ry: 1.9,
          fill: paint.outline,
          opacity: 0.8,
        },
        svg,
      );
  }

  // specular highlights: first is the wide window-light, the rest are pin-point glints
  shape.gloss.forEach(([cx, cy, rx, ry, rot], index) => {
    el(
      documentRef,
      "ellipse",
      {
        cx,
        cy,
        rx,
        ry,
        transform: rot ? `rotate(${rot} ${cx} ${cy})` : null,
        fill: "#fff",
        opacity: index === 0 ? paint.gloss : paint.glossSoft,
      },
      svg,
    );
  });
  // a soft glint on the plinth so the base reads as the same jelly as the body
  el(
    documentRef,
    "ellipse",
    { cx: 36, cy: 84.4, rx: 7, ry: 1.5, fill: "#fff", opacity: paint.glossSoft },
    svg,
  );

  return svg;
}
