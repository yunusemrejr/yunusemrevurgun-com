/* Specimen: a processor in its socket under a finned heatsink, the chip an on-device chatbot runs on.
 * The socket's load lever is the one lit part. A retention wire runs over the fin tops and drops onto
 * two hooks at the socket's ends; a row of six capacitors sits on the bench beside the socket, with
 * traces that run into the socket's edge. Every line ends on a dot, an edge, a closed outline or
 * another line; only the printed rules on the spreader face stop on bare face, marked free. */

export const meta = {
  label:
    "A processor in its socket on a bench plate: a heat spreader under a nine-fin heatsink, held down by a retention wire hooked at both ends of the socket, with a load lever on one side and a row of six capacitors on the other.",
  extents: [
    [-58, -34, 0],
    [46, 36, 0],
    [-58, -34, 52],
    [46, 36, 52],
  ],
};

const SOCKET = { x: -40, y: -34, w: 80, d: 68, r: 4 };
const SOCKET_TOP = 8;
const PACKAGE = { x: -30, y: -24, w: 60, d: 48, r: 2 };
const SPREADER = { x: -23, y: -17, w: 46, d: 34, r: 1.5 };
const BASE = { x: -23, y: -17, w: 46, d: 34, r: 1 };
const BASE_Z = 15;
const FIN_TOP = 50;
const FIN_PITCH = 5.2;
const LEVER = { x: 36, y: -32, w: 4, d: 54, r: 1.2 };
const HOOK_X = 42.25;
const CAP_X = -54;
const CAP_YS = [-30, -18, -6, 6, 18, 30];

/** Points spaced about `pitch` apart along the outline of a rectangle, starting at its first corner. */
function outlinePoints({ x, y, w, d }, pitch) {
  const corners = [
    [x, y],
    [x + w, y],
    [x + w, y + d],
    [x, y + d],
  ];
  const out = [];
  for (let i = 0; i < 4; i++) {
    const [ax, ay] = corners[i];
    const [bx, by] = corners[(i + 1) % 4];
    const n = Math.round(Math.hypot(bx - ax, by - ay) / pitch);
    for (let s = 0; s < n; s++) out.push([ax + ((bx - ax) * s) / n, ay + ((by - ay) * s) / n]);
  }
  return out;
}

export function parts(lib) {
  const { k, P, at, S, L, D, faceRect, faceLine } = lib;
  const out = [];

  // one capacitor: a mid body on a small pad, a seam band, and a ring on top
  const capacitor = (cy) => ({
    pad: S(k.slabOf({ x: CAP_X - 3.6, y: cy - 3.6, w: 7.2, d: 7.2, r: 0.8 }, 0, 0.5, P, 2), { tone: "lo" }),
    body:
      S(k.cylinder(CAP_X, cy, 2.4, 0.5, 5.5, P, 20), { tone: "mid" }) +
      L(k.sideArc(CAP_X, cy, 2.4, 1.6, P, 14) + k.sideArc(CAP_X, cy, 2.4, 4.6, P, 14), { tone: "faint" }) +
      L(k.ring(CAP_X, cy, 1.5, 6, P, 16), { tone: "lo" }),
  });
  // a trace runs from the capacitor's pad to the socket's left edge, dotted at both ends
  const trace = (cy) =>
    L(k.lineOnTop([CAP_X + 3.1, cy], [SOCKET.x, cy], 0.5, P), { tone: "mid" }) +
    D([at([CAP_X + 3.1, cy, 0.5]), at([SOCKET.x, cy, 0.5])], { size: 0.45, tone: "mid" });

  // the retention hook on one end: a post on the plate, a pin cap, and the wire rising from its dot
  const hook = (sx) =>
    S(k.slabOf({ x: sx - 1.75, y: -4, w: 3.5, d: 8, r: 0.8 }, 0, 14, P, 3, 0.5), { tone: "mid" }) +
    S(k.cylinder(sx, 0, 1.2, 14, 1.5, P, 16), { tone: "hi" }) +
    D([at([sx, 0, 15.5])], { size: 0.45, tone: "mid" }) +
    L(k.rise([sx, 0], 15.5, FIN_TOP, P), { tone: "hi" });

  // capacitors, far to near; the traces at the back run behind the left hook, the front ones over it
  const caps = CAP_YS.map(capacitor);
  for (const c of caps) out.push(c.pad);
  for (const c of caps) out.push(c.body);
  CAP_YS.filter((cy) => cy < 0).forEach((cy) => out.push(trace(cy)));
  out.push(hook(-HOOK_X));
  CAP_YS.filter((cy) => cy >= 0).forEach((cy) => out.push(trace(cy)));

  // socket: body with a seam ring, a recessed outline, a ring of pins and four corner screws
  out.push(S(k.slabOf(SOCKET, 0, SOCKET_TOP, P, 4, 0.8), { tone: "mid", inner: L(k.sideSeam(SOCKET, 4, P, 4), { tone: "faint" }) }));
  out.push(L(k.planOutline(k.insetPlan(SOCKET, 7), SOCKET_TOP, P, 4), { tone: "faint" }));
  const pins = outlinePoints({ x: -35.5, y: -29.5, w: 71, d: 59 }, 2.6).filter(([x, y]) => !(Math.abs(x) >= 33.5 && Math.abs(y) >= 25));
  out.push(D(pins.map(([x, y]) => at([x, y, SOCKET_TOP])), { size: 0.35, tone: "mid" }));
  const screws = k.corners(SOCKET, 2.6);
  for (const [x, y] of screws) out.push(L(k.ring(x, y, 1.5, SOCKET_TOP, P, 14), { tone: "lo" }));
  out.push(D(screws.map(([x, y]) => at([x, y, SOCKET_TOP])), { size: 0.42, tone: "mid" }));

  // the processor package: substrate with a seam, a corner marker, and two small chips behind the spreader
  out.push(S(k.slabOf(PACKAGE, SOCKET_TOP, 3.5, P, 3, 0.4), { tone: "mid", inner: L(k.sideSeam(PACKAGE, 9.6, P, 3), { tone: "faint" }) }));
  out.push(S(k.extrude([[-29.2, 23.2], [-25.2, 23.2], [-29.2, 19.2]], 11.5, 0.6, P), { tone: "lo" }));

  // the heat spreader (the subject), its printed rules and logo on the front face (the heatsink base covers the top)
  out.push(S(k.slabOf(SPREADER, 11.5, BASE_Z - 11.5, P, 4, 0.8), { tone: "hi" }));
  out.push(L(
    [
      [12.1, -19, -3],
      [12.9, -19, -9],
      [13.7, -19, -6],
    ].map(([z, x0, x1]) => faceLine(x0, z, x1, z, SPREADER.y + SPREADER.d / 2)).join(""),
    { tone: "lo", free: true },
  ));
  out.push(L(faceRect(6, 11.9, 11, 14.3, 0.6, SPREADER.y + SPREADER.d / 2), { tone: "lo" }));

  // heatsink base, then the nine fins far to near, each with a notch line across its front face
  out.push(S(k.slabOf(BASE, BASE_Z, 4, P, 4, 0.8), { tone: "mid" }));
  for (let i = 0; i < 9; i++) {
    const xc = -20.8 + i * FIN_PITCH;
    out.push(S(k.slabOf({ x: xc - 1, y: -17, w: 2, d: 34, r: 0 }, 19, FIN_TOP - 19, P, 2, 0.3), { tone: "mid" }));
    out.push(L(k.segment([xc + 1, -17, 46], [xc + 1, 17, 46], P), { tone: "lo" }));
  }

  // chips on the package margins in front of and beside the spreader, then the load lever (the lit part)
  for (const cx of [15.5, 19.5]) out.push(S(k.slabOf({ x: cx - 1.6, y: 19, w: 3.2, d: 2.4, r: 0.4 }, 11.5, 1.1, P, 2, 0.3), { tone: "mid" }));
  for (const cy of [-20, -13, -6, 1, 8, 15]) {
    out.push(S(k.slabOf({ x: 24.6, y: cy - 1.2, w: 3.2, d: 2.4, r: 0.4 }, 11.5, 1.1, P, 2, 0.3), { tone: "mid" }));
  }
  out.push(S(k.cylinder(38, -30, 2.4, SOCKET_TOP, 5.5, P, 20), { tone: "mid" }));
  out.push(S(k.slabOf(LEVER, SOCKET_TOP, 4, P, 4, 0.5), { tone: "hi", lit: true }));
  out.push(L(k.ring(38, -30, 1.2, SOCKET_TOP + 5.5, P, 12), { tone: "lo" }));
  out.push(S(k.cylinder(38, 19.4, 2.6, 12, 2.6, P, 20), { tone: "mid" }));
  out.push(L(k.knurl(38, 19.4, 2.6, 12, 14.6, 20, P, 0.3), { tone: "lo" }));
  out.push(D([at([38, 19.4, 14.6])], { size: 0.4, tone: "mid" }));

  // the right hook stands nearer the viewer than the lever, so it is drawn over it; the wire runs over the fin tops
  out.push(hook(HOOK_X));
  out.push(`<path class="iso-line" data-tone="hi" style="stroke-width:1.2" d="${k.pathOf([at([-HOOK_X, 0, FIN_TOP]), at([HOOK_X, 0, FIN_TOP])])}"/>`);
  return out;
}
