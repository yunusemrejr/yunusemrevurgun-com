/* Specimen: a three-drawer index-card archive on a bench plate. The middle drawer is pulled out 26 units
 * toward the viewer, so its box shows a row of index cards (three of them with tabs) and the opening it
 * came from. The pulled front is the one lit part. Painted back to front: plinth, carcass, the closed
 * fronts, then the pulled box as one group (floor, back wall, left wall, cards, right wall, front). */
import { makeLib } from "../lib.mjs";

export const meta = {
  label:
    "A three-drawer index-card archive on a bench plate: the middle drawer is pulled out to show a row of index cards, three of them with tabs, beside its label holder and pull.",
  extents: [
    [-46, -40, 0],
    [-46, -40, 70],
    [46, -40, 70],
    [46, 16, 70],
    [-44, 43.5, 28],
    [44, 47.1, 45],
  ],
};

const CAR = { x: -46, y: -40, w: 92, d: 56, r: 3 }; // carcass, z 6..70
const CAR_Z0 = 6;
const CAR_Z1 = 70;
const FRONT_Y = CAR.y + CAR.d; // 16, the carcass front face
const SLAB = 1.5; // drawer front thickness
const FACE = FRONT_Y + SLAB; // 17.5, the face of a closed front
const PULL = 26; // the middle drawer's travel toward +y
const FRONT = { x: -44, w: 88 };
const DRAWERS = [
  { z0: 9, z1: 26 },
  { z0: 28, z1: 45 },
  { z0: 47, z1: 64 },
];
const BOX = { x0: -38, x1: 38, y0: -10, y1: FRONT_Y, floorZ: 28.5, floorH: 1.5, floorTop: 30, wallTop: 44 }; // rest position
const CARDS = Array.from({ length: 15 }, (_, i) => {
  const tall = i === 2 || i === 6 || i === 10;
  const tabs = { 2: [-5, 0], 6: [3, 8], 10: [8, 13] };
  return { x: -32 + i * 4.6, h: tall ? 13.5 : 11, tab: tabs[i] };
});
const CARD = { y0: -6, y1: 14, t: 1.2 };

export function parts(lib) {
  const { k, P, S, L, D, at } = lib;
  const out = [];

  // plinth, a little smaller than the carcass so the carcass overhangs it
  out.push(S(k.slabOf({ x: -44, y: -38, w: 88, d: 52, r: 2 }, 0, CAR_Z0, P, 4, 0.6), { tone: "lo", crease: "lo" }));

  // carcass: two seams wrap its visible corners (bottom band and top band), a recessed top inlay and screws
  const seams = L(k.sideSeam(CAR, 8, P, 6), { tone: "faint" }) + L(k.sideSeam(CAR, 66, P, 6), { tone: "faint" });
  out.push(S(k.slabOf(CAR, CAR_Z0, CAR_Z1 - CAR_Z0, P, 6, 1.2), { tone: "mid", inner: seams }));
  // the +x side: a closed panel outline with four screws
  const SIDE_X = CAR.x + CAR.w;
  out.push(L(lib.sideRect(-34, 14, 10, 62, 2, SIDE_X), { tone: "faint" }));
  const sideScrews = [
    [-31, 17],
    [7, 17],
    [-31, 59],
    [7, 59],
  ];
  out.push(L(sideScrews.map(([y, z]) => k.sideRing([SIDE_X, y, z], 0.9, "x", P, 12)).join(""), { tone: "lo" }));
  out.push(D(sideScrews.map(([y, z]) => at([SIDE_X, y, z])), { size: 0.4, tone: "mid" }));
  // the top: a raised pad inside a recessed inlay outline, a label plate on the front strip
  out.push(L(k.planOutline(k.insetPlan(CAR, 7), CAR_Z1, P, 6), { tone: "faint" }));
  out.push(S(k.slabOf(k.insetPlan(CAR, 9), CAR_Z1, 0.8, P, 6, 0.4), { tone: "mid", crease: "none" }));
  out.push(L(k.planOutline({ x: -22, y: -24, w: 44, d: 2.4, r: 1.2 }, CAR_Z1 + 0.8, P, 6), { tone: "lo" }));
  const padScrews = [
    [-34, -28],
    [34, -28],
    [-34, 4],
    [34, 4],
  ];
  out.push(L(padScrews.map(([x, y]) => k.ring(x, y, 1, CAR_Z1 + 0.8, P, 10)).join(""), { tone: "lo" }));
  out.push(D(padScrews.map(([x, y]) => at([x, y, CAR_Z1 + 0.8])), { size: 0.35, tone: "mid" }));
  const label = { x: -36, y: 9.5, w: 22, d: 4.5, r: 0.8 };
  out.push(S(k.slabOf(label, CAR_Z1, 0.6, P, 3), { tone: "lo", crease: "none" }));
  out.push(L([0, 1, 2].map((i) => k.lineOnTop([label.x + 3, label.y + 1.4 + i * 1.4], [label.x + 3 + [14, 9, 16][i], label.y + 1.4 + i * 1.4], CAR_Z1 + 0.6, P)).join(""), { tone: "lo", free: true }));
  const topScrews = k.corners(CAR, 3);
  out.push(L(topScrews.map(([x, y]) => k.ring(x, y, 1.6, CAR_Z1, P, 14)).join(""), { tone: "lo" }));
  out.push(D(topScrews.map(([x, y]) => at([x, y, CAR_Z1])), { size: 0.5, tone: "mid" }));
  // the carcass front: a closed opening outline round the middle drawer, and two screws in the top band
  out.push(L(lib.faceRect(-39, DRAWERS[1].z0 - 0.6, 39, DRAWERS[1].z1 + 0.6, 1, FRONT_Y), { tone: "hi" }));
  for (const x of [-38, 38]) {
    out.push(L(lib.faceCircle(x, 68, 1, FRONT_Y, 14), { tone: "lo" }));
    out.push(D([lib.facePoint(x, 68, FRONT_Y)], { size: 0.4, tone: "mid" }));
  }

  // the two closed drawers, painted before the pulled box (the box's floor overhangs the lower front)
  out.push(front(lib, DRAWERS[0].z0, DRAWERS[0].z1));
  out.push(front(lib, DRAWERS[2].z0, DRAWERS[2].z1));

  // the pulled middle drawer: one group, moved 26 units toward +y
  const inner = [];
  inner.push(S(k.slabOf({ x: BOX.x0, y: BOX.y0, w: BOX.x1 - BOX.x0, d: BOX.y1 - BOX.y0, r: 0.8 }, BOX.floorZ, BOX.floorH, P, 3, 0.4), { tone: "mid" }));
  inner.push(S(k.slabOf({ x: BOX.x0, y: BOX.y0, w: BOX.x1 - BOX.x0, d: 1.5, r: 0.6 }, BOX.floorZ, BOX.wallTop - BOX.floorZ, P, 3, 0.4), { tone: "mid" }));
  inner.push(S(k.slabOf({ x: BOX.x0, y: BOX.y0 + 1.5, w: 1.5, d: BOX.y1 - BOX.y0 - 1.5, r: 0.4 }, BOX.floorTop, BOX.wallTop - BOX.floorTop, P, 2, 0.3), { tone: "mid" }));
  const cards = [...CARDS].sort((a, b) => lib.depth(a.x, 4, BOX.floorTop) - lib.depth(b.x, 4, BOX.floorTop));
  for (const c of cards) inner.push(card(lib, c));
  inner.push(S(k.slabOf({ x: BOX.x1 - 1.5, y: BOX.y0 + 1.5, w: 1.5, d: BOX.y1 - BOX.y0 - 1.5, r: 0.4 }, BOX.floorTop, BOX.wallTop - BOX.floorTop, P, 2, 0.3), { tone: "mid" }));
  // rivets on the walls that show above the cards: back wall inner face, left wall inner face, right wall outer face
  inner.push(D([at([-34.5, BOX.y0 + 1.5, 42.5]), at([34.5, BOX.y0 + 1.5, 42.5])], { size: 0.4, tone: "mid" }));
  inner.push(D([at([BOX.x0 + 1.5, -4, 42.5]), at([BOX.x0 + 1.5, 9, 42.5])], { size: 0.4, tone: "mid" }));
  inner.push(L(lib.sideRect(-4, 32, 12, 42, 1, BOX.x1), { tone: "faint" }));
  inner.push(D([at([BOX.x1, -4, 37]), at([BOX.x1, 12, 37])], { size: 0.4, tone: "mid" }));
  inner.push(front(lib, DRAWERS[1].z0, DRAWERS[1].z1, { lit: true }));
  out.push(k.groupSvg(inner.join(""), { class: "archive-pulled", transform: k.translateAlong(P, [0, PULL, 0]) }));

  return out;
}

/** A closed drawer front on the carcass face: a slab, a label holder, and a pull on two posts. */
function front(lib, z0, z1, { lit = false } = {}) {
  const { k, P, S, L, D, faceRect, facePoint, faceCircle } = lib;
  const zc = (z0 + z1) / 2;
  const out = [];
  out.push(S(k.slabOf({ x: FRONT.x, y: FRONT_Y, w: FRONT.w, d: SLAB, r: 0.8 }, z0, z1 - z0, P, 3, 0.4), { tone: "hi", lit }));
  // label holder: a closed inset frame, a card slab proud 0.6, two rivets on the card
  out.push(L(faceRect(-37, zc - 4.5, -18, zc + 4.5, 1.2, FACE), { tone: "lo" }));
  out.push(S(k.slabOf({ x: -35.5, y: FACE, w: 16, d: 0.6, r: 0.3 }, zc - 3.2, 6.4, P, 2, 0), { tone: "mid" }));
  out.push(D([facePoint(-33.4, zc, FACE + 0.6), facePoint(-21.6, zc, FACE + 0.6)], { size: 0.4, tone: "mid" }));
  // pull on the right: a bar standing two units off the face, on two 2x2 posts (the right side stays
  // in view when the pulled drawer covers the left of the drawer below it)
  const zb = zc + 1.2;
  const zh = 2.4;
  out.push(S(k.slabOf({ x: 26, y: FACE, w: 2, d: 2, r: 0.4 }, zb, zh, P, 2, 0), { tone: "mid" }));
  out.push(S(k.slabOf({ x: 38.5, y: FACE, w: 2, d: 2, r: 0.4 }, zb, zh, P, 2, 0), { tone: "mid" }));
  out.push(S(k.slabOf({ x: 25, y: FACE + 2, w: 18, d: 1.5, r: 0.6 }, zb, zh, P, 3, 0.5), { tone: "hi" }));
  return out.join("");
}

/** One index card standing in the box, with its tab on top when it has one. */
function card(lib, c) {
  const { k, P, S } = lib;
  const out = [];
  out.push(S(k.slabOf({ x: c.x, y: CARD.y0, w: CARD.t, d: CARD.y1 - CARD.y0, r: 0.2 }, BOX.floorTop, c.h, P, 2, 0), { tone: "mid" }));
  if (c.tab) out.push(S(k.slabOf({ x: c.x, y: c.tab[0], w: CARD.t, d: c.tab[1] - c.tab[0], r: 0.2 }, BOX.floorTop + c.h, 2, P, 2, 0), { tone: "mid" }));
  return out.join("");
}
