/* Specimen: a bearing table for travel (a surveyor's circle): a round graduated disc on a stepped
 * pedestal, with a pointer arm that carries a sight vane and a counterweight. The disc is the subject
 * (hi), the pointer arm is the one lit part, and the pedestal and clamps are quiet (mid and lo). The
 * arm reads 35 degrees clockwise from the zero mark, which sits at the front edge of the disc (-y). */
export const meta = {
  label:
    "A bearing table for travel: a round graduated disc on a stepped pedestal, with a pointer arm that carries a sight vane and a counterweight, on a bench plate with a ruler along its front edge.",
  extents: [
    [-40, -6, 32],
    [40, -6, 32],
    [0, -46, 32],
    [0, 34, 32],
    [-16, 8, 36],
    [17.6, -31.1, 45.2],
  ],
};

export function parts(lib) {
  const { k, P, at, S, L, D, depth } = lib;
  const out = [];

  // the pivot, the disc's centre, and the arm's frame: U along the arm, V across it
  const C = [0, -6];
  const TOP = 32; // the disc's top face
  const ARM_TOP = 35.2; // the arm's top face, lying on the disc
  const ANGLE = (35 * Math.PI) / 180;
  const U = [Math.sin(ANGLE), -Math.cos(ANGLE)];
  const V = [Math.cos(ANGLE), Math.sin(ANGLE)];
  const floor = (u, v = 0) => [C[0] + u * U[0] + v * V[0], C[1] + u * U[1] + v * V[1]];
  const rect = (u0, u1, v0, v1) => [floor(u0, v0), floor(u1, v0), floor(u1, v1), floor(u0, v1)];

  // level screw feet: four on the bench round the lowest step, each a pad, a body and a locking nut
  const feet = [30, 150, 210, 330].map((deg) => {
    const a = (deg * Math.PI) / 180;
    return [C[0] + 35 * Math.cos(a), C[1] + 35 * Math.sin(a)];
  });
  const foot = ([x, y]) => [
    S(k.cylinder(x, y, 3.4, 0, 0.6, P, 24), { tone: "lo" }),
    S(k.cylinder(x, y, 2.4, 0.6, 2, P, 20), { tone: "mid" }),
    S(k.cylinder(x, y, 3.2, 2.6, 0.9, P, 24), { tone: "lo" }),
    L(k.ring(x, y, 1.1, 3.5, P, 16), { tone: "faint" }),
    D([at([x, y, 3.5])], { size: 0.4 }),
  ];
  const stepDepth = depth(C[0], C[1], 2.5);
  const farFeet = feet.filter((f) => depth(f[0], f[1], 1.5) < stepDepth);
  const nearFeet = feet.filter((f) => depth(f[0], f[1], 1.5) >= stepDepth);
  for (const f of farFeet) out.push(...foot(f));

  // the stepped pedestal, lowest step first: r30 h5, r22 h7, and the neck r9 h14 under the disc
  out.push(S(k.cylinder(C[0], C[1], 30, 0, 5, P, 72, 0.6), { tone: "lo" }));
  out.push(L(k.sideArc(C[0], C[1], 30, 2.5, P, 48), { tone: "faint" }));
  out.push(L(k.ring(C[0], C[1], 26.5, 5, P, 72), { tone: "faint" }));
  for (const f of nearFeet) out.push(...foot(f));

  out.push(S(k.cylinder(C[0], C[1], 22, 5, 7, P, 64, 0.5), { tone: "mid" }));
  out.push(L(k.ring(C[0], C[1], 16, 12, P, 72), { tone: "faint" }));
  const stepScrews = [45, 135, 225, 315].map((deg) => {
    const a = (deg * Math.PI) / 180;
    return [C[0] + 19.5 * Math.cos(a), C[1] + 19.5 * Math.sin(a), 12];
  });
  out.push(D(stepScrews.map(([x, y, z]) => at([x, y, z])), { size: 0.45 }));

  // knurled adjustment ring on the middle step, with the clamp block on its outer edge
  out.push(S(k.cylinder(C[0], C[1], 22.8, 6.2, 3, P, 64), { tone: "mid" }));
  out.push(L(k.knurl(C[0], C[1], 22.8, 6.2, 9.2, 64, P, 0), { tone: "lo" }));

  out.push(S(k.cylinder(C[0], C[1], 9, 12, 14, P, 40), { tone: "mid" }));
  out.push(L([17, 22].map((z) => k.sideArc(C[0], C[1], 9, z, P, 24)).join(""), { tone: "faint" }));

  const CLAMP = { x: 22.9, y: C[1] - 4, w: 6, d: 8, r: 1.2 };
  const KNOB = [CLAMP.x + CLAMP.w / 2, C[1]];
  out.push(S(k.slabOf(CLAMP, 6.2, 5.8, P, 4, 0.5), { tone: "mid" }));
  out.push(S(k.cylinder(KNOB[0], KNOB[1], 2.9, 12, 0.5, P, 18), { tone: "lo" }));
  out.push(S(k.cylinder(KNOB[0], KNOB[1], 2.2, 12.5, 3, P, 18), { tone: "mid" }));
  out.push(L(k.knurl(KNOB[0], KNOB[1], 2.2, 12.5, 15.5, 16, P, 0), { tone: "lo" }));

  // the disc: a lip under a knurled band, the band's seam, and the graduated top
  out.push(S(k.cylinder(C[0], C[1], 39.2, 26, 3, P, 72), { tone: "hi" }));
  out.push(S(k.cylinder(C[0], C[1], 40, 29, 3, P, 96, 0.8), { tone: "hi" }));
  out.push(L(k.knurl(C[0], C[1], 40, 29, TOP, 90, P, 0), { tone: "lo" }));
  out.push(L(k.sideArc(C[0], C[1], 40, 29, P, 60), { tone: "faint" }));

  out.push(L(k.ring(C[0], C[1], 37.5, TOP, P, 144), { tone: "lo" }));
  const ticks = k.radialTicks(C[0], C[1], 37.5, 72, 6, TOP, [1.4, 2.8], P);
  out.push(L(ticks.minor, { tone: "lo" }), L(ticks.major, { tone: "mid" }));
  out.push(`<path class="iso-line" data-tone="hi" style="stroke-width:1.1" d="${k.lineOnTop([C[0], C[1] - 34.7], [C[0], C[1] - 37.5], TOP, P)}"/>`);
  out.push(S(k.extrude([[C[0], C[1] - 37.5], [C[0] + 1.4, C[1] - 39.6], [C[0] - 1.4, C[1] - 39.6]], TOP, 1.2, P), { tone: "hi" }));

  out.push(L(k.ring(C[0], C[1], 28, TOP, P, 120) + k.ring(C[0], C[1], 10.5, TOP, P, 72), { tone: "lo" }));
  // the scale plate's four clamp screws, each a ring with a head dot
  const plateScrews = [45, 135, 225, 315].map((deg) => {
    const a = (deg * Math.PI) / 180;
    return [C[0] + 22 * Math.cos(a), C[1] + 22 * Math.sin(a)];
  });
  out.push(L(plateScrews.map(([x, y]) => k.ring(x, y, 1.1, TOP, P, 14)).join(""), { tone: "lo" }));
  out.push(D(plateScrews.map(([x, y]) => at([x, y, TOP])), { size: 0.35 }));
  out.push(
    L(
      k.lineOnTop([C[0] - 28, C[1]], [C[0] + 28, C[1]], TOP, P) + k.lineOnTop([C[0], C[1] - 28], [C[0], C[1] + 28], TOP, P),
      { tone: "faint" },
    ),
  );

  // the pointer arm: one long thin convex rectangle, the lit part, with a level vial on its top
  out.push(S(k.extrude(rect(-14, 32, -1.6, 1.6), TOP, 3.2, P, { bevel: 0.4 }), { tone: "hi", lit: true }));
  out.push(S(k.extrude(rect(9, 9.6, -1.6, 1.6), ARM_TOP, 1.8, P), { tone: "mid" }));
  out.push(S(k.extrude(rect(9.6, 14.4, -1.6, 1.6), ARM_TOP, 1.6, P), { tone: "mid" }));
  out.push(S(k.extrude(rect(14.4, 15, -1.6, 1.6), ARM_TOP, 1.8, P), { tone: "mid" }));
  out.push(S(k.extrude(rect(9.8, 14.2, -1, 1), ARM_TOP + 1.6, 0.5, P, { bevel: 0.2 }), { tone: "hi" }));

  // the sight vane: two posts on the far end with a slit plate between them, painted far to near
  const vanePlate = rect(24.7, 29.3, -0.4, 0.4);
  const slot = k.pathOf(
    k.roundedPlan({ x: 26.6, y: 37.2, w: 0.8, d: 4.4, r: 0.4 }, 2).map(([u, z]) => at([...floor(u, 0.4), z])),
    true,
  );
  const post = (u) => {
    const [x, y] = floor(u);
    return S(k.cylinder(x, y, 1.3, ARM_TOP, 10, P, 16), { tone: "mid" });
  };
  const vaneItems = [
    { d: depth(...floor(30.6), 40.2), svg: post(30.6) },
    { d: depth(...floor(27), 39.1), svg: S(k.extrude(vanePlate, ARM_TOP, 7.8, P), { tone: "mid" }) + L(slot, { tone: "lo" }) },
    { d: depth(...floor(23.4), 40.2), svg: post(23.4) },
  ].sort((a, b) => a.d - b.d);
  out.push(...vaneItems.map((item) => item.svg));

  // the counterweight on the tail: a round boss with a screw ring and a screw head
  const [cwx, cwy] = floor(-11.5);
  out.push(S(k.cylinder(cwx, cwy, 4.5, ARM_TOP, 3, P, 36), { tone: "mid" }));
  out.push(L(k.ring(cwx, cwy, 3.2, ARM_TOP + 3, P, 36), { tone: "lo" }));
  out.push(S(k.cylinder(cwx, cwy, 1.3, ARM_TOP + 3, 1, P, 14), { tone: "mid" }));

  // the pivot cap over the arm, a washer, and the knurled lock nut on top
  out.push(S(k.cylinder(C[0], C[1], 6, ARM_TOP, 3.4, P, 48, 0.4), { tone: "mid" }));
  out.push(L(k.sideArc(C[0], C[1], 6, ARM_TOP + 1.7, P, 24), { tone: "faint" }));
  out.push(S(k.cylinder(C[0], C[1], 4.9, 38.6, 0.6, P, 36), { tone: "lo" }));
  out.push(S(k.cylinder(C[0], C[1], 3.6, 39.2, 3, P, 32), { tone: "mid" }));
  out.push(L(k.knurl(C[0], C[1], 3.6, 39.2, 42.2, 28, P, 0), { tone: "lo" }));
  out.push(L(k.ring(C[0], C[1], 2, 42.2, P, 24), { tone: "lo" }));

  return out;
}
