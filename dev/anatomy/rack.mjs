#!/usr/bin/env node
/* Fig. 1 — a control rack: the machine behind "Systems that have to keep running."
 *
 * A DIN-rail PLC rack on a bench plate: power supply, CPU, two digital I/O cards, an analogue card
 * and a network card, then a run of spare terminal blocks on the open rail. One part is lit (the CPU,
 * the thing that has to keep running); its RUN lamp blinks. Everything else is still.
 *
 * Built with the Anatomy kit (dev/anatomy/kit/iso-kit.mjs, MIT): solids painted back to front, four
 * facing tones, seams that end on edges, every wire ending on a screw. The kit's camera is
 * `fitProjection` with a 58 degree azimuth so the long side (the module fronts) faces the viewer.
 *
 * Run: node dev/anatomy/rack.mjs            -> assets/anatomy/rack.svg
 *      node dev/anatomy/rack.mjs --missing  -> assets/anatomy/rack-missing.svg  (the 404 figure: the CPU is
 *      lifted out of its slot, its lamps are dark and the tower's red lamp is lit — "part not found")
 */
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import * as k from "./kit/iso-kit.mjs";
import { SITE_TOKENS } from "./site-theme.mjs";

const HERE = dirname(fileURLToPath(import.meta.url));
const MISSING = process.argv.includes("--missing");
const LIFT = 62;
const OUT = join(HERE, "..", "..", "assets", "anatomy");

const WIDTH = 640;
const HEIGHT = 310;

// ---- world -------------------------------------------------------------------------------------------
const PLATE = { x: -176, y: -58, w: 352, d: 112, r: 9 };
const PLATE_H = 12;
const FOOT_H = 5;
const RAIL = { x: -164, y: -4, w: 328, d: 24, r: 1.2 };
const RAIL_H = 3.6;

const MOD_Y = -18;
const MOD_D = 44;
const YF = MOD_Y + MOD_D; // front face of every module
const PANEL = 1.4; // front plate proud of the body
const PF = YF + PANEL;
const H = 72;

const MODS = [
  { id: "psu", x: -150, w: 54, kind: "psu" },
  { id: "cpu", x: -94, w: 44, kind: "cpu" },
  { id: "di", x: -48, w: 26, kind: "io", rows: 4 },
  { id: "do", x: -20, w: 26, kind: "io", rows: 4 },
  { id: "ai", x: 8, w: 26, kind: "io", rows: 2 },
  { id: "net", x: 36, w: 34, kind: "net" },
];
const BLOCKS_X = 82; // spare terminal blocks start here
const BLOCK_W = 7.4;
const BLOCK_N = 6;
const BLOCK_H = 36;
const BLOCK_D = 30;

const P = k.fitProjection(
  [...k.boxCorners(PLATE, -PLATE_H - FOOT_H, 0), [-150, MOD_Y, H + 12 + (MISSING ? LIFT : 0)], [70, YF, H + 12], [BLOCKS_X + BLOCK_N * (BLOCK_W + 0.6), YF, 0]],
  WIDTH,
  HEIGHT,
  { pad: 22, azimuth: 58, elevation: 28 },
);

const at = (point) => k.iso(point, P);
const S = (paths, style) => k.solidSvg(paths, style);
const L = (d, style) => k.lineSvg(d, style);
const D = (points, style) => k.dotsSvg(points, style);
const byDepth = (points) => [...points].sort((a, b) => k.depthOf([a[0], a[1], 0], P) - k.depthOf([b[0], b[1], 0], P));

/** A rounded rectangle drawn on the vertical face at depth y, from face coordinates (x, z). */
function faceRect(x0, z0, x1, z1, r, y, steps = 4) {
  const ring = k.roundedPlan({ x: x0, y: z0, w: x1 - x0, d: z1 - z0, r }, steps);
  return k.pathOf(ring.map(([u, z]) => at([u, y, z])), true);
}
const faceLine = (x0, z0, x1, z1, y) => k.pathOf([at([x0, y, z0]), at([x1, y, z1])]);
const faceDot = (x, z, y) => at([x, y, z]);
const faceCircle = (x, z, r, y, steps = 18) => k.sideRing([x, y, z], r, "y", P, steps);

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
  const label = { x: -166, y: PLATE.y + PLATE.d - 34, w: 14, d: 17, r: 1.4 };
  out.push(S(k.slabOf(label, 0, 0.8, P, 3), { tone: "lo", crease: "none" }));
  out.push(L(k.planOutline(k.insetPlan(label, 1.5), 0.8, P, 3), { tone: "faint" }));
  out.push(L([8, 5.5, 8].map((w, i) => k.lineOnTop([label.x + 3, label.y + 4.5 + i * 3.4], [label.x + 3 + w, label.y + 4.5 + i * 3.4], 0.8, P)).join(""), { tone: "lo", free: true }));
  return out.join("");
}

// ---- the rail ----------------------------------------------------------------------------------------
function rail() {
  const out = [];
  out.push(S(k.slabOf(RAIL, 0, RAIL_H, P, 3, 0.4), { tone: "mid" }));
  // the two long slots of a top-hat rail, closed outlines, and the mounting holes between them
  for (const y of [RAIL.y + 5, RAIL.y + RAIL.d - 8.2]) {
    out.push(L(k.planOutline({ x: RAIL.x + 8, y, w: RAIL.w - 16, d: 3.2, r: 1.6 }, RAIL_H, P, 4), { tone: "lo" }));
  }
  const holes = [];
  for (let x = RAIL.x + 14; x < RAIL.x + RAIL.w - 10; x += 25) holes.push([x, RAIL.y + RAIL.d / 2]);
  out.push(L(holes.map(([x, y]) => k.ring(x, y, 1.5, RAIL_H, P, 12)).join(""), { tone: "lo" }));
  return out.join("");
}

// ---- a module ----------------------------------------------------------------------------------------
const plan = (m) => ({ x: m.x, y: MOD_Y, w: m.w, d: MOD_D, r: 2.4 });

function body(m, lit = false) {
  const out = [];
  out.push(S(k.slabOf(plan(m), 0, H, P, 4, 0.6), { tone: "hi", lit, crease: "lo" }));
  // recessed top with four vents (closed slots)
  out.push(L(k.planOutline(k.insetPlan(plan(m), 3), H, P, 3), { tone: "faint" }));
  const vents = [];
  for (let i = 0; i < 4; i++) vents.push(k.planOutline({ x: m.x + 6, y: MOD_Y + 9 + i * 5.2, w: m.w - 12, d: 1.5, r: 0.75 }, H, P, 3));
  out.push(L(vents.join(""), { tone: "lo" }));
  // front plate and its seam
  out.push(S(k.slabOf({ x: m.x + 1.2, y: YF, w: m.w - 2.4, d: PANEL, r: 1.2 }, 5, H - 10, P, 3, 0), { tone: "mid", crease: "none" }));
  return out.join("");
}

function terminalRow(m, z, n, tone = "lo") {
  // screw heads on the front plate: ring + slot, each ending on the plate
  const step = (m.w - 8) / Math.max(1, n - 1);
  const rings = [];
  const slots = [];
  for (let i = 0; i < n; i++) {
    const x = n === 1 ? m.x + m.w / 2 : m.x + 4 + i * step;
    rings.push(faceCircle(x, z, 1.7, PF, 14));
    slots.push(faceLine(x - 1.2, z, x + 1.2, z, PF));
  }
  return L(rings.join(""), { tone }) + L(slots.join(""), { tone: "mid" });
}

function labelWindow(m, z0, z1) {
  return L(faceRect(m.x + 4, z0, m.x + m.w - 4, z1, 1.4, PF), { tone: "lo" }) +
    L([0, 1].map((i) => faceLine(m.x + 6.2, z0 + 3.4 + i * 3.6, m.x + m.w - 6.2 - i * 5, z0 + 3.4 + i * 3.6, PF)).join(""), { tone: "lo", free: true });
}

function leds(m, z, count, kinds, gap = 4.4) {
  // a row of lamps; kinds[i] is "ok" | "err" | "dim", blink picks the pulsing ones
  const out = [];
  for (let i = 0; i < count; i++) {
    const [cx, cy] = faceDot(m.x + 5 + i * gap, z, PF);
    const kind = kinds[i];
    const blink = kind.blink ? ` data-blink="${kind.blink}" style="--at:${(i * 370) % 1800}ms"` : "";
    out.push(`<circle class="rk-led rk-led--${kind.tone}" cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="1.25"${blink}/>`);
  }
  return out.join("");
}

function psu(m) {
  const out = [body(m)];
  out.push(terminalRow(m, H - 11, 4));
  // output adjust knob: ring, knurl is too fine at this size, so a pointer and a scale of dots
  const kx = m.x + m.w * 0.66;
  const kz = 47;
  out.push(S(k.slabOf({ x: kx - 6, y: PF, w: 12, d: 2.2, r: 6 }, kz - 6, 12, P, 10, 0), { tone: "mid", crease: "none" }));
  out.push(L(faceCircle(kx, kz, 4.2, PF + 2.2, 24), { tone: "lo" }));
  out.push(L(faceLine(kx, kz, kx + 2.6, kz + 3.2, PF + 2.2), { tone: "hi" }));
  const arc = [];
  for (let a = -135; a <= 135; a += 30) arc.push(faceDot(kx + Math.sin((a * Math.PI) / 180) * 7.8, kz + Math.cos((a * Math.PI) / 180) * 7.8, PF));
  out.push(D(arc, { size: 0.4, tone: "mid" }));
  out.push(labelWindow(m, 25, 35));
  out.push(leds(m, 41, 1, [{ tone: "ok" }]));
  out.push(terminalRow(m, 14, 4));
  return out.join("");
}

function cpu(m) {
  const out = [body(m, true)];
  out.push(terminalRow(m, H - 11, 3));
  // LCD: a dark well with three lines of status text
  const [x0, x1, z0, z1] = [m.x + 5, m.x + m.w - 5, 47, 60];
  out.push(`<path class="rk-lcd" d="${faceRect(x0, z0, x1, z1, 1.6, PF)}"/>`);
  out.push(`<path class="rk-lcd-bar" d="${[0, 1, 2].map((i) => faceLine(x0 + 3, z1 - 3.4 - i * 3.6, x0 + 3 + [18, 12, 22][i], z1 - 3.4 - i * 3.6, PF)).join("")}"/>`);
  out.push(L(faceRect(x0 - 1, z0 - 1, x1 + 1, z1 + 1, 2.2, PF), { tone: "lo" }));
  // lamps: RUN blinks (the heartbeat), STOP and ERR rest, BUS blinks slowly
  out.push(leds(m, 40.5, 4, MISSING ? [{ tone: "dim" }, { tone: "dim" }, { tone: "err" }, { tone: "dim" }] : [{ tone: "ok", blink: "fast" }, { tone: "dim" }, { tone: "dim" }, { tone: "ok", blink: "slow" }]));
  // mode selector: a slot with its slider
  out.push(L(faceRect(m.x + 6, 30.5, m.x + 22, 34.5, 2, PF), { tone: "lo" }));
  out.push(S(k.slabOf({ x: m.x + 12, y: PF, w: 5, d: 1.8, r: 1 }, 29.6, 6, P, 3, 0), { tone: "hi", crease: "none" }));
  // the memory-card door and the service port
  out.push(L(faceRect(m.x + 24, 26, m.x + m.w - 5, 35, 1.4, PF), { tone: "lo" }));
  out.push(L(faceLine(m.x + 27, 28.6, m.x + m.w - 8, 28.6, PF) + faceLine(m.x + 27, 32, m.x + m.w - 11, 32, PF), { tone: "lo", free: true }));
  out.push(L(faceRect(m.x + 8, 14, m.x + 22, 22, 1.4, PF), { tone: "mid" }));
  out.push(L([0, 1, 2, 3, 4, 5].map((i) => faceLine(m.x + 10.2 + i * 1.9, 14.8, m.x + 10.2 + i * 1.9, 17.4, PF)).join(""), { tone: "lo" }));
  out.push(terminalRow(m, 8, 3));
  return out.join("");
}

function io(m) {
  const out = [body(m)];
  out.push(terminalRow(m, H - 11, 2));
  out.push(labelWindow(m, 48, 58));
  // lamp matrix: two columns, one lamp per channel; every few lit
  const lamps = [];
  for (let r = 0; r < m.rows; r++) {
    for (let c = 0; c < 2; c++) {
      const [cx, cy] = faceDot(m.x + 8 + c * 9, 42 - r * 4.4, PF);
      const lit = (r * 2 + c) % 3 === 0;
      lamps.push(`<circle class="rk-led rk-led--${lit ? "ok" : "dim"}" cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="1.15"/>`);
    }
  }
  out.push(lamps.join(""));
  // plug-in connector block with two rows of wire entries
  const cb = { x: m.x + 2.4, y: PF, w: m.w - 4.8, d: 3, r: 1 };
  out.push(S(k.slabOf(cb, 8, 17, P, 3, 0), { tone: "mid", crease: "faint" }));
  const entries = [];
  const heads = [];
  for (let c = 0; c < 2; c++) {
    for (let r = 0; r < 2; r++) {
      const cx = m.x + 6.5 + c * 8;
      const cz = 11.4 + r * 7;
      entries.push(faceRect(cx - 1.6, cz - 1.5, cx + 1.6, cz + 1.5, 0.5, PF + 3, 2));
      heads.push(faceDot(cx, cz + 3.0, PF + 3));
    }
  }
  out.push(L(entries.join(""), { tone: "lo" }));
  out.push(D(heads, { size: 0.5, tone: "mid" }));
  return out.join("");
}

function net(m) {
  const out = [body(m)];
  out.push(terminalRow(m, H - 11, 3));
  out.push(labelWindow(m, 48, 58));
  // an RJ45 port: a rounded well with a latch notch and eight contacts, and two lamps beside it
  const [x0, x1, z0, z1] = [m.x + 8, m.x + 22, 31, 41];
  out.push(`<path class="rk-well" d="${faceRect(x0, z0, x1, z1, 1.2, PF)}"/>`);
  out.push(L(faceRect(x0, z0, x1, z1, 1.2, PF), { tone: "lo" }));
  out.push(L(faceRect(x0 + 4.2, z1 - 0.1, x1 - 4.2, z1 + 2.2, 0.6, PF), { tone: "lo" }));
  out.push(L([0, 1, 2, 3, 4, 5, 6, 7].map((i) => faceLine(x0 + 2 + i * 1.4, z0 + 1, x0 + 2 + i * 1.4, z0 + 3.4, PF)).join(""), { tone: "lo" }));
  out.push(leds(m, 44.6, 2, [{ tone: "ok", blink: "fast" }, { tone: "ok", blink: "slow" }], 5.4));
  out.push(labelWindow(m, 15, 25));
  out.push(terminalRow(m, 10, 3));
  return out.join("");
}

function module(m) {
  return { psu, cpu, io, net }[m.kind](m);
}

// ---- end clamps and spare terminal blocks ----------------------------------------------------------
function clamp(x) {
  const out = [];
  const c = { x, y: MOD_Y + 2, w: 5, d: MOD_D - 6, r: 1.2 };
  out.push(S(k.slabOf(c, 0, 26, P, 3, 0.4), { tone: "mid" }));
  out.push(S(k.cylinder(x + 2.5, c.y + c.d - 1.4, 1.7, 26, 1, P, 12), { tone: "lo" }));
  out.push(L(k.lineOnTop([x + 1.3, c.y + c.d - 1.4], [x + 3.7, c.y + c.d - 1.4], 27, P), { tone: "mid" }));
  return out.join("");
}

function blocks() {
  const out = [];
  for (let i = 0; i < BLOCK_N; i++) {
    const x = BLOCKS_X + i * (BLOCK_W + 0.6);
    const p = { x, y: MOD_Y + 6, w: BLOCK_W, d: BLOCK_D, r: 1.2 };
    const yf = p.y + p.d;
    const tall = i === 2 ? BLOCK_H + 4 : BLOCK_H;
    out.push(S(k.slabOf(p, 0, tall, P, 3, 0.4), { tone: i === 2 ? "hi" : "mid", crease: "lo" }));
    // screw head on top and the wire entry on the front, both on the part
    out.push(S(k.cylinder(x + BLOCK_W / 2, p.y + 9, 1.9, tall, 0.9, P, 12), { tone: "lo" }));
    out.push(L(k.lineOnTop([x + BLOCK_W / 2 - 1.3, p.y + 9], [x + BLOCK_W / 2 + 1.3, p.y + 9], tall + 0.9, P), { tone: "mid" }));
    out.push(L(faceRect(x + 1.6, 8, x + BLOCK_W - 1.6, 15, 0.6, yf, 2), { tone: "lo" }));
    out.push(L(faceRect(x + 1.6, 21, x + BLOCK_W - 1.6, 28, 0.6, yf, 2), { tone: "lo" }));
    out.push(D([faceDot(x + BLOCK_W / 2, 18, yf)], { size: 0.5, tone: "mid" }));
  }
  return out.join("");
}

// ---- wires ---------------------------------------------------------------------------------------------
/** A smooth arc between two world points, lifted by `lift` at the middle, as screen points. */
function arc(a, b, lift, steps = 14) {
  const pts = [];
  for (let i = 0; i <= steps; i++) {
    const t = i / steps;
    const bulge = Math.sin(Math.PI * t) * lift;
    pts.push(at([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t + bulge]));
  }
  return pts;
}

function wires() {
  const out = [];
  const jump = (m1, i1, n1, m2, i2, n2) => {
    const s1 = (m1.w - 8) / Math.max(1, n1 - 1);
    const s2 = (m2.w - 8) / Math.max(1, n2 - 1);
    const a = [m1.x + 4 + i1 * s1, YF - 11, H];
    const b = [m2.x + 4 + i2 * s2, YF - 11, H];
    return k.wireSvg(at(a), at(b), { via: arc(a, b, 11).slice(1, -1), tone: "mid", size: 1.2 });
  };
  const [psuM, cpuM, diM, doM, aiM, netM] = MODS;
  // the 24 V bus hops module to module along the tops
  if (!MISSING) {
    out.push(jump(psuM, 3, 4, cpuM, 0, 3));
    out.push(jump(cpuM, 2, 3, diM, 0, 2));
  }
  out.push(jump(diM, 1, 2, doM, 0, 2));
  out.push(jump(doM, 1, 2, aiM, 0, 2));
  // network uplink: from the RJ45 port, down and forward to a jack on the bench
  const port = [netM.x + 15, PF, 36];
  const jack = { x: 56, y: 36, w: 12, d: 9, r: 1.6 };
  out.push(S(k.slabOf(jack, 0, 7, P, 3, 0.3), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(jack, 2), 7, P, 3), { tone: "lo" }));
  const to = [jack.x + jack.w / 2, jack.y + 2, 7];
  const pts = [];
  const steps = 22;
  for (let i = 0; i <= steps; i++) {
    const t = i / steps;
    // out of the port along +y, then a gentle S down onto the jack
    const e = t * t * (3 - 2 * t);
    pts.push(at([port[0] + (to[0] - port[0]) * e, port[1] + (to[1] + 6 - port[1]) * Math.min(1, t * 1.1), port[2] + (to[2] - port[2]) * e - Math.sin(Math.PI * t) * 6]));
  }
  out.push(k.wireSvg(pts[0], pts[pts.length - 1], { via: pts.slice(1, -1), tone: "hi", size: 1.3 }));
  return out.join("");
}

// ---- the signal tower ------------------------------------------------------------------------------------
const TOWER = { x: 150, y: 28 };
function tower() {
  const out = [];
  const [cx, cy] = [TOWER.x, TOWER.y];
  out.push(S(k.cylinder(cx, cy, 8.4, 0, 3, P, 28), { tone: "lo" }));
  out.push(S(k.cylinder(cx, cy, 6.6, 3, 4, P, 28), { tone: "mid" }));
  out.push(S(k.cylinder(cx, cy, 2.2, 7, 22, P, 16), { tone: "mid", crease: "none" }));
  const lens = [
    { z: 29, cls: "rk-lens--red", glow: MISSING, glowCls: "rk-glow--red" },
    { z: 41, cls: "rk-lens--amber", glow: false },
    { z: 53, cls: "rk-lens--green", glow: !MISSING },
  ];
  for (const l of lens) {
    out.push(S(k.cylinder(cx, cy, 6.8, l.z, 1.6, P, 28), { tone: "mid", crease: "none" }));
    const lens3 = k.cylinder(cx, cy, 6.2, l.z + 1.6, 9.2, P, 28, 0);
    out.push(S(lens3, { tone: "hi", crease: "faint", className: l.cls }));
    out.push(L(k.sideArc(cx, cy, 6.2, l.z + 4.4, P, 20) + k.sideArc(cx, cy, 6.2, l.z + 7.2, P, 20), { tone: "faint" }));
    if (l.glow) out.push(`<path class="rk-glow${l.glowCls ? ` ${l.glowCls}` : ""}" data-blink="fast" style="--at:0ms" d="${lens3.fill}"/>`);
  }
  out.push(S(k.cylinder(cx, cy, 6.8, 64.8, 1.6, P, 28), { tone: "mid", crease: "none" }));
  out.push(S(k.cylinder(cx, cy, 4.2, 66.4, 2.4, P, 20), { tone: "lo" }));
  return out.join("");
}

/** The CPU, lifted straight out of its slot: footprint ghosted on the rail, two alignment lines. */
function lifted(m) {
  const ghost = L(k.planOutline(plan(m), RAIL_H, P, 4), { tone: "mid", dashed: true });
  const corners = [[m.x + 1, YF - 1], [m.x + m.w - 1, YF - 1]];
  const guides = corners.map(([x, y]) => L(k.rise([x, y], RAIL_H, LIFT, P), { tone: "mid", dashed: true })).join("");
  return `${ghost}${guides}<g transform="${k.translateAlong(P, [0, 0, LIFT])}">${module(m)}</g>`;
}

// ---- assemble ------------------------------------------------------------------------------------------
function ordered() {
  // everything standing on the rail, painted far to near
  const items = [
    ...MODS.map((m) => ({ d: k.depthOf([m.x + m.w / 2, MOD_Y + MOD_D / 2, 0], P), svg: MISSING && m.id === "cpu" ? lifted(m) : module(m) })),
    { d: k.depthOf([-155.5, 0, 0], P), svg: clamp(-158) },
    { d: k.depthOf([77.7, 0, 0], P), svg: clamp(75.2) },
    { d: k.depthOf([BLOCKS_X + 20, 0, 0], P), svg: blocks() },
  ];
  return items.sort((a, b) => a.d - b.d).map((item) => item.svg);
}

const body_ = [bench(), rail(), ...ordered(), wires(), tower()];

const label = MISSING
  ? "The same control rack with its CPU module lifted out of the slot: a ghost outline marks the empty rail, the module hovers above on two dashed alignment lines with its lamps dark, and the signal tower shows a red lamp."
  : "A DIN-rail control rack on a bench plate: a power supply, a CPU with a blinking RUN lamp, two digital I/O cards, an analogue card, a network card and a row of spare terminal blocks, wired together with jumpers and one network cable, beside a signal tower whose green lamp is lit.";
const svg = k.figureSvg({ width: WIDTH, height: HEIGHT, label, body: body_, standalone: "light" }).replace("</style>", `${SITE_TOKENS}</style>`);

mkdirSync(OUT, { recursive: true });
const file = MISSING ? "rack-missing.svg" : "rack.svg";
writeFileSync(join(OUT, file), svg + "\n");
console.log("wrote assets/anatomy/" + file, svg.length, "bytes");
