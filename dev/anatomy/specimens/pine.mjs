/* Specimen: a young pine in a soil block, measured by a vernier height gauge.
 * Nature meets measurement: six whorls of octagonal tiers hang on a thin trunk, and the gauge's
 * scriber (the one lit part) reaches over the crown to touch the tip of the leader. The crown is
 * schematic, not a measured tree; the gauge proportions are invented to read as a real vernier. */
import { makeLib } from "../lib.mjs";

const TREE = [-10, -6]; // trunk axis on the bench (x, y)
const SOIL = { x: -32, y: -28, w: 44, d: 44, r: 6 };
const SOIL_H = 10;
const TRUNK_R = 2.6;
const TIER_Z0 = 22; // bottom of the lowest whorl
const TIER_H = 7;
const PITCH = 13; // whorl bottom to next whorl bottom: 6 units of bare trunk between tiers
const TIER_R = [28, 24, 19.5, 15, 11.5, 8];
const LEADER_R = 1.3;
const TIP_Z = 118; // top of the leader

const BASE = { x: 30, y: -14, w: 26, d: 20, r: 2 };
const BEAM = { x: 40, y: -6, w: 5, d: 4, r: 0.8 };
const BEAM_TOP = 130;
const SLIDER = { x: 36, y: -9, w: 13, d: 10, r: 2 };
const SLIDER_Z = 112;
const SLIDER_H = 12;
const ARM_Z = 116.6; // scriber arm: 1.4 thick, its top at TIP_Z
const TIP_X = TREE[0] + LEADER_R + 0.2; // a hair clear of the leader's side

export const meta = {
  label:
    "A young pine sapling in a soil block on a bench plate: six tiers of branches on a thin trunk, with a vernier height gauge beside it whose scriber reaches over the crown and nearly touches the tip of the leader.",
  extents: [
    [TREE[0] - TIER_R[0], TREE[1], TIER_Z0 + TIER_H],
    [TREE[0] + TIER_R[0], TREE[1], TIER_Z0 + TIER_H],
    [TREE[0], TREE[1] - TIER_R[0], TIER_Z0 + TIER_H],
    [TREE[0], TREE[1] + TIER_R[0], TIER_Z0 + TIER_H],
    [TREE[0], TREE[1], TIP_Z],
    [BEAM.x + BEAM.w / 2, BEAM.y + BEAM.d, BEAM_TOP],
  ],
};

export function parts(lib) {
  const { k, P, at, S, L, D } = lib;
  const out = [];
  const [tx, ty] = TREE;
  const tierZ = (i) => TIER_Z0 + i * PITCH;
  const trunk = (z0, z1) => S(k.cylinder(tx, ty, TRUNK_R, z0, z1 - z0, P, 16), { tone: "mid", crease: "none" });

  // soil block with a faint inset outline on top
  out.push(S(k.slabOf(SOIL, 0, SOIL_H, P, 8, 1.6), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(SOIL, 4), SOIL_H, P, 8), { tone: "faint" }));

  // bare trunk below the lowest whorl
  out.push(trunk(SOIL_H, tierZ(0)));

  // whorls, bottom to top; the bare trunk between two whorls is drawn after the lower one and
  // before the upper one, so the trunk standing on a tier's top face covers that face behind it
  TIER_R.forEach((r, i) => {
    const z = tierZ(i);
    out.push(S(k.cylinder(tx, ty, r, z, TIER_H, P, 8, 0.5), { tone: "hi", className: "pn-needles" }));
    if (i < TIER_R.length - 1) out.push(trunk(z + TIER_H, tierZ(i + 1)));
  });

  // leader: the new shoot above the top whorl, ending at TIP_Z
  const topWhorl = tierZ(TIER_R.length - 1) + TIER_H;
  out.push(S(k.cylinder(tx, ty, LEADER_R, topWhorl, TIP_Z - topWhorl, P, 8, 0.3), { tone: "hi", className: "pn-needles" }));

  // vernier height gauge: heavy base, beam, tick scale on the +y face
  out.push(S(k.slabOf(BASE, 0, 6, P, 8, 1), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(BASE, 3), 6, P, 6), { tone: "faint" }));
  out.push(D([at([BASE.x + 3, BASE.y + 3, 6]), at([BASE.x + BASE.w - 3, BASE.y + 3, 6]), at([BASE.x + 3, BASE.y + BASE.d - 3, 6])], { size: 0.45 }));

  out.push(S(k.slabOf(BEAM, 6, BEAM_TOP - 6, P, 6, 0.6), { tone: "mid" }));

  // ticks hang from the beam's left edge on its +y face: every 3 units, longer every 5th (15 units)
  const tickY = BEAM.y + BEAM.d;
  const minor = [];
  const major = [];
  for (let n = 2; n * 3 <= BEAM_TOP - 4; n++) {
    const z = n * 3;
    const big = n % 5 === 0;
    (big ? major : minor).push(lib.faceLine(BEAM.x, z, BEAM.x + (big ? 2.6 : 1.4), z, tickY));
  }
  out.push(L(minor.join(""), { tone: "lo" }), L(major.join(""), { tone: "mid" }));

  // slider block clamping the beam at the tree's tip height, with an inset line on top
  out.push(S(k.slabOf(SLIDER, SLIDER_Z, SLIDER_H, P, 8, 1), { tone: "mid" }));
  out.push(L(k.planOutline(k.insetPlan(SLIDER, 2.5), SLIDER_Z + SLIDER_H, P, 6), { tone: "faint" }));

  // scriber arm from the slider's -x face to just short of the leader: the one lit part
  const armLen = SLIDER.x - TIP_X;
  out.push(S(k.slabOf({ x: TIP_X, y: ty - 0.8, w: armLen, d: 1.6, r: 0.6 }, ARM_Z, TIP_Z - ARM_Z, P, 3, 0.3), { tone: "hi", lit: true }));

  return out;
}
