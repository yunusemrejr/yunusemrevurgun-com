#!/usr/bin/env node
/* Fig. 1 — the Analytical Engine: a computer made of brass, drawn as a bench model.
 *
 * Babbage's Analytical Engine (designed 1834-1871, never finished) is the first design for a general
 * purpose computer, and Ada Lovelace's 1843 Note G is the first program written for it. This figure
 * shows its organs on one bench plate, left to right:
 *
 *   operation cards   a Jacquard prism with a chain of punched cards hanging from it: which operation
 *                     to do next. The card being read is the one lit part
 *   the Store         eight columns of decimal digit wheels, V1 to V8, four wheels per column
 *   the Mill          three columns of larger wheels (two ingress axes, one egress axis) where the
 *                     arithmetic happens; a toothed rack along the front carries numbers between columns
 *   variable cards    a second prism and chain: which Store column to read or write
 *
 * What the wheels show is the starting state of Note G: V1 = 1, V2 = 2 and V3 = n = 4 in the Store, and
 * the Mill's ingress axes loaded with the 2 and the 4 that its first operation multiplies. Every wheel's
 * scale is turned by its digit: the zero mark is the long tick, and the dot at the front rim is the fixed
 * index, so the digit is read by counting ticks from the dot to the long one.
 *
 * Built with the Anatomy kit (dev/anatomy/kit/iso-kit.mjs, MIT), same camera, bench and palette as
 * rack.mjs. Run: node dev/anatomy/engine.mjs  -> assets/anatomy/engine.svg
 */
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import * as k from "./kit/iso-kit.mjs";
import { SITE_TOKENS } from "./site-theme.mjs";

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT = join(HERE, "..", "..", "assets", "anatomy");
const WIDTH = 640;
const HEIGHT = 310;

// ---- world -------------------------------------------------------------------------------------------
const PLATE = { x: -176, y: -34, w: 352, d: 74, r: 9 };
const PLATE_H = 12;
const FOOT_H = 5;

// the frame: end plates, base, beam
const X0 = -113; // left end plate's right face
const X1 = 120; // right end plate's left face
const PLATE_T = 5;
const FRAME_Y = -22;
const FRAME_D = 34; // base front face at y = 12
const END_Y = -14; // the end plates are shallower than the base: they carry the beam, not the wheels
const END_D = 16;
const BASE_H = 6;
const TOP_Z = 52;
const BEAM = { x: X0, y: -6, w: X1 - X0, d: 8, r: 1.5 };
const BEAM_Z = 46;
const BEAM_H = 4;

// wheels: four per column, a 9 unit pitch, 3 units thick, spacers fill the gap
const WHEEL_Z0 = 8;
const PITCH = 9;
const WHEEL_H = 3;
const WHEELS = 4;
const RACK_LEVEL = 1; // the rack meshes with the second wheel of every column

const STORE = Array.from({ length: 8 }, (_, i) => ({
  x: -100 + i * 16,
  y: -2,
  r: 8,
  teeth: 20,
  name: `V${i + 1}`,
  // Note G's start: V1 = 1, V2 = 2, V3 = n = 4, the rest empty. Units wheel first.
  value: [1, 2, 4, 0, 0, 0, 0, 0][i],
}));
const MILL = [
  { x: 46, y: -5, r: 11, teeth: 28, name: "A0", value: 2 }, // ingress axis: the 2 of V2 x V3
  { x: 74, y: -5, r: 11, teeth: 28, name: "A1", value: 4 }, // ingress axis: the 4 of V2 x V3
  { x: 102, y: -5, r: 11, teeth: 28, name: "egress", value: 0 },
];
const COLUMNS = [...STORE, ...MILL];
const FRONT = 6; // every wheel's front tangent, so one rack meshes with all of them

const RACK = { x: X0, y: FRONT, w: X1 - X0, d: 3.4, r: 0.8 };
const RACK_Z = WHEEL_Z0 + RACK_LEVEL * PITCH - 0.5;
const RACK_H = WHEEL_H + 1;

const READER_W = 36;
const READER_D = 34;
const CARD = { w: 20, h: 6.4, pitch: 7.2, y: 4, t: 1.4 };
const PRISM = { y: -14, w: 24, d: 18, r: 2 };
const PRISM_Z = 36;
const PRISM_H = 16;
// two readers, as the engine was to have: operation cards on the left, variable cards on the right
const OPERATION_X = -166;
const VARIABLE_X = 132;


const P = k.fitProjection(
  [
    ...k.boxCorners(PLATE, -PLATE_H - FOOT_H, 0),
    [OPERATION_X, -22, PRISM_Z + PRISM_H + 4],
    [X1 + PLATE_T, END_Y, TOP_Z + 2],
    [X0 - PLATE_T, END_Y, TOP_Z + 2],
    [VARIABLE_X + READER_W, 12, PRISM_Z + PRISM_H + 4],
  ],
  WIDTH,
  HEIGHT,
  { pad: 20, azimuth: 58, elevation: 28 },
);

const at = (point) => k.iso(point, P);
const S = (paths, style) => k.solidSvg(paths, style);
const L = (d, style) => k.lineSvg(d, style);
const D = (points, style) => k.dotsSvg(points, style);
const byDepth = (points) => [...points].sort((a, b) => k.depthOf([a[0], a[1], 0], P) - k.depthOf([b[0], b[1], 0], P));

/** A rounded rectangle drawn on the +y face at depth y, from face coordinates (x, z). */
function faceRect(x0, z0, x1, z1, r, y, steps = 3) {
  const ring = k.roundedPlan({ x: x0, y: z0, w: x1 - x0, d: z1 - z0, r }, steps);
  return k.pathOf(ring.map(([u, z]) => at([u, y, z])), true);
}
const faceLine = (x0, z0, x1, z1, y) => k.pathOf([at([x0, y, z0]), at([x1, y, z1])]);
const faceDot = (x, z, y) => at([x, y, z]);
const faceCircle = (x, z, r, y, steps = 14) => k.sideRing([x, y, z], r, "y", P, steps);

// ---- the bench ---------------------------------------------------------------------------------------
function bench() {
  const out = [`<path class="iso-halo" d="${k.haloOf(PLATE, -PLATE_H - FOOT_H, PLATE_H, P)}"/>`];
  for (const [x, y] of byDepth(k.corners(PLATE, 24))) {
    out.push(S(k.cylinder(x, y, 7.4, -PLATE_H - FOOT_H, 1.6, P, 28), { tone: "lo" }));
    out.push(S(k.cylinder(x, y, 5.6, -PLATE_H - FOOT_H + 1.6, FOOT_H - 1.6, P, 28), { tone: "mid" }));
  }
  out.push(S(k.slabOf(PLATE, -PLATE_H, PLATE_H, P, 8, 1.6), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(PLATE, 5), 0, P), { tone: "faint" }));
  const screws = k.corners(PLATE, 11);
  out.push(L(screws.map(([x, y]) => k.ring(x, y, 2.3, 0, P, 16)).join(""), { tone: "lo" }));
  out.push(D(screws.map(([x, y]) => at([x, y, 0])), { size: 0.5 }));
  // ruler strip along the front edge, ticks hanging from its rear edge
  const strip = { x: -150, y: PLATE.y + PLATE.d - 16, w: 276, d: 7, r: 1 };
  out.push(S(k.slabOf(strip, 0, 0.6, P, 3), { tone: "lo", crease: "none" }));
  const ticks = k.topTicks(strip.x + 4, strip.x + strip.w - 4, 4, 5, strip.y + 0.8, 0.6, [2, 3.6], P);
  out.push(L(ticks.minor, { tone: "lo" }), L(ticks.major, { tone: "mid" }));
  out.push(D([at([strip.x + 2, strip.y + 3.5, 0.6]), at([strip.x + strip.w - 2, strip.y + 3.5, 0.6])], { size: 0.45 }));
  // label plate, front left: three printed lines and two screws
  const label = { x: -168, y: PLATE.y + PLATE.d - 19, w: 14, d: 13, r: 1.4 };
  out.push(S(k.slabOf(label, 0, 0.8, P, 3), { tone: "lo", crease: "none" }));
  out.push(L(k.planOutline(k.insetPlan(label, 1.5), 0.8, P, 3), { tone: "faint" }));
  out.push(L([8, 5.5, 8].map((w, i) => k.lineOnTop([label.x + 3, label.y + 3.2 + i * 3.2], [label.x + 3 + w, label.y + 3.2 + i * 3.2], 0.8, P)).join(""), { tone: "lo", free: true }));
  return out.join("");
}

// ---- the card readers: a prism with a chain of punched cards ----------------------------------------
// Six hole columns, two rows per card; 1 is a hole. The operation reader's first card is the one lit part.
const OPERATION_CARDS = [
  [[1, 0, 1, 1, 0, 1], [0, 1, 0, 0, 1, 1]],
  [[1, 1, 0, 1, 0, 0], [0, 0, 1, 1, 0, 1]],
  [[0, 1, 1, 0, 1, 0], [1, 0, 0, 1, 1, 0]],
  [[1, 0, 0, 0, 1, 1], [0, 1, 1, 0, 0, 1]],
];
const VARIABLE_CARDS = [
  [[0, 1, 0, 0, 1, 0], [1, 0, 1, 1, 0, 0]],
  [[1, 0, 0, 1, 0, 1], [0, 1, 1, 0, 1, 0]],
  [[0, 0, 1, 0, 1, 1], [1, 1, 0, 0, 0, 1]],
];

function reader(ox, cards, lit) {
  const out = [];
  const yFront = PRISM.y + PRISM.d; // 4
  const cardX = ox + 8;
  const holeX = (c) => cardX + 3.2 + c * 2.72;
  // plinth with a groove and corner screws
  const plinth = { x: ox, y: -22, w: READER_W, d: READER_D, r: 2 };
  out.push(S(k.slabOf(plinth, 0, 6, P, 4, 0.5), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(plinth, 3), 6, P, 3), { tone: "faint" }));
  const screws = k.corners(plinth, 5);
  out.push(L(screws.map(([x, y]) => k.ring(x, y, 1.5, 6, P, 12)).join(""), { tone: "lo" }));
  out.push(D(screws.map(([x, y]) => at([x, y, 6])), { size: 0.42 }));
  // two bearing uprights with the prism between them
  const upL = { x: ox + 2, y: PRISM.y, w: 4, d: PRISM.d, r: 1 };
  const prism = { x: ox + 6, y: PRISM.y, w: PRISM.w, d: PRISM.d, r: PRISM.r };
  const upR = { x: ox + 6 + PRISM.w, y: PRISM.y, w: 4, d: PRISM.d, r: 1 };
  out.push(S(k.slabOf(upL, 6, 48, P, 3, 0.4), { tone: "mid" }));
  // the prism: a square block whose face carries the pins the cards press against
  out.push(S(k.slabOf(prism, PRISM_Z, PRISM_H, P, 4, 0.5), { tone: "hi", crease: "lo" }));
  out.push(L(k.planOutline(k.insetPlan(prism, 3), PRISM_Z + PRISM_H, P, 3), { tone: "faint" }));
  out.push(L(faceRect(prism.x + 2, PRISM_Z + 2, prism.x + prism.w - 2, PRISM_Z + PRISM_H - 2, 1, yFront), { tone: "faint" }));
  const pins = [];
  const pinDots = [];
  for (let c = 0; c < 6; c++) {
    for (const z of [PRISM_Z + 5, PRISM_Z + 8, PRISM_Z + 11]) {
      pins.push(faceCircle(holeX(c), z, 0.95, yFront, 10));
      pinDots.push(faceDot(holeX(c), z, yFront));
    }
  }
  out.push(L(pins.join(""), { tone: "lo" }));
  out.push(D(pinDots, { size: 0.32, tone: "mid" }));
  out.push(S(k.slabOf(upR, 6, 48, P, 3, 0.4), { tone: "mid" }));
  for (const up of [upL, upR]) {
    const cx = up.x + up.w / 2;
    out.push(L(faceCircle(cx, PRISM_Z + 8, 1.2, up.y + up.d, 12), { tone: "lo" }));
    out.push(D([faceDot(cx, PRISM_Z + 8, up.y + up.d)], { size: 0.4 }));
  }
  // the chain hangs from the prism's lower edge: each card a thin slab with its punched holes, laced to the next
  const yf = CARD.y + CARD.t;
  const gap = CARD.pitch - CARD.h;
  cards.forEach((bits, i) => {
    const top = PRISM_Z - i * CARD.pitch;
    const z0 = top - CARD.h;
    out.push(S(k.slabOf({ x: cardX, y: CARD.y, w: CARD.w, d: CARD.t, r: 0.5 }, z0, CARD.h, P, 2, 0), { tone: "hi", lit: lit && i === 0, crease: "none" }));
    const holes = [];
    bits.forEach((row, r) => {
      const zc = z0 + CARD.h * (r === 0 ? 0.68 : 0.3);
      row.forEach((bit, c) => {
        if (bit) holes.push(faceRect(holeX(c) - 0.8, zc - 0.55, holeX(c) + 0.8, zc + 0.55, 0.3, yf, 2));
      });
    });
    out.push(L(holes.join(""), { tone: "mid" }));
    out.push(L([cardX + 1.4, cardX + CARD.w - 1.4].map((x) => faceCircle(x, z0 + CARD.h / 2, 0.6, yf, 8)).join(""), { tone: "lo" }));
    if (i < cards.length - 1) {
      out.push(L(faceLine(cardX + 3, z0, cardX + 3, z0 - gap, yf) + faceLine(cardX + CARD.w - 3, z0, cardX + CARD.w - 3, z0 - gap, yf), { tone: "mid" }));
    }
  });
  return out.join("");
}

// ---- the frame ---------------------------------------------------------------------------------------
function endPlate(x) {
  const out = [];
  const p = { x, y: END_Y, w: PLATE_T, d: END_D, r: 1.2 };
  out.push(S(k.slabOf(p, 0, TOP_Z, P, 3, 0.4), { tone: "mid" }));
  const yf = END_Y + END_D;
  const cx = x + PLATE_T / 2;
  const zs = [12, 27, 42];
  out.push(L(zs.map((z) => faceCircle(cx, z, 1.1, yf, 12)).join(""), { tone: "lo" }));
  out.push(D(zs.map((z) => faceDot(cx, z, yf)), { size: 0.42 }));
  return out.join("");
}

function base() {
  const out = [];
  const b = { x: X0, y: FRAME_Y, w: X1 - X0, d: FRAME_D, r: 1 };
  const yf = FRAME_Y + FRAME_D;
  out.push(S(k.slabOf(b, 0, BASE_H, P, 3, 0.5), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(b, 3), BASE_H, P, 3), { tone: "faint" }));
  // two engraved name plates on the front face: STORE under the eight columns, MILL under the three
  for (const [x0, x1] of [[-96, -8], [40, 110]]) {
    out.push(L(faceRect(x0, 1.2, x1, 4.8, 0.8, yf), { tone: "lo" }));
    out.push(L(faceLine(x0 + 3, 3, x0 + (x1 - x0) * 0.32, 3, yf), { tone: "lo", free: true }));
    out.push(D([faceDot(x1 - 2.2, 3, yf)], { size: 0.4, tone: "mid" }));
  }
  // screws at the corners of the base's top
  const screws = [
    [X0 + 5, FRAME_Y + 5],
    [X1 - 5, FRAME_Y + 5],
    [X0 + 5, yf - 4],
    [X1 - 5, yf - 4],
  ];
  out.push(L(screws.map(([x, y]) => k.ring(x, y, 1.4, BASE_H, P, 10)).join(""), { tone: "lo" }));
  out.push(D(screws.map(([x, y]) => at([x, y, BASE_H])), { size: 0.4 }));
  return out.join("");
}

function beam() {
  const out = [];
  out.push(S(k.slabOf(BEAM, BEAM_Z, BEAM_H, P, 3, 0.4), { tone: "mid" }));
  const top = BEAM_Z + BEAM_H;
  // a ruler strip along the top with the axle screws below it
  const strip = { x: X0 + 5, y: BEAM.y + 1.2, w: BEAM.w - 10, d: 3.2, r: 0.6 };
  out.push(S(k.slabOf(strip, top, 0.4, P, 2), { tone: "lo", crease: "none" }));
  const ticks = k.topTicks(strip.x + 2, strip.x + strip.w - 2, 4, 5, strip.y + 0.5, top + 0.4, [1.2, 2.2], P);
  out.push(L(ticks.minor, { tone: "lo" }), L(ticks.major, { tone: "mid" }));
  // axle caps: one screw per column on the front of the beam
  const yf = BEAM.y + BEAM.d;
  out.push(L(COLUMNS.map((c) => faceCircle(c.x, BEAM_Z + BEAM_H / 2, 1, yf, 10)).join(""), { tone: "lo" }));
  out.push(D(COLUMNS.map((c) => faceDot(c.x, BEAM_Z + BEAM_H / 2, yf)), { size: 0.38 }));
  return out.join("");
}

// ---- digit wheels ------------------------------------------------------------------------------------
/** The wheel's scale: ten ticks, the long one is zero, turned so the digit d sits at the front index. */
function wheel(c, zi, digit) {
  const out = [];
  const z = WHEEL_Z0 + zi * PITCH;
  const top = z + WHEEL_H;
  const steps = c.r > 9 ? 36 : 28;
  out.push(S(k.cylinder(c.x, c.y, c.r, z, WHEEL_H, P, steps, 0.3), { tone: "hi", crease: "lo" }));
  // gear teeth on the rim, edge to edge
  out.push(L(k.knurl(c.x, c.y, c.r, z, top, c.teeth, P, 0), { tone: "lo" }));
  // scale: a ring and ten ticks hanging inward from it; angle 0 is -y and runs clockwise, front is pi
  const rr = c.r - 1.3;
  out.push(L(k.ring(c.x, c.y, rr, top, P, steps), { tone: "lo" }));
  const t = k.radialTicks(c.x, c.y, rr, 10, 10, top, [1.5, 2.8], P, Math.PI - (digit * Math.PI * 2) / 10);
  out.push(L(t.minor, { tone: "lo" }), L(t.major, { tone: "hi" }));
  // the fixed index dot on the ring at the front
  out.push(D([at([c.x, c.y + rr, top])], { size: 0.55, tone: "hi" }));
  return out.join("");
}

function column(c) {
  const out = [];
  const digits = [c.value % 10, 0, 0, 0]; // units wheel at the bottom, the values here are single digits
  out.push(S(k.cylinder(c.x, c.y, 1.6, BASE_H, BEAM_Z - BASE_H, P, 12), { tone: "mid", crease: "none" }));
  for (let i = 0; i < WHEELS; i++) {
    const zb = i === 0 ? BASE_H : WHEEL_Z0 + (i - 1) * PITCH + WHEEL_H;
    const zt = WHEEL_Z0 + i * PITCH;
    out.push(S(k.cylinder(c.x, c.y, 3, zb, zt - zb, P, 14), { tone: "mid", crease: "none" }));
    out.push(wheel(c, i, digits[i]));
  }
  return out.join("");
}

// ---- the rack ----------------------------------------------------------------------------------------
function brackets() {
  const out = [];
  for (const x of [-92, -28, 60, 114]) {
    const b = { x: x - 2, y: FRONT, w: 4, d: RACK.d, r: 0.6 };
    out.push(S(k.slabOf(b, BASE_H, RACK_Z - BASE_H, P, 2, 0), { tone: "mid", crease: "none" }));
    out.push(D([faceDot(x, 11, FRONT + RACK.d)], { size: 0.4, tone: "mid" }));
  }
  return out.join("");
}

function rack() {
  const out = [];
  const yf = RACK.y + RACK.d;
  const top = RACK_Z + RACK_H;
  out.push(S(k.slabOf(RACK, RACK_Z, RACK_H, P, 2, 0.4), { tone: "hi", crease: "lo" }));
  // teeth: one every 2.5 units, which is the tooth pitch of the 20-tooth, 8 unit wheels (2 pi 8 / 20)
  const t = k.sideTicks(RACK.x + 1.25, RACK.x + RACK.w - 1, 2.5, 4, yf, top, [2, 2], P);
  out.push(L(t.minor, { tone: "lo" }), L(t.major, { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(RACK, 0.9), top, P, 2), { tone: "faint" }));
  return out.join("");
}

// ---- the divider -------------------------------------------------------------------------------------
function divider() {
  const out = [];
  const d = { x: 26, y: -14, w: 3, d: 20, r: 0.6 };
  out.push(S(k.slabOf(d, BASE_H, BEAM_Z - BASE_H, P, 2, 0.3), { tone: "mid" }));
  const yf = d.y + d.d;
  out.push(L(faceRect(d.x + 0.7, 14, d.x + d.w - 0.7, 38, 0.5, yf, 2), { tone: "faint" }));
  return out.join("");
}

// ---- assemble ----------------------------------------------------------------------------------------
const items = [
  bench(),
  reader(OPERATION_X, OPERATION_CARDS, true),
  endPlate(X0 - PLATE_T),
  base(),
];
for (const c of [...COLUMNS].sort((a, b) => a.x - b.x)) {
  items.push(column(c));
  if (c.name === "V8") items.push(divider()); // between the last Store column and the first Mill column
}
items.push(brackets(), rack(), beam(), endPlate(X1), reader(VARIABLE_X, VARIABLE_CARDS, false));

const label =
  "The Analytical Engine as a bench model: a card reader with a chain of punched Jacquard cards, a Store of eight columns of decimal digit wheels, a Mill of three columns of larger wheels, and a toothed rack along the front that carries numbers between the columns, with  at the right. The wheels are set to the starting values of Ada Lovelace's Note G, and the card being read is lit.";
const svg = k.figureSvg({ width: WIDTH, height: HEIGHT, label, body: items, standalone: "light" }).replace("</style>", `${SITE_TOKENS}</style>`);

mkdirSync(OUT, { recursive: true });
writeFileSync(join(OUT, "engine.svg"), svg + "\n");
console.log("wrote assets/anatomy/engine.svg", svg.length, "bytes");
