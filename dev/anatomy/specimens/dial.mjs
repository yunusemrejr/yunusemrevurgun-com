/* Specimen: a dial indicator on a magnetic stand, its plunger resting on a gauge block.
 * The reference specimen for the set (scaled down from the kit's own dial-indicator example): the
 * needle is the one lit part, everything else is quiet. Default figure for pages without their own. */
import { makeLib } from "../lib.mjs";

export const meta = {
  label:
    "A dial indicator on a magnetic stand: a post and arm hold a round dial whose plunger rests on a gauge block standing on a bench plate with a ruler along its front edge.",
  extents: [
    [-62, -24, 0],
    [-62, 6, 0],
    [-52, -9, 96],
    [48, -9, 80],
  ],
};

export function parts(lib) {
  const { k, P, at, S, L, D } = lib;
  const out = [];

  // magnetic base block with its on/off knob
  const MAG = { x: -62, y: -26, w: 36, d: 32, r: 3 };
  out.push(S(k.slabOf(MAG, 0, 12, P, 5, 0.6), { tone: "mid" }));
  out.push(S(k.slabOf(k.insetPlan(MAG, 0.8), 12, 6, P, 5, 0.6), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(MAG, 3), 18, P, 4), { tone: "faint" }));
  out.push(D([at([MAG.x + 5, MAG.y + 5, 18]), at([MAG.x + 5, MAG.y + MAG.d - 5, 18]), at([MAG.x + MAG.w - 5, MAG.y + 5, 18])], { size: 0.45 }));
  const knob = [MAG.x + 26, MAG.y + 23];
  out.push(S(k.cylinder(knob[0], knob[1], 5.4, 18, 3.4, P, 28), { tone: "mid" }));
  out.push(L(k.knurl(knob[0], knob[1], 5.4, 18, 21.4, 32, P), { tone: "lo" }));
  out.push(L(k.lineOnTop([knob[0] - 2.6, knob[1]], [knob[0] + 4.2, knob[1]], 21.4, P), { tone: "hi" }));

  // post, collar, clamp with its thumb screw
  const POST = [-52, -10];
  out.push(S(k.cylinder(POST[0], POST[1], 4.6, 18, 3.6, P, 24), { tone: "mid" }));
  out.push(S(k.cylinder(POST[0], POST[1], 3, 21.6, 70, P, 24), { tone: "mid", crease: "none" }));
  out.push(L([44, 60].map((z) => k.sideArc(POST[0], POST[1], 3, z, P, 14)).join(""), { tone: "faint" }));
  const clamp = { x: POST[0] - 6.5, y: POST[1] - 6.5, w: 13, d: 13, r: 2.4 };
  out.push(S(k.slabOf(clamp, 78, 11, P, 4, 0.5), { tone: "mid" }));
  out.push(S(k.cylinder(POST[0], POST[1], 3, 89, 6, P, 24), { tone: "mid", crease: "none" }));
  out.push(S(k.cylinder(POST[0], POST[1], 3.6, 95, 2, P, 24), { tone: "mid" }));
  const thumb = [clamp.x + 6.5, clamp.y + clamp.d + 2.6];
  out.push(S(k.slabOf({ x: thumb[0] - 1.3, y: clamp.y + clamp.d, w: 2.6, d: 2.8, r: 0.8 }, 82, 4, P, 2), { tone: "mid", crease: "none" }));
  out.push(S(k.cylinder(thumb[0], thumb[1] + 1.4, 2.4, 81.4, 5.6, P, 18), { tone: "mid" }));
  out.push(L(k.knurl(thumb[0], thumb[1] + 1.4, 2.4, 81.4, 87, 16, P), { tone: "lo" }));

  // arm out to the dial
  const DIAL = [34, -10];
  const arm = { x: clamp.x + clamp.w - 1, y: DIAL[1] - 3, w: DIAL[0] - 15 - (clamp.x + clamp.w) + 2, d: 6, r: 2 };
  out.push(S(k.slabOf(arm, 78, 4.4, P, 4, 0.4), { tone: "mid" }));
  out.push(D([at([arm.x + 3.6, DIAL[1], 82.4]), at([arm.x + arm.w - 4, DIAL[1], 82.4])], { size: 0.42 }));

  // the gauge block the plunger rests on, with a label plate
  const BLOCK = { x: DIAL[0] - 10, y: DIAL[1] - 10, w: 20, d: 20, r: 1.4 };
  const BLOCK_H = 16;
  out.push(S(k.slabOf(BLOCK, 0, BLOCK_H, P, 3, 0.5), { tone: "hi", crease: "lo" }));
  out.push(L(k.planOutline(k.insetPlan(BLOCK, 2), BLOCK_H, P, 3), { tone: "faint" }));
  const plate = { x: BLOCK.x + 3, y: BLOCK.y + BLOCK.d, w: 14, d: 0.1, r: 0 };
  out.push(L(k.segment([plate.x, BLOCK.y + BLOCK.d, 5], [plate.x + 14, BLOCK.y + BLOCK.d, 5], P) + k.segment([plate.x, BLOCK.y + BLOCK.d, 9], [plate.x + 9, BLOCK.y + BLOCK.d, 9], P), { tone: "lo", free: true }));

  // plunger: tip on the block, rod up into the dial
  const TOP = 66;
  out.push(S(k.cylinder(DIAL[0], DIAL[1], 2.4, BLOCK_H, 2.2, P, 18), { tone: "hi", lit: true }));
  out.push(S(k.cylinder(DIAL[0], DIAL[1], 1.3, BLOCK_H + 2.2, TOP - BLOCK_H - 2.2, P, 14), { tone: "hi", crease: "none", lit: true }));

  // dial: puck, knurled bezel, ring, ticks, crown, needle
  const R = 15;
  out.push(S(k.cylinder(DIAL[0], DIAL[1], 2.6, 74, 8, P, 18), { tone: "mid", crease: "none" }));
  out.push(S(k.slabOf({ x: DIAL[0] - R - 5, y: DIAL[1] - 3.2, w: 7, d: 6.4, r: 1.4 }, 76, 4, P, 3, 0.3), { tone: "mid" }));
  out.push(S(k.cylinder(DIAL[0], DIAL[1], R, TOP, 9, P, 56, 0.7), { tone: "hi", crease: "lo" }));
  out.push(L(k.knurl(DIAL[0], DIAL[1], R, TOP + 6, TOP + 9, 70, P, 0.3), { tone: "lo" }));
  out.push(L(k.sideArc(DIAL[0], DIAL[1], R, TOP + 6, P, 32), { tone: "faint" }));
  out.push(L(k.ring(DIAL[0], DIAL[1], R - 2, TOP + 9, P, 56), { tone: "lo" }));
  const ticks = k.radialTicks(DIAL[0], DIAL[1], R - 2.6, 50, 5, TOP + 9, [1.2, 2.6], P);
  out.push(L(ticks.minor, { tone: "lo" }), L(ticks.major, { tone: "mid" }));
  out.push(S(k.slabOf({ x: DIAL[0] + R - 1, y: DIAL[1] - 2, w: 4.4, d: 4, r: 1 }, 76, 4.4, P, 3), { tone: "mid" }));
  const angle = 0.62 * Math.PI * 2;
  const tip = [DIAL[0] + Math.sin(angle) * (R - 3.4), DIAL[1] - Math.cos(angle) * (R - 3.4)];
  const tail = [DIAL[0] - Math.sin(angle) * 3, DIAL[1] + Math.cos(angle) * 3];
  out.push(`<path class="iso-line" data-tone="lit" data-free="start" style="stroke-width:1.1" d="${k.lineOnTop(tail, tip, TOP + 9.2, P)}"/>`);
  out.push(L(k.ring(DIAL[0], DIAL[1], 1.5, TOP + 9, P, 14), { tone: "mid" }));
  return out;
}
