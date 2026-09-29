/* =============================================================================
   Jell-omo, in 3D.
   -----------------------------------------------------------------------------
   The mascot on this site is a 3D render of a red fluted jelly in a navy and
   cream baseball cap. This file rebuilds that render as a live object: a small
   fragment shader ray-marches a signed-distance model of the jelly and the
   cap, shades it as translucent glossy jelly, and hands the result to the 2D
   room canvas as a sprite. Because it is a real model, it can turn to face
   the way it walks, squash when it lands, wobble when it stops, and show the
   back of its cap when it walks away from you.

   If WebGL is not available the same call falls back to the flat mascot image,
   tilted and squashed, so the shop still works everywhere.
   ========================================================================== */

const VERT = `
attribute vec2 aPos;
varying vec2 vUv;
void main() {
  vUv = aPos * 0.5 + 0.5;
  gl_Position = vec4(aPos, 0.0, 1.0);
}`;

const FRAG = `
precision highp float;
varying vec2 vUv;

uniform float uTime;
uniform float uYaw;      // turn about the vertical axis, radians (0 = face the viewer)
uniform float uLean;     // lean into the walk, radians
uniform vec2  uSquash;   // x: width scale, y: height scale
uniform float uWob;      // 0..1 jelly wobble amplitude
uniform float uMood;     // 0 content ^_^ ... 1 surprised o_o
uniform float uSleep;    // 0..1 sleepy eyes
uniform float uCapTilt;  // cap lag, radians

const float PI = 3.14159265;
const float BODY_H = 1.62;

mat2 rot(float a) { float c = cos(a), s = sin(a); return mat2(c, -s, s, c); }
float smin(float a, float b, float k) {
  float h = clamp(0.5 + 0.5 * (b - a) / k, 0.0, 1.0);
  return mix(b, a, h) - k * h * (1.0 - h);
}

// World -> model space. The model stands on y = 0 with its face towards +z.
vec3 toModel(vec3 p) {
  p.xy = rot(-uLean) * p.xy;
  p.x /= uSquash.x;
  p.z /= uSquash.x;
  p.y /= uSquash.y;
  p.xz = rot(-uYaw) * p.xz;
  return p;
}

// --- the jelly: a fluted dome, like a pudding out of its mould ------------
float sdBody(vec3 p) {
  float t = clamp(p.y / BODY_H, 0.0, 1.0);
  float a = atan(p.z, p.x);
  // Ten lobes with creases between them, strongest low down and fading into
  // the dome at the top, the way a mould's flutes converge.
  float l = abs(cos(a * 5.0));
  l = sqrt(l * l + 0.02) / sqrt(1.02);
  float flute = 1.0 - 0.115 * (1.0 - l) * (1.0 - 0.9 * smoothstep(0.5, 1.0, t));
  // Dome profile with a soft belly just above the base.
  float prof = sqrt(max(1.0 - pow(t, 2.3), 0.0));
  float belly = 1.0 + 0.05 * smoothstep(0.0, 0.3, t) * (1.0 - smoothstep(0.3, 0.8, t));
  float R = prof * belly * flute;
  // Jelly wobble: a travelling ripple that settles.
  R += uWob * 0.035 * sin(p.y * 7.0 - uTime * 11.0) * (1.0 - t * 0.5);
  float rho = length(p.xz);
  float d = (rho - R) * 0.62;
  d = max(d, -p.y + 0.0);
  d = max(d, p.y - BODY_H);
  // Soften the bottom rim so it reads as a thick slab and not a cut.
  return d - 0.0;
}

// --- the cap ---------------------------------------------------------------
// Cap space: origin at the middle of the rim, tilted a little forward and
// turned a little to the side, like the picture. The rim rests where the dome
// is as wide as the cap's opening (a little over 1.0 up), so the cap is pulled
// down over the jelly and grips it, instead of hovering above its very tip.
const float CAP_Y = 1.12;
vec3 toCap(vec3 p) {
  p -= vec3(0.0, CAP_Y, -0.03);
  p.xz = rot(-0.34) * p.xz;      // turned towards the viewer's right
  p.yz = rot(0.03 + uCapTilt) * p.yz;   // barely nodding
  p.xy = rot(-uCapTilt * 0.7 - 0.05) * p.xy;
  return p;
}

float sdEllipsoid(vec3 p, vec3 r) {
  float k0 = length(p / r);
  float k1 = length(p / (r * r));
  return k0 * (k0 - 1.0) / max(k1, 1e-4);
}

float sdCrown(vec3 q) {
  float d = sdEllipsoid(q + vec3(0.0, 0.0, 0.03), vec3(0.86, 0.82, 0.92));
  d = max(d, -(q.y + 0.08));                                // open at the rim
  d = max(d, -sdEllipsoid(q + vec3(0.0, 0.08, 0.03), vec3(0.79, 0.76, 0.85))); // hollow
  return d;
}

float sdBrim(vec3 q) {
  q.y += 0.02;
  float zz = max(q.z - 0.62, 0.0);
  q.y += 0.2 * zz * zz;                                   // curved down
  vec2 e = vec2(q.x / 0.86, (q.z - 0.5) / 0.68);
  float ed = (length(e) - 1.0) * 0.6;
  float slab = max(ed, abs(q.y) - 0.042);
  return max(slab, 0.56 - q.z);
}

// map returns distance and a material id in y:
//   0 jelly, 1 cream felt, 2 navy felt
vec2 map(vec3 wp) {
  vec3 p = toModel(wp);
  float dBody = sdBody(p);
  vec3 q = toCap(p);
  float dCrown = sdCrown(q);
  float dBrim = sdBrim(q);
  float dButton = length(q - vec3(0.0, 0.82, 0.0)) - 0.065;
  float dCap = smin(dCrown, dBrim, 0.05);
  dCap = min(dCap, dButton);
  float m = (dCap < dBody) ? 1.0 : 0.0;
  // Navy: the brim, the button, and the back panels.
  if (m > 0.5) {
    float phi = atan(q.x, q.z);
    bool front = abs(phi) < 1.0472;
    bool inBrim = dBrim < dCrown + 0.03 && q.z > 0.5;
    if (!front || inBrim || dButton < dCrown) m = 2.0;
  }
  return vec2(min(dBody, dCap) * min(uSquash.x, uSquash.y), m);
}

float mapD(vec3 p) { return map(p).x; }

vec3 calcNormal(vec3 p) {
  const vec2 k = vec2(1.0, -1.0);
  const float h = 0.0016;
  return normalize(
    k.xyy * mapD(p + k.xyy * h) + k.yyx * mapD(p + k.yyx * h) +
    k.yxy * mapD(p + k.yxy * h) + k.xxx * mapD(p + k.xxx * h));
}

float ambientOcc(vec3 p, vec3 n) {
  float occ = 0.0, sca = 1.0;
  for (int i = 0; i < 4; i++) {
    float h = 0.03 + 0.11 * float(i);
    occ += (h - mapD(p + n * h)) * sca;
    sca *= 0.7;
  }
  return clamp(1.0 - 2.3 * occ, 0.0, 1.0);
}

// How much jelly a ray would cross going in: thin edges glow, the core is deep.
float thickness(vec3 p, vec3 n) {
  float t = 0.0;
  vec3 dir = -n;
  float s = 0.0;
  for (int i = 0; i < 6; i++) {
    s += 0.13;
    float d = mapD(p + dir * s);
    t += clamp(-d, 0.0, 0.2) * 1.2;
  }
  return clamp(t, 0.0, 1.0);
}

float hash(vec3 p) {
  p = fract(p * 0.3183099 + 0.1);
  p *= 17.0;
  return fract(p.x * p.y * p.z * (p.x + p.y + p.z));
}

// Distance from a point to a segment, for the face.
float seg(vec2 p, vec2 a, vec2 b) {
  vec2 pa = p - a, ba = b - a;
  float h = clamp(dot(pa, ba) / dot(ba, ba), 0.0, 1.0);
  return length(pa - ba * h);
}

// The painted-on face: ^ _ ^ , with a surprised and a sleepy variation.
float faceMask(vec3 m) {
  if (m.z < 0.15) return 0.0;
  vec2 f = vec2(m.x, m.y);
  float eyeY = 0.66;
  float ex = 0.34;
  float w = 0.056;
  float aa = 0.012;

  float happy = 1e3;
  float open = 1e3;
  float sleep = 1e3;
  for (int i = 0; i < 2; i++) {
    float sx = (i == 0) ? -1.0 : 1.0;
    vec2 c = vec2(sx * ex, eyeY);
    // chevron ^
    happy = min(happy, min(seg(f, c + vec2(-0.165, -0.075), c + vec2(0.0, 0.085)),
                           seg(f, c + vec2(0.0, 0.085), c + vec2(0.165, -0.075))));
    // round open eye
    open = min(open, abs(length((f - c) * vec2(1.0, 0.85)) - 0.02) - 0.0);
    open = min(open, length((f - c) * vec2(1.0, 0.8)) - 0.062);
    // a calm shut line
    sleep = min(sleep, seg(f, c + vec2(-0.11, 0.0), c + vec2(0.11, -0.012)));
  }
  float eyes = mix(happy - w, open, uMood);
  eyes = mix(eyes, sleep - w * 0.9, uSleep);
  // mouth: a little dash, or a tiny o when surprised
  float mouthDash = seg(f, vec2(-0.08, 0.47), vec2(0.08, 0.47)) - 0.04;
  float mouthO = abs(length((f - vec2(0.0, 0.47)) * vec2(1.0, 1.1)) - 0.04) - 0.017;
  float mouth = mix(mouthDash, mouthO, uMood);
  float d = min(eyes, mouth);
  return 1.0 - smoothstep(-aa, aa, d);
}

vec3 envColor(vec3 r) {
  // A warm room: a bright ceiling, a soft window, a dark floor.
  vec3 c = mix(vec3(0.16, 0.09, 0.07), vec3(0.75, 0.62, 0.52), smoothstep(-0.5, 0.9, r.y));
  float win = smoothstep(0.30, 0.08, abs(r.x + 0.42)) * smoothstep(0.55, 0.30, abs(r.y - 0.42));
  c += vec3(1.0, 0.96, 0.9) * win * 1.4;
  float lamp = smoothstep(0.12, 0.02, length(vec2(r.x - 0.6, r.y - 0.8)));
  c += vec3(1.0, 0.8, 0.5) * lamp;
  return c;
}

void main() {
  // Orthographic-ish camera, a touch above the jelly.
  vec2 uv = (vUv - 0.5) * 2.0;
  float ext = 1.5;
  vec3 target = vec3(0.0, 1.0, 0.0);
  float elev = 0.20;
  vec3 fw = normalize(vec3(0.0, -sin(elev), -cos(elev)));
  vec3 rt = vec3(1.0, 0.0, 0.0);
  vec3 up = vec3(0.0, cos(elev), -sin(elev));
  vec3 ro = target - fw * 7.0 + (rt * uv.x + up * uv.y) * ext;
  vec3 rd = fw;

  // Bounding sphere.
  vec3 oc = ro - vec3(0.0, 1.0, 0.0);
  float bR = 2.3;
  float bb = dot(oc, rd);
  float cc = dot(oc, oc) - bR * bR;
  float disc = bb * bb - cc;
  if (disc < 0.0) { gl_FragColor = vec4(0.0); return; }
  float t = -bb - sqrt(disc);
  float tEnd = -bb + sqrt(disc);

  float hitT = -1.0;
  float minD = 1e3;
  float mat = 0.0;
  float minMat = 0.0;
  for (int i = 0; i < 72; i++) {
    vec3 p = ro + rd * t;
    vec2 h = map(p);
    if (h.x < minD) { minD = h.x; minMat = h.y; }
    if (h.x < 0.0012) { hitT = t; mat = h.y; break; }
    t += h.x;
    if (t > tEnd) break;
  }

  if (hitT < 0.0) {
    // Cheap edge anti-aliasing from the closest approach.
    float a = 1.0 - smoothstep(0.0, 0.012, minD);
    if (a <= 0.001) { gl_FragColor = vec4(0.0); return; }
    // A near-miss picks up a rim colour so the silhouette stays clean.
    vec3 rimCol = minMat < 0.5 ? vec3(0.62, 0.03, 0.05) : (minMat < 1.5 ? vec3(0.78, 0.68, 0.56) : vec3(0.07, 0.09, 0.22));
    gl_FragColor = vec4(rimCol * a * 0.8, a * 0.8);
    return;
  }

  vec3 p = ro + rd * hitT;
  vec3 n = calcNormal(p);
  vec3 lp = toModel(p);

  vec3 L1 = normalize(vec3(-0.55, 0.85, 0.75));   // key: a warm lamp up and to the left
  vec3 L2 = normalize(vec3(0.9, 0.25, 0.35));     // fill from the right
  vec3 v = -rd;
  vec3 h1 = normalize(L1 + v);
  float ndl1 = max(dot(n, L1), 0.0);
  float ndl2 = max(dot(n, L2), 0.0);
  float fres = pow(1.0 - max(dot(n, v), 0.0), 3.0);
  float ao = ambientOcc(p, n);
  vec3 refl = reflect(rd, n);
  vec3 col;

  if (mat < 0.5) {
    // ---- jelly ----
    float th = thickness(p, n);
    vec3 deep = vec3(0.50, 0.00, 0.04);
    vec3 body = vec3(0.90, 0.05, 0.06);
    vec3 glow = vec3(1.00, 0.34, 0.20);
    vec3 base = mix(glow, body, smoothstep(0.0, 0.55, th));
    base = mix(base, deep, smoothstep(0.5, 1.0, th) * 0.55);
    // Light travels through it: lit sides glow from within.
    float wrap = 0.5 + 0.5 * dot(n, L1);
    col = base * (0.52 + 0.72 * wrap);
    col += glow * 0.20 * ndl2;
    // Bounce light from the floor colours the underside.
    col += vec3(0.55, 0.16, 0.08) * 0.35 * smoothstep(0.1, -0.7, n.y);
    col *= mix(0.55, 1.0, ao);
    // Glossy surface.
    float spec = pow(max(dot(n, h1), 0.0), 110.0);
    float spec2 = pow(max(dot(n, h1), 0.0), 18.0) * 0.16;
    vec3 env = envColor(refl);
    col += env * (0.10 + 0.55 * fres) * mix(0.6, 1.0, ao);
    col += vec3(1.0, 0.97, 0.92) * spec * 1.25;
    col += vec3(1.0, 0.9, 0.85) * spec2;
    // A rim of light through the edge.
    col += glow * fres * 0.45;

    // The face, drawn on the front of the jelly.
    float fm = faceMask(lp);
    // The face sits in the jelly: dark ink that still catches the gloss.
    vec3 ink = vec3(0.13, 0.05, 0.08);
    col = mix(col, ink, fm * 0.94) + vec3(1.0) * spec * fm * 0.6;
  } else {
    // ---- felt cap ----
    vec3 q = toCap(lp);
    float phi = atan(q.x, q.z);
    vec3 albedo;
    if (mat < 1.5) albedo = vec3(0.93, 0.85, 0.74);      // cream
    else albedo = vec3(0.09, 0.12, 0.30);                // navy
    // Seams between six panels.
    float sm = abs(fract(phi / 1.0472 + 0.5) - 0.5);      // 0 at a seam
    float seam = 1.0 - smoothstep(0.0, 0.045, sm);
    float onCrown = smoothstep(0.35, 0.6, q.y);
    seam *= step(0.0, q.y);
    if (mat > 0.5 && length(q.xz) < 1.05 && q.y > 0.0) albedo *= 1.0 - seam * 0.20;
    // Felt grain.
    float g = hash(floor(lp * 90.0));
    albedo *= 0.965 + g * 0.07;
    float wrap = 0.55 + 0.45 * dot(n, L1);
    col = albedo * (0.40 + 0.72 * wrap);
    col += albedo * 0.16 * ndl2;
    col *= mix(0.45, 1.0, ao);
    // A little velvety sheen at grazing angles.
    col += vec3(0.85, 0.85, 0.95) * pow(1.0 - max(dot(n, v), 0.0), 3.0) * 0.16;
    // The red jelly bounces onto the underside of the brim.
    col += vec3(0.5, 0.05, 0.04) * 0.18 * smoothstep(0.0, -0.8, n.y);
  }

  // Filmic-ish tone and gamma.
  col = col / (1.0 + col * 0.10);
  col = max(col, 0.0);
  gl_FragColor = vec4(col, 1.0);
}
`;

/**
 * Create a Jell-omo renderer. Returns null if WebGL is unavailable.
 * The returned object draws onto its own canvas; use `draw` to blit it.
 */
export function createJelloAvatar(size = 384) {
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const gl = canvas.getContext('webgl', {
    alpha: true,
    premultipliedAlpha: true,
    antialias: false,
    preserveDrawingBuffer: true,
  });
  if (!gl) return null;

  const compile = (type, src) => {
    const s = gl.createShader(type);
    gl.shaderSource(s, src);
    gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
      const log = gl.getShaderInfoLog(s);
      gl.deleteShader(s);
      throw new Error(log || 'shader compile failed');
    }
    return s;
  };

  let prog;
  try {
    prog = gl.createProgram();
    gl.attachShader(prog, compile(gl.VERTEX_SHADER, VERT));
    gl.attachShader(prog, compile(gl.FRAGMENT_SHADER, FRAG));
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(prog));
  } catch (err) {
    console.warn('Jell-omo: 3D shader unavailable, using the flat mascot.', err);
    return null;
  }

  gl.useProgram(prog);
  const buf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
  const loc = gl.getAttribLocation(prog, 'aPos');
  gl.enableVertexAttribArray(loc);
  gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
  gl.viewport(0, 0, size, size);
  gl.clearColor(0, 0, 0, 0);

  const u = {};
  for (const name of ['uTime', 'uYaw', 'uLean', 'uSquash', 'uWob', 'uMood', 'uSleep', 'uCapTilt']) {
    u[name] = gl.getUniformLocation(prog, name);
  }

  let lost = false;
  canvas.addEventListener('webglcontextlost', (e) => { e.preventDefault(); lost = true; });
  canvas.addEventListener('webglcontextrestored', () => { lost = false; });

  return {
    canvas,
    size,
    /** Render one frame of Jell-omo with the given pose. */
    render(pose) {
      if (lost) return false;
      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.uniform1f(u.uTime, pose.time || 0);
      gl.uniform1f(u.uYaw, pose.yaw || 0);
      gl.uniform1f(u.uLean, pose.lean || 0);
      gl.uniform2f(u.uSquash, pose.sx || 1, pose.sy || 1);
      gl.uniform1f(u.uWob, pose.wob || 0);
      gl.uniform1f(u.uMood, pose.mood || 0);
      gl.uniform1f(u.uSleep, pose.sleep || 0);
      gl.uniform1f(u.uCapTilt, pose.capTilt || 0);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      return true;
    },
  };
}
