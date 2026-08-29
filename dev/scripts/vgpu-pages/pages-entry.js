/*
 * Inner-page ambient backgrounds — vgpu (Vercel WebGPU lib), one bundle for
 * all pages, built ONCE with esbuild into a committed static asset:
 *
 *   cd dev/scripts/vgpu-pages && npm install && npm run build
 *   -> writes assets/js/vgpu-pages.min.js (IIFE, self-contained, committed)
 *
 * Same palette + fallback philosophy as the landing hero (dev/scripts/
 * vgpu-hero/hero-entry.js), but every page gets its OWN quiet effect instead
 * of the contour field. All effects are ambient only (no pointer tracking —
 * content sits above them and they must never fight the UI):
 *
 *   about          wind       gusty paper wisps from the top-left corner
 *   rmrp           memories   fading dust specks (memory dust)
 *   music          wave       one thin undulating waveform ribbon
 *   comedy         bubbles    sparse faint rings rising (shower thoughts)
 *   science-corner lattice    node lattice slowly lighting up
 *   post-code      grid       blueprint grid with traveling scan pulses
 *
 * Fallback: no WebGPU / init failure -> canvas is removed, plain paper
 * background stays (page fully functional). prefers-reduced-motion -> one
 * static frame. Colors from dev/new_design_plan_and_assets/palette.md.
 */
import { clock, effect, frameLoop, init, surface } from "vgpu";

const SHADER = /* wgsl */ `
struct Params {
  time: f32,
  width: f32,
  height: f32,
  seed: f32,
}

@group(0) @binding(0) var<uniform> params: Params;

fn hash2(p: vec2f) -> f32 {
  return fract(sin(dot(p, vec2f(127.1, 311.7))) * 43758.5453);
}

fn vnoise(p: vec2f) -> f32 {
  let i = floor(p);
  let f = fract(p);
  let u = f * f * (3.0 - 2.0 * f);
  let a = hash2(i);
  let b = hash2(i + vec2f(1.0, 0.0));
  let c = hash2(i + vec2f(0.0, 1.0));
  let d = hash2(i + vec2f(1.0, 1.0));
  return mix(mix(a, b, u.x), mix(c, d, u.x), u.y);
}

fn fbm(p: vec2f) -> f32 {
  var v = 0.0;
  var amp = 0.5;
  var q = p;
  for (var k = 0; k < 4; k++) {
    v += amp * vnoise(q);
    q = q * 2.03 + vec2f(17.3, 9.1);
    amp *= 0.5;
  }
  return v;
}

const BG = vec3f(0.890, 0.886, 0.871);   // #e3e2de paper
const INK = vec3f(0.518, 0.565, 0.643);  // #8490a4
const SEC = vec3f(0.561, 0.651, 0.651);  // #8fa6a6
`;

/* About — wind: brief gusts of paper wisps blown in from the top-left.
 * Two out-of-phase slow noises gate the gusts, so the loop never reads as a
 * fixed cycle; wisps are horizontally stretched noise filaments. */
const WIND = SHADER + /* wgsl */ `
@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let aspect = params.width / params.height;
  let p = vec2f(uv.x * aspect, uv.y);
  let t = params.time;

  // gust envelope: quiet most of the time, brief swells
  let g1 = vnoise(vec2(t * 0.050, 3.7));
  let g2 = vnoise(vec2(t * 0.023, 9.1));
  let gust = smoothstep(0.60, 0.95, g1 * 0.65 + g2 * 0.50);

  // wind strength grows toward the top-left corner (the source)
  let corner = 1.0 - clamp(length(p * vec2f(0.9, 1.4)) / max(aspect, 1.0), 0.0, 1.0);

  // horizontally stretched filaments, advected faster near the source
  let flow = t * (0.10 + 0.08 * corner);
  let wisp = fbm(vec2(p.x * 1.9 - flow * (0.7 + 0.6 * corner), p.y * 3.6 - flow * 0.5));
  let wisp2 = fbm(vec2(p.x * 3.4 - flow * 1.8, p.y * 6.2 - flow * 0.9) + vec2f(4.2, 0.0));
  let v = (wisp * 0.7 + wisp2 * 0.3) * 6.0;
  let w = fwidth(v) * 1.35;
  let g = min(fract(v), 1.0 - fract(v));
  let streak = 1.0 - smoothstep(0.0, w, g);

  // keep away from the very edges of the screen
  let edge = smoothstep(0.0, 0.10, uv.x) * smoothstep(0.0, 0.08, uv.y) *
             smoothstep(1.0, 0.92, uv.x) * smoothstep(1.0, 0.94, uv.y);

  let strength = gust * (0.35 + 0.65 * corner) * edge;
  let col = mix(BG, INK, streak * 0.13 * strength);
  return vec4f(col, 1.0);
}
`;

/* rmrp — memories: sparse dust specks that fade in and out on their own long
 * clocks, like moments surfacing and sinking again. */
const MEMORIES = SHADER + /* wgsl */ `
@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let aspect = params.width / params.height;
  let p = vec2f(uv.x * aspect, uv.y);
  let t = params.time;

  let cells = vec2f(9.0 * max(aspect, 1.0), 9.0);
  let id = floor(p * cells);
  let h = hash2(id);
  let h2 = hash2(id + 31.7);
  let h3 = hash2(id + 57.3);

  // each speck fades in/out on its own offset clock
  let cyc = fract(t * 0.02 * (0.5 + h) + h2 * 7.13);
  let fade = smoothstep(0.0, 0.28, cyc) * smoothstep(1.0, 0.72, cyc);
  let on = step(0.58, hash2(id + 11.1));

  // tiny drift so nothing sits perfectly still
  let wob = vec2f(sin(t * (0.10 + h) + h2 * 6.28), cos(t * (0.13 + h2) + h3 * 6.28)) * 0.07;
  let d = length(fract(p * cells) - 0.5 - (vec2f(h, h3) - 0.5) * 0.7 + wob);
  let r = 0.06 + 0.06 * h2;
  let speck = smoothstep(r, r * 0.3, d);
  let halo = smoothstep(r * 2.8, 0.0, d) * 0.25;

  let col = mix(BG, INK, (speck * 0.28 + halo * 0.10) * fade * on);
  return vec4f(col, 1.0);
}
`;

/* music — wave: one thin waveform ribbon drifting through the lower third,
 * with a faint echo. Amplitude breathes on a slow global swell. */
const WAVE = SHADER + /* wgsl */ `
@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let tt = params.time;
  let base = 0.74 + 0.05 * sin(uv.x * 2.2 + tt * 0.11);
  let breathe = 0.7 + 0.3 * sin(tt * 0.07 + 1.0);
  let d = 0.020 * sin(uv.x * 9.0 - tt * 0.50)
        + 0.012 * sin(uv.x * 17.0 + tt * 0.34)
        + 0.007 * sin(uv.x * 31.0 - tt * 0.92);
  let y = base + d * breathe;
  let line = 1.0 - smoothstep(0.0, 0.0022 + fwidth(y), abs(uv.y - y));
  let y2 = base + 0.045 + d * 0.55;
  let echo = 1.0 - smoothstep(0.0, 0.0018 + fwidth(y2), abs(uv.y - y2));

  var col = mix(BG, INK, line * 0.20);
  col = mix(col, SEC, echo * 0.09);
  return vec4f(col, 1.0);
}
`;

/* comedy — bubbles: sparse faint rings rising with a slight wobble, like
 * shower thoughts. Not every cell is populated; wrap edges fade. */
const BUBBLES = SHADER + /* wgsl */ `
@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let aspect = params.width / params.height;
  let p = vec2f(uv.x * aspect, uv.y);
  let t = params.time;

  let cells = vec2f(6.0 * max(aspect, 1.0), 6.0);
  let id = floor(p * cells);
  let h = hash2(id);
  let h2 = hash2(id + 23.1);
  let h3 = hash2(id + 47.9);

  let speed = 0.02 + 0.03 * h2;
  let cyc = fract(uv.y * cells.y + t * speed * cells.y * (0.4 + h) + h2 * 10.0);
  let edgeFade = smoothstep(0.0, 0.12, cyc) * smoothstep(1.0, 0.88, cyc);
  let on = step(0.64, hash2(id + 91.3));

  let wob = 0.12 * sin(t * (0.5 + h) + h3 * 6.28);
  let cx = fract(p.x * cells.x) - 0.5 - (h - 0.5) * 0.6 + wob;
  let cy = cyc - 0.5;
  let d = length(vec2f(cx, cy) * vec2f(1.0, 1.15));
  let r = 0.10 + 0.10 * h3;
  let ring = smoothstep(r, r - 0.025, d) * smoothstep(r * 0.45, r * 0.75, d);

  let col = mix(BG, INK, ring * on * edgeFade * 0.20);
  return vec4f(col, 1.0);
}
`;

/* science-corner — lattice: fixed node lattice; a slow noise field lights
 * individual nodes up and down, like ideas connecting and fading. */
const LATTICE = SHADER + /* wgsl */ `
@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let aspect = params.width / params.height;
  let p = vec2f(uv.x * aspect, uv.y);
  let t = params.time;

  let spacing = 0.07;
  let gp = vec2f(p.x / spacing, p.y / spacing);
  let id = floor(gp);
  let f = fract(gp) - 0.5;

  let b = smoothstep(0.52, 0.85, fbm(id * 0.33 + vec2f(t * 0.025, -t * 0.017)));
  let dotMask = smoothstep(0.16, 0.05, length(f)) * b;

  // faint diagonal scan band ties the nodes together
  let bandPos = (p.x / aspect + p.y) - fract(t * 0.012) * 2.2 - 0.4;
  let band = exp(-bandPos * bandPos * 8.0) * 0.35;

  let col = mix(BG, INK, dotMask * 0.20 + band * 0.05);
  return vec4f(col, 1.0);
}
`;

/* post-code — grid: faint blueprint grid with slow traveling scan pulses.
 * Distinct from about's organic wisps: all straight lines, mechanical sweep. */
const GRID = SHADER + /* wgsl */ `
@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let aspect = params.width / params.height;
  let p = vec2f(uv.x * aspect, uv.y);
  let t = params.time;

  let s = 0.055;
  let cx = p.x / s;
  let cy = p.y / s;
  let dx = min(fract(cx), 1.0 - fract(cx));
  let dy = min(fract(cy), 1.0 - fract(cy));
  let lx = 1.0 - smoothstep(0.0, fwidth(cx) * 1.6, dx);
  let ly = 1.0 - smoothstep(0.0, fwidth(cy) * 1.6, dy);
  let grid = max(lx, ly);

  // one vertical and one horizontal scan pulse, out of phase
  let sx = uv.x - fract(t * 0.014) * 1.3 - 0.15;
  let sy = uv.y - fract(t * 0.010 + 0.5) * 1.3 - 0.15;
  let scanX = exp(-sx * sx * 90.0);
  let scanY = exp(-sy * sy * 90.0);

  var col = mix(BG, INK, grid * 0.085);
  col = mix(col, SEC, grid * (scanX + scanY) * 0.30);
  return vec4f(col, 1.0);
}
`;

const EFFECTS = {
  wind: { shader: WIND, fps: 30 },
  memories: { shader: MEMORIES, fps: 30 },
  wave: { shader: WAVE, fps: 30 },
  bubbles: { shader: BUBBLES, fps: 30 },
  lattice: { shader: LATTICE, fps: 30 },
  grid: { shader: GRID, fps: 30 },
};

const STATIC_TIME = 37.0; // mid-cycle frame for prefers-reduced-motion

export async function mountPageFx(canvas) {
  const fxName = canvas && canvas.dataset ? canvas.dataset.fx : "";
  const cfg = EFFECTS[fxName];
  if (!cfg) return false;

  const fail = () => {
    // Plain paper background carries the page without the canvas.
    if (canvas && canvas.remove) canvas.remove();
    return false;
  };

  if (!canvas || !("gpu" in navigator)) return fail();

  let gpu;
  try {
    gpu = await init({ powerPreference: "low-power" });
  } catch {
    return fail();
  }

  let canvasSurface;
  try {
    canvasSurface = surface(gpu, canvas, { dpr: [1, 1.5] });
  } catch {
    return fail();
  }

  const params = effect(gpu, cfg.shader, {
    set: {
      params: {
        time: 12.0,
        width: canvasSurface.size[0],
        height: canvasSurface.size[1],
        seed: 0.0,
      },
    },
  });

  const reduced =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // NOTE: onResize fires synchronously on subscription — `reduced` must be
  // declared above this line (TDZ otherwise).
  canvasSurface.onResize(({ width, height }) => {
    params.set({ params: { width, height } });
    if (reduced) params.draw(canvasSurface);
  });

  canvas.classList.add("is-live"); // CSS fades it in over the paper bg

  if (reduced) {
    params.set({ params: { time: STATIC_TIME } });
    params.draw(canvasSurface);
    canvas.dataset.vgpu = "static";
    return { stop: () => {}, surface: canvasSurface, effect: params };
  }

  const time = clock(gpu);
  const loop = frameLoop(
    gpu,
    (frame) => {
      params.set({ params: { time: time.time } });
      frame.pass(canvasSurface, params);
    },
    { fps: cfg.fps },
  );

  canvas.dataset.vgpu = "live";
  return { stop: () => loop.stop(), surface: canvasSurface, effect: params };
}
