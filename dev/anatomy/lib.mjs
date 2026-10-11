/* Shared drawing helpers for the small "specimen" figures (dev/anatomy/specimens/*.mjs).
 *
 * A specimen is one small machine standing on a bench plate, drawn with the Anatomy kit, shown in the
 * sky band at the top of an inner page. They share a camera (azimuth 50, elevation 28), a canvas and a
 * base (plate on four feet, ruler strip along the front edge, corner screws), so a set of them reads as
 * one family: the instruments on one bench. `makeLib(P)` binds the helpers to the figure's projection.
 *
 * Conventions (from the kit, see ~/.claude/skills/anatomy/references/craft.md):
 *   - x and y are the floor, z is up, one unit is about one pixel of the finished figure
 *   - the camera looks from +x +y: the +y faces and the tops are the large visible faces, +x faces are
 *     narrow. Detail goes on +y faces and tops.
 *   - paint back to front: base, then parts sorted by depthOf(centre), parts that wrap others split
 *   - tones: base `lo`, structure `mid`, the subject `hi`, one `lit` part (red edge) at most
 *   - every line ends on something: an edge, another line, a dot or a closed outline
 */
import * as k from "./kit/iso-kit.mjs";

export const CANVAS = { width: 320, height: 190 };
export const CAMERA = { azimuth: 50, elevation: 28 };

/** The bench every specimen stands on. Top of the plate is z = 0. */
export const PLATE = { x: -84, y: -54, w: 168, d: 108, r: 8 };
export const PLATE_H = 8;
export const FOOT_H = 4;

export function makeLib(P) {
  const at = (point) => k.iso(point, P);
  const S = (paths, style) => k.solidSvg(paths, style);
  const L = (d, style) => k.lineSvg(d, style);
  const D = (points, style) => k.dotsSvg(points, style);
  const byDepth = (points) => [...points].sort((a, b) => k.depthOf([a[0], a[1], 0], P) - k.depthOf([b[0], b[1], 0], P));
  const depth = (x, y, z = 0) => k.depthOf([x, y, z], P);

  /** A rounded rectangle on the vertical +y face at depth y, from face coordinates (x, z). Closed path. */
  const faceRect = (x0, z0, x1, z1, r, y, steps = 4) =>
    k.pathOf(k.roundedPlan({ x: x0, y: z0, w: x1 - x0, d: z1 - z0, r }, steps).map(([u, z]) => at([u, y, z])), true);
  /** A straight line on the +y face. */
  const faceLine = (x0, z0, x1, z1, y) => k.pathOf([at([x0, y, z0]), at([x1, y, z1])]);
  /** A screen point on the +y face. */
  const facePoint = (x, z, y) => at([x, y, z]);
  /** A circle on the +y face. */
  const faceCircle = (x, z, r, y, steps = 18) => k.sideRing([x, y, z], r, "y", P, steps);
  /** The same three, on the +x face (x fixed, running along y). */
  const sideRect = (y0, z0, y1, z1, r, x, steps = 4) =>
    k.pathOf(k.roundedPlan({ x: y0, y: z0, w: y1 - y0, d: z1 - z0, r }, steps).map(([u, z]) => at([x, u, z])), true);
  const sideLine = (y0, z0, y1, z1, x) => k.pathOf([at([x, y0, z0]), at([x, y1, z1])]);

  /** A smooth arc between two world points, lifted by `lift` at the middle, as screen points. */
  const arc = (a, b, lift, steps = 14) => {
    const pts = [];
    for (let i = 0; i <= steps; i++) {
      const t = i / steps;
      pts.push(at([a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t + Math.sin(Math.PI * t) * lift]));
    }
    return pts;
  };

  /** The standard base: halo, feet, plate, groove, corner screws, and a ruler strip on the front edge. */
  const base = () => {
    const out = [`<path class="iso-halo" d="${k.haloOf(PLATE, -PLATE_H - FOOT_H, PLATE_H, P)}"/>`];
    for (const [x, y] of byDepth(k.corners(PLATE, 18))) {
      out.push(S(k.cylinder(x, y, 6, -PLATE_H - FOOT_H, 1.4, P, 24), { tone: "lo" }));
      out.push(S(k.cylinder(x, y, 4.6, -PLATE_H - FOOT_H + 1.4, FOOT_H - 1.4, P, 24), { tone: "mid" }));
    }
    out.push(S(k.slabOf(PLATE, -PLATE_H, PLATE_H, P, 8, 1.4), { tone: "mid" }));
    out.push(L(k.planOutline(k.insetPlan(PLATE, 4), 0, P), { tone: "faint" }));
    const screws = k.corners(PLATE, 9);
    out.push(L(screws.map(([x, y]) => k.ring(x, y, 2, 0, P, 14)).join(""), { tone: "lo" }));
    out.push(D(screws.map(([x, y]) => at([x, y, 0])), { size: 0.45 }));
    const strip = { x: PLATE.x + 20, y: PLATE.y + PLATE.d - 14, w: PLATE.w - 40, d: 6, r: 1 };
    out.push(S(k.slabOf(strip, 0, 0.5, P, 3), { tone: "lo", crease: "none" }));
    const ticks = k.topTicks(strip.x + 3, strip.x + strip.w - 3, 3.5, 5, strip.y + 0.7, 0.5, [1.8, 3.2], P);
    out.push(L(ticks.minor, { tone: "lo" }), L(ticks.major, { tone: "mid" }));
    out.push(D([at([strip.x + 1.6, strip.y + 3, 0.5]), at([strip.x + strip.w - 1.6, strip.y + 3, 0.5])], { size: 0.4 }));
    return out.join("");
  };

  return { k, P, at, S, L, D, byDepth, depth, faceRect, faceLine, facePoint, faceCircle, sideRect, sideLine, arc, base, PLATE, PLATE_H, FOOT_H };
}
