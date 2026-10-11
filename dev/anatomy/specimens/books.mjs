/* Specimen: a reading shelf for a journal. A board holds four upright books (the leftmost one pulled
 * forward, with a bookmark ribbon hanging down its spine), an L-shaped bookend at the left, and at the
 * right a pen cup with three pencils beside a notebook on a spiral binding. The pulled book is the one
 * lit part; everything else is quiet. Paint order: shelf, bookend, books left to right, pen cup, pencils
 * above the rim, notebook. */
import { makeLib } from "../lib.mjs";

export const meta = {
  label:
    "A reading shelf for a journal: a board on a bench plate holds four upright books, one pulled forward with a red bookmark ribbon hanging down its spine, beside an L-shaped bookend, a pen cup with three pencils and a notebook on a spiral binding.",
  extents: [
    [-68, -34, 0],
    [-68, -34, 51],
    [-30, -2, 57],
    [8, -12, 49],
    [65, 8, 0],
    [-68, 10, 0],
  ],
};

const SHELF_TOP = 5;
const CUP = { x: 8, y: -12, r: 9, rim: 27 };
const PENCILS = [
  { x: CUP.x - 3.4, y: CUP.y + 1.2, top: 45 },
  { x: CUP.x + 1.6, y: CUP.y - 2.4, top: 41 },
  { x: CUP.x + 3.4, y: CUP.y + 2.6, top: 49 },
];
const BOOKS = [
  { x: -44, t: 9, h: 46, pulled: true },
  { x: -34, t: 7, h: 52 },
  { x: -26, t: 11, h: 40 },
  { x: -14, t: 8, h: 48 },
];
const PULL = [0, 10, 0];

export function parts(lib) {
  const { k, P, at, S, L, D, depth, faceLine, sideRect } = lib;
  const out = [];

  // ---- the shelf board: a groove wraps its visible front and end, four screw heads at the ends
  const SH = { x: -68, y: -34, w: 136, d: 44, r: 2 };
  out.push(S(k.slabOf(SH, 0, SHELF_TOP, P, 6, 1), { tone: "mid", inner: L(k.sideSeam(SH, 2.5, P, 6), { tone: "faint" }) }));
  out.push(L(k.planOutline(k.insetPlan(SH, 2.6), SHELF_TOP, P, 6), { tone: "faint" }));
  for (const [x, y] of [
    [-62.5, 4.5],
    [-55, 4.5],
    [65, -28],
    [65, 4.5],
  ]) {
    out.push(S(k.cylinder(x, y, 1.1, SHELF_TOP, 0.8, P, 12), { tone: "mid" }));
    out.push(L(k.ring(x, y, 1.9, SHELF_TOP, P, 14), { tone: "lo" }));
    out.push(D([at([x, y, SHELF_TOP + 0.8])], { size: 0.4 }));
  }

  // ---- the bookend: a foot on the shelf, an upright at its far end with a panel and two screws
  const FOOT = { x: -68, y: -34, w: 22, d: 34, r: 1 };
  out.push(S(k.slabOf(FOOT, SHELF_TOP, 4, P, 4, 0.6), { tone: "lo", inner: L(k.planOutline(k.insetPlan(FOOT, 3), 9, P, 3), { tone: "faint" }) }));
  const UPRIGHT = { x: -68, y: -34, w: 4, d: 34, r: 0.8 };
  out.push(S(k.slabOf(UPRIGHT, 9, 41, P, 3, 0.5), { tone: "mid", inner: L(k.sideSeam(UPRIGHT, 16, P, 3), { tone: "faint" }) }));
  out.push(L(sideRect(-29, 22, -5, 46, 1.2, -64), { tone: "faint" }));
  out.push(D([at([-64, -25, 42]), at([-64, -9, 42])], { size: 0.45 }));
  for (const y of [-30, -10]) out.push(S(k.slabOf({ x: -64, y, w: 2.6, d: 4, r: 0.6 }, 9, 4, P, 3, 0.4), { tone: "mid" }));

  // ---- a book: spine on the +y face, page block on top, bands and a label plate on the spine
  const book = (b) => {
    const zb = SHELF_TOP;
    const zt = zb + b.h;
    const plan = { x: b.x, y: -30, w: b.t, d: 28, r: 0.6 };
    const front = plan.y + plan.d;
    const zc = (zb + zt) / 2;
    const body = S(k.slabOf(plan, zb, b.h, P, 3, 0.5), {
      tone: "hi",
      lit: !!b.pulled,
      inner:
        L(k.sideSeam(plan, zt - 1.6, P, 3), { tone: "mid" }) +
        L(k.sideSeam(plan, zb + 1.4, P, 3), { tone: "lo" }) +
        L(k.planOutline(k.insetPlan(plan, 2), zt, P, 2), { tone: "lo" }),
    });
    // the bands stand 0.6 proud of the spine, inset so their corners stay inside its silhouette
    const band = (z) => S(k.slabOf({ x: b.x + 0.5, y: front, w: b.t - 1, d: 0.6, r: 0 }, z, 1.6, P, 2, 0.3), { tone: "mid" });
    // the label plate: a thin raised slab whose outline is the frame, with two printed rule lines
    const x0 = b.x + 1.1;
    const x1 = b.x + b.t - 2.7;
    const label = S(k.slabOf({ x: x0, y: front, w: x1 - x0, d: 0.4, r: 0.2 }, zc - 4.5, 9, P, 2), { tone: "lo" });
    const rules = L(faceLine(x0 + 0.9, zc + 2, x1 - 0.9, zc + 2, front + 0.4) + faceLine(x0 + 0.9, zc - 1.5, x1 - 1.6, zc - 1.5, front + 0.4), { tone: "lo", free: true });
    const pieces = [body, band(zb + 3.2), band(zt - 6.6), label, rules];
    if (b.pulled) {
      // a bookmark ribbon: a strip lying on the page block, draped over the front edge and down the spine
      const rx0 = b.x + b.t - 2;
      const rx1 = b.x + b.t - 0.6;
      pieces.push(S(k.slabOf({ x: rx0, y: front + 0.6, w: rx1 - rx0, d: 0.8, r: 0.2 }, zb + 12, zt - zb - 12, P, 2), { tone: "hi" }));
      pieces.push(S(k.slabOf({ x: rx0, y: front - 5.5, w: rx1 - rx0, d: 7, r: 0.2 }, zt, 0.5, P, 2), { tone: "hi" }));
      return `<g transform="${k.translateAlong(P, PULL)}">${pieces.join("")}</g>`;
    }
    return pieces.join("");
  };
  for (const b of BOOKS) out.push(book(b));

  // ---- the pen cup: a foot, a body with a rim and a dark mouth, and three pencils standing in it
  out.push(S(k.cylinder(CUP.x, CUP.y, CUP.r + 0.6, SHELF_TOP, 1.6, P, 40), { tone: "lo" }));
  out.push(S(k.cylinder(CUP.x, CUP.y, CUP.r, SHELF_TOP + 1.6, CUP.rim - SHELF_TOP - 1.6, P, 40), { tone: "mid" }));
  out.push(S(k.cylinder(CUP.x, CUP.y, CUP.r + 0.5, CUP.rim - 2.4, 2.4, P, 40), { tone: "mid" }));
  out.push(L(k.ring(CUP.x, CUP.y, CUP.r - 0.8, CUP.rim, P, 40), { tone: "lo" }));
  out.push(`<path class="iso-shade" data-shade="0" d="${k.onTop(k.circleRing(CUP.x, CUP.y, CUP.r - 1.8, 40), CUP.rim, P, true)}"/>`);
  out.push(L(k.ring(CUP.x, CUP.y, CUP.r - 1.8, CUP.rim, P, 40), { tone: "faint" }));

  // The cup is solid, so each pencil shows only above the rim; the part below it is inside the cup.
  const pencils = PENCILS.map((p) => ({ ...p, d: depth(p.x, p.y, CUP.rim) })).sort((a, b) => a.d - b.d);
  for (const p of pencils) {
    out.push(S(k.cylinder(p.x, p.y, 1.6, CUP.rim, p.top - CUP.rim, P, 16), { tone: "mid" }));
    out.push(L(k.sideArc(p.x, p.y, 1.6, p.top - 4.4, P, 8), { tone: "faint" }));
    out.push(S(k.cylinder(p.x, p.y, 0.8, p.top, 2.2, P, 10), { tone: "hi" }));
  }

  // ---- the notebook: two stacked pads (the upper one offset by 2), a cover label and a spiral binding
  const PAD_LOW = { x: 26, y: -18, w: 36, d: 26, r: 0.8 };
  const PAD_HIGH = { x: 24, y: -18, w: 36, d: 26, r: 0.8 };
  out.push(S(k.slabOf(PAD_LOW, SHELF_TOP, 2.4, P, 4, 0.5), { tone: "hi" }));
  const cover = { x: 30, y: -12, w: 12, d: 6, r: 0.6 };
  out.push(
    S(k.slabOf(PAD_HIGH, SHELF_TOP + 2.4, 2.4, P, 4, 0.5), {
      tone: "hi",
      inner:
        L(k.planOutline(k.insetPlan(PAD_HIGH, 2.2), SHELF_TOP + 4.8, P, 3), { tone: "faint" }) +
        L(k.planOutline(cover, SHELF_TOP + 4.8, P, 2), { tone: "lo" }) +
        L(k.lineOnTop([31.6, -9.6], [38.6, -9.6], SHELF_TOP + 4.8, P) + k.lineOnTop([31.6, -7.6], [36, -7.6], SHELF_TOP + 4.8, P), { tone: "lo", free: true }),
    }),
  );
  for (const x of [34, 48]) out.push(S(k.slabOf({ x, y: PAD_HIGH.y + PAD_HIGH.d, w: 4, d: 1.2, r: 0.3 }, SHELF_TOP + 3.2, 1.6, P, 2), { tone: "mid" }));
  for (const y of [-14, -8.5, -3, 2.5]) out.push(L(k.sideRing([24, y, SHELF_TOP + 2.4], 2.2, "y", P, 20), { tone: "mid" }));

  return out;
}
