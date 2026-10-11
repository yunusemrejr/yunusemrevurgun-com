/* Specimen: a bench patch panel for the contact page. An upright panel on a base rail carries six network
 * jacks in a row; a patch cable is plugged into jack 3 and runs down to a second plug lying on the bench, and a
 * short loopback cable joins jacks 5 and 6. The plug in jack 3 is the one lit part.
 *
 * Layout (world units, z up): the panel's +y face is at y = -21. Raised bezels (y -21..-19) hold the jack wells
 * at z 16..28, LED housings sit above each jack at z 30..34, the port marks at z 37..39, the groove at z 13 and
 * the top seam at z 44. Plugs stand on the face (y -21..-7) with their boots pointing toward the viewer. */
export const meta = {
  label:
    "A bench patch panel for a contact page: an upright panel on a base rail holds a row of six network jacks, with a patch cable plugged into jack 3 that runs down to a second plug lying on the bench, and a short loopback cable between jacks 5 and 6.",
  extents: [
    [-64, -30, 0],
    [64, -14, 6],
    [-60, -21, 46],
    [60, -21, 46],
    [39, 14, 37],
    [37, 20, 6],
  ],
};

const FACE = -21; // the panel's +y face
const BEZEL = -19; // front of the raised jack bezels
const LEDF = -20; // front of the LED housings
const PLUG_Z = 22.5; // centre height of every jack plug and boot
const PITCH = 19.5;
const JACKS = [0, 1, 2, 3, 4, 5].map((i) => (i - 2.5) * PITCH); // centres, left to right
const CABLED = 2; // jack 3 (0-based)
const CLIPS = [-46, 46]; // clear of the plugs on the screen, at the two ends of the rail's front strip
const PANEL = { x: -60, y: -26, w: 120, d: 5, r: 2 };
const RAIL = { x: -64, y: -30, w: 128, d: 16, r: 2 };

/** Catmull-Rom spline through 3D points, sampled `per` steps per span (endpoints repeated at the ends). */
function spline(points, per = 8) {
  const out = [];
  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[Math.max(0, i - 1)];
    const p1 = points[i];
    const p2 = points[i + 1];
    const p3 = points[Math.min(points.length - 1, i + 2)];
    for (let s = 0; s < per; s++) {
      const t = s / per;
      const t2 = t * t;
      const t3 = t2 * t;
      out.push([0, 1, 2].map((c) =>
        0.5 * (2 * p1[c] + (-p0[c] + p2[c]) * t + (2 * p0[c] - 5 * p1[c] + 4 * p2[c] - p3[c]) * t2 + (-p0[c] + 3 * p1[c] - 3 * p2[c] + p3[c]) * t3),
      ));
    }
  }
  out.push(points[points.length - 1]);
  return out;
}

export function parts(lib) {
  const { k, P, at, S, L, D, faceRect, faceLine, facePoint, faceCircle } = lib;
  const out = [];

  // base rail: a seam round its front face, and two screw heads on its top edge
  out.push(S(k.slabOf(RAIL, 0, 6, P, 6, 1), { tone: "mid", inner: L(k.sideSeam(RAIL, 3, P, 6), { tone: "faint" }) }));
  out.push(L(k.planOutline({ x: -52, y: -19.5, w: 104, d: 3, r: 1.4 }, 6, P, 6), { tone: "faint" })); // the rail's slot
  for (const x of [-56, 56]) {
    out.push(L(k.ring(x, -17.5, 2.2, 6, P, 16), { tone: "lo" }));
    out.push(S(k.cylinder(x, -17.5, 1.3, 6, 1.2, P, 16), { tone: "mid", crease: "none" }));
    out.push(D([at([x, -17.5, 7.2])], { size: 0.35 }));
  }
  // left end plate of the rail (further from the viewer than the panel, so it goes first)
  out.push(S(k.slabOf({ x: -64, y: -30, w: 3, d: 16, r: 1 }, 6, 10, P, 3, 0.5), { tone: "mid" }));

  // the panel: a bevel and seam along the top edge, a groove low on the face, a recessed top outline
  out.push(
    S(k.slabOf(PANEL, 6, 40, P, 6, 1.2), {
      tone: "hi",
      inner: L(k.sideSeam(PANEL, 44, P, 6) + k.sideSeam(PANEL, 13, P, 6), { tone: "faint" }),
    }),
  );
  out.push(L(k.planOutline(k.insetPlan(PANEL, 1.6), 46, P, 4), { tone: "faint" }));

  // face details: four corner screws and a label plate with two printed rules
  for (const x of [-55.5, 55.5]) {
    for (const z of [40.6, 9.6]) {
      out.push(L(faceCircle(x, z, 1.9, FACE, 14), { tone: "lo" }));
      out.push(D([facePoint(x, z, FACE)], { size: 0.45 }));
    }
  }
  out.push(L(faceRect(-51, 40.4, -34, 42.8, 0.8, FACE), { tone: "lo" }));
  out.push(L(faceLine(-48.5, 42, -37, 42, FACE) + faceLine(-48.5, 41, -41.5, 41, FACE), { tone: "lo", free: true }));
  // cable-tie slots under each jack: closed slots in the bottom band
  out.push(L(JACKS.map((cx) => faceRect(cx - 2.2, 9.2, cx + 2.2, 10.8, 0.5, FACE, 2)).join(""), { tone: "lo" }));

  // the six jacks: bezel, well with latch notch and contacts, LED housing with its lamp and port mark
  JACKS.forEach((cx, i) => {
    out.push(S(k.slabOf({ x: cx - 8, y: FACE, w: 16, d: 2, r: 1.2 }, 14.5, 15, P, 3, 0.5), { tone: "mid" }));
    const x0 = cx - 7;
    const x1 = cx + 7;
    out.push(`<path class="rk-well" d="${faceRect(x0, 16, x1, 28, 1.6, BEZEL)}"/>`);
    out.push(L(faceRect(x0, 16, x1, 28, 1.6, BEZEL), { tone: "lo" }));
    out.push(L(faceRect(cx - 3, 27.2, cx + 3, 29, 0.6, BEZEL), { tone: "lo" }));
    const contacts = [0, 1, 2, 3, 4, 5, 6, 7].map((j) => {
      const x = cx - 4.55 + j * 1.3;
      return faceRect(x - 0.45, 17.4, x + 0.45, 21.4, 0.4, BEZEL);
    });
    out.push(L(contacts.join(""), { tone: "lo" }));

    out.push(S(k.slabOf({ x: cx - 2, y: FACE, w: 4, d: 1, r: 0.6 }, 30.4, 4, P, 2, 0.3), { tone: "mid" }));
    out.push(L(faceCircle(cx, 32.4, 1.5, LEDF, 16), { tone: "lo" }));
    const [lx, ly] = facePoint(cx, 32.4, LEDF);
    out.push(`<circle class="rk-led rk-led--${i === CABLED ? "ok" : "dim"}" cx="${lx.toFixed(1)}" cy="${ly.toFixed(1)}" r="1.1"/>`);
    out.push(L(faceLine(cx - 3, 37.4, cx + 3, 37.4, FACE) + faceLine(cx - 3, 39.2, cx - 0.6, 39.2, FACE), { tone: "lo", free: true }));
  });

  // two cable clips on the rail, in front of the panel
  for (const cx of CLIPS) {
    out.push(S(k.slabOf({ x: cx - 5, y: -20, w: 10, d: 4.5, r: 1.2 }, 6, 4, P, 3, 0.6), { tone: "mid" }));
    out.push(S(k.slabOf({ x: cx - 4, y: -19.8, w: 8, d: 3, r: 1 }, 10, 1.6, P, 3, 0.4), { tone: "mid", crease: "lo" }));
    out.push(S(k.cylinder(cx, -18.3, 1.1, 11.6, 0.8, P, 14), { tone: "lo", crease: "none" }));
    out.push(D([at([cx, -18.3, 12.4])], { size: 0.35 }));
  }
  // right end plate of the rail (nearer than the panel, so it goes after the structure)
  out.push(S(k.slabOf({ x: 61, y: -30, w: 3, d: 16, r: 1 }, 6, 10, P, 3, 0.5), { tone: "mid" }));

  // a plug on the face: body in the jack, a latch on top, a collar and a boot stepping down in size
  const plug = (cx, lit) => [
    S(k.slabOf({ x: cx - 6, y: FACE, w: 12, d: 14, r: 2 }, 17, 11, P, 4, 0.8), { tone: "hi", lit }),
    S(k.slabOf({ x: cx - 2.5, y: -18, w: 5, d: 8, r: 1 }, 28, 1.6, P, 3, 0.4), { tone: "hi", crease: "lo" }),
    S(k.slabOf({ x: cx - 4.5, y: -7, w: 9, d: 1.6, r: 1.2 }, 18, 9, P, 3, 0.3), { tone: "hi" }),
    S(k.slabOf({ x: cx - 4, y: -5.4, w: 8, d: 4.6, r: 2 }, 18.5, 8, P, 4, 0.5), { tone: "hi" }),
    S(k.slabOf({ x: cx - 2.5, y: -0.8, w: 5, d: 5.8, r: 1.6 }, 20, 5, P, 3, 0.4), { tone: "hi" }),
  ];
  out.push(...plug(JACKS[CABLED], true));
  out.push(...plug(JACKS[4], false));
  out.push(...plug(JACKS[5], false));

  // the patch cable: from the boot of jack 3, down in a smooth curve to the bench, then right to the lying plug
  const cabled = [
    [JACKS[CABLED], 5, PLUG_Z],
    [JACKS[CABLED], 9, 16.5],
    [JACKS[CABLED], 12, 9],
    [JACKS[CABLED], 13.6, 3.6],
    [-7.8, 14.4, 1.6],
    [-1, 15, 1.6],
    [6, 15, 1.6],
    [13, 14, 1.3],
  ];
  const cable = spline(cabled, 8).map((p) => at(p));
  out.push(k.wireSvg(cable[0], cable[cable.length - 1], { via: cable.slice(1, -1), tone: "hi", size: 1.2, ends: "from" }));

  // the loopback: from the boot of jack 5, up and forward in front of the plugs, down into the boot of jack 6
  const [a, b] = [JACKS[4], JACKS[5]];
  const loop = [];
  for (let s = 0; s <= 16; s++) {
    const t = s / 16;
    const bulge = Math.sin(Math.PI * t);
    loop.push([a + (b - a) * t, 5 + 9 * bulge, PLUG_Z + 14 * bulge]);
  }
  const loopPts = loop.map((p) => at(p));
  out.push(k.wireSvg(loopPts[0], loopPts[loopPts.length - 1], { via: loopPts.slice(1, -1), tone: "hi", size: 1.2 }));

  // the lying plug, nearest the viewer, painted last: boot tip, boot, collar, body, latch
  out.push(S(k.slabOf({ x: 13, y: 12.2, w: 4, d: 3.6, r: 1.2 }, 0, 2.6, P, 3, 0.3), { tone: "hi" }));
  out.push(S(k.slabOf({ x: 17, y: 10.2, w: 3.8, d: 7.6, r: 1.6 }, 0, 3.6, P, 3, 0.4), { tone: "hi" }));
  out.push(S(k.slabOf({ x: 20.8, y: 9.5, w: 2.2, d: 9, r: 1 }, 0, 4, P, 3, 0.3), { tone: "hi" }));
  out.push(S(k.slabOf({ x: 23, y: 8, w: 14, d: 12, r: 2 }, 0, 4.5, P, 4, 0.6), { tone: "hi" }));
  out.push(S(k.slabOf({ x: 29.5, y: 11.5, w: 5, d: 5, r: 1 }, 4.5, 1.2, P, 3, 0.4), { tone: "hi", crease: "lo" }));
  // the patch cable's terminal on the lying plug's boot: drawn last so it sits on the boot
  out.push(D([at([13, 14, 1.3])], { size: 1.2, tone: "hi" }));

  return out;
}
