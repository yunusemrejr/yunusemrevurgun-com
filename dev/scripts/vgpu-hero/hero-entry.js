/*
 * Landing hero background — vgpu (Vercel WebGPU lib) contour field.
 *
 * WHY: vgpu is an npm ESM library but this site is plain PHP with no bundler,
 * so this entry is bundled ONCE with esbuild into a committed static asset:
 *
 *   cd dev/scripts/vgpu-hero && npm install && npm run build
 *   -> writes assets/js/vgpu-hero.min.js (IIFE, self-contained, committed)
 *
 * Design: quiet topographic contour lines (fbm value-noise field) drifting
 * slowly over the deep room background. The pointer raises a soft hill
 * that bends the contours toward it — interaction as terrain, not decoration.
 * Palette mirrors the public tokens in assets/css/variables.css:
 *   bg #15120f, ink (lamplight amber) #e0a33f, secondary #8ea6c8.
 * Fallback: no WebGPU / init failure -> canvas stays hidden (CSS bg + HTML
 * hero text carry the page). prefers-reduced-motion -> single static frame.
 */
import { clock, effect, frameLoop, init, surface } from "vgpu";

const SHADER = /* wgsl */ `
struct Params {
  time: f32,
  width: f32,
  height: f32,
  mx: f32,      // pointer x in uv (0..1, top-origin)
  my: f32,      // pointer y in uv
  glow: f32,    // 0..1 pointer presence
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

@fragment
fn fs_main(@location(0) uv: vec2f) -> @location(0) vec4f {
  let aspect = params.width / params.height;
  let p = vec2f(uv.x * aspect, uv.y);

  // slow drift
  let t = params.time * 0.02;
  let base = fbm(p * 2.4 + vec2f(t, -t * 0.6) + params.seed);
  let detail = fbm(p * 5.1 + vec2f(-t * 0.4, t * 0.3)) * 0.35;

  // pointer raises a soft hill that bends the contours
  let m = vec2f(params.mx * aspect, params.my);
  let d2 = dot(p - m, p - m);
  let bump = 0.28 * exp(-d2 * 5.0) * params.glow;

  let field = base + detail + bump;

  // contour lines: thin at every level, heavier every 4th
  let levels = 14.0;
  let v = field * levels;
  let w = fwidth(v) * 1.4;
  let g = min(fract(v), 1.0 - fract(v));
  let line = 1.0 - smoothstep(0.0, w, g);
  let vm = field * levels / 4.0;
  let wm = fwidth(vm) * 1.2;
  let gm = min(fract(vm), 1.0 - fract(vm));
  let major = 1.0 - smoothstep(0.0, wm, gm);

  let bg = vec3f(0.082, 0.071, 0.059);          // #15120f room
  let ink = vec3f(0.878, 0.639, 0.247);         // #e0a33f lamplight
  let sec = vec3f(0.557, 0.651, 0.784);         // #8ea6c8

  var col = bg;
  col = mix(col, ink, line * 0.24);
  col = mix(col, ink, major * 0.17);

  // soft secondary tint hugging the pointer hill (feedback, not glow)
  let halo = exp(-d2 * 9.0) * params.glow;
  col = mix(col, sec, halo * 0.14);

  // paper grain
  let grain = hash2(uv * params.width + vec2f(fract(params.time) * 61.7, 0.0));
  col += (grain - 0.5) * 0.012;

  return vec4f(col, 1.0);
}
`;

export async function mountVgpuHero(canvas) {
  const hero = canvas ? (canvas.closest(".ui-hero") || canvas.parentElement) : null;

  const fallback = () => {
    // No WebGPU / init failed: hand over to the animated CSS fallback.
    if (hero) hero.classList.add("ui-hero--fallback");
    return false;
  };

  if (!canvas || !("gpu" in navigator)) return fallback();

  let gpu;
  try {
    gpu = await init({ powerPreference: "low-power" });
  } catch {
    return fallback(); // WebGPU unavailable or no adapter
  }

  let canvasSurface;
  try {
    canvasSurface = surface(gpu, canvas, { dpr: [1, 1.75] });
  } catch {
    return fallback();
  }

  const params = effect(gpu, SHADER, {
    set: {
      params: {
        time: 12.0, // start mid-field so frame 0 isn't the flat seed
        width: canvasSurface.size[0],
        height: canvasSurface.size[1],
        mx: 0.5,
        my: 0.4,
        glow: 0.0,
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
    if (reduced) params.draw(canvasSurface); // keep the static frame filled
  });

  // Pointer: target position + eased follow so the hill glides, not snaps.
  let tx = 0.5,
    ty = 0.4,
    cx = 0.5,
    cy = 0.4;
  let glow = 0.0;

  if (!reduced) {
    hero.addEventListener(
      "pointermove",
      (e) => {
        const r = hero.getBoundingClientRect();
        tx = (e.clientX - r.left) / Math.max(1, r.width);
        ty = (e.clientY - r.top) / Math.max(1, r.height);
        glow = 1.0;
      },
      { passive: true },
    );
    hero.addEventListener(
      "pointerleave",
      () => {
        glow = 0.0;
      },
      { passive: true },
    );
  }

  if (reduced) {
    // One static frame — the field is drawn, nothing animates.
    params.draw(canvasSurface);
    canvas.dataset.vgpu = "static";
    return { stop: () => {}, surface: canvasSurface, effect: params };
  }

  const time = clock(gpu);
  const loop = frameLoop(
    gpu,
    (frame) => {
      cx += (tx - cx) * 0.08;
      cy += (ty - cy) * 0.08;
      params.set({
        params: {
          time: time.time,
          mx: cx,
          my: cy,
          glow,
        },
      });
      frame.pass(canvasSurface, params);
    },
    { fps: 45 },
  );

  canvas.dataset.vgpu = "live";
  return { stop: () => loop.stop(), surface: canvasSurface, effect: params };
}
