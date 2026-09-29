/* =============================================================================
   The cat's mind.
   -----------------------------------------------------------------------------
   A very small recurrent neural network (a 12-unit GRU, 1,194 weights, about
   5 KB) decides what the cat does. Twice a second it reads a short description
   of how things are, updates its memory, and picks one of four things to do
   (sleep, sit, walk, groom) plus a direction to walk in.

   What it sees: how rested it is, how restless it is, where it is, where its
   cushion is, where Jell-omo is and whether he is moving, what it is doing
   now and for how long, whether it was just poked, and a little noise. What
   it remembers is its hidden state, so its choices depend on its recent past
   and not only on the moment.

   Nobody wrote a schedule. The weights were trained offline by evolution
   strategies in a simulator that runs this same file (dev/scripts/
   train-cat-brain.mjs), rewarded for keeping itself rested and comfortable:
   sleep on the cushion, get up when restless, stretch its legs, come home when
   tired. Actions are sampled, not picked, and the state carries on from one
   decision to the next, so it never plays the same day twice.

   This file has no DOM in it, so the trainer imports it under Node.
   ========================================================================== */

export const IN = 18;
export const H = 12;
export const OUT = 6;
export const ACT = { SLEEP: 0, SIT: 1, WALK: 2, GROOM: 3 };
export const STEP = 0.5; // seconds between decisions

const oWz = 0;
const oUz = oWz + H * IN;
const obz = oUz + H * H;
const oWr = obz + H;
const oUr = oWr + H * IN;
const obr = oUr + H * H;
const oWn = obr + H;
const oUn = oWn + H * IN;
const obn = oUn + H * H;
const oWo = obn + H;
const obo = oWo + OUT * H;
export const N_PARAMS = obo + OUT;

const sig = (x) => 1 / (1 + Math.exp(-x));

/** A GRU cell and a linear read-out. Weights live in one flat Float32Array. */
export class CatBrain {
  constructor(w) {
    if (w.length !== N_PARAMS) throw new Error(`cat brain wants ${N_PARAMS} weights, got ${w.length}`);
    this.w = w;
    this.h = new Float32Array(H);
    this.z = new Float32Array(H);
    this.r = new Float32Array(H);
    this.out = new Float32Array(OUT);
  }

  reset() {
    this.h.fill(0);
  }

  step(x) {
    const { w, h, out } = this;
    const nh = new Float32Array(H);
    for (let j = 0; j < H; j++) {
      let az = w[obz + j];
      let ar = w[obr + j];
      let an = w[obn + j];
      const wi = j * IN;
      for (let i = 0; i < IN; i++) {
        const xi = x[i];
        az += w[oWz + wi + i] * xi;
        ar += w[oWr + wi + i] * xi;
        an += w[oWn + wi + i] * xi;
      }
      const uj = j * H;
      let un = 0;
      for (let k = 0; k < H; k++) {
        const hk = h[k];
        az += w[oUz + uj + k] * hk;
        ar += w[oUr + uj + k] * hk;
        un += w[oUn + uj + k] * hk;
      }
      const z = sig(az);
      const r = sig(ar);
      const n = Math.tanh(an + r * un);
      nh[j] = (1 - z) * n + z * h[j];
    }
    h.set(nh);
    for (let i = 0; i < OUT; i++) {
      let a = w[obo + i];
      const oi = i * H;
      for (let j = 0; j < H; j++) a += w[oWo + oi + j] * h[j];
      out[i] = a;
    }
    return out;
  }
}

/** Small seeded generator so the trainer's episodes can be replayed. */
export function mulberry32(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export function gauss(rng) {
  const u = Math.max(rng(), 1e-9);
  return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * rng());
}

export function decodeWeights(b64) {
  const bin = atob(b64);
  const view = new DataView(new ArrayBuffer(bin.length));
  for (let i = 0; i < bin.length; i++) view.setUint8(i, bin.charCodeAt(i));
  const w = new Float32Array(bin.length / 4);
  for (let i = 0; i < w.length; i++) w[i] = view.getFloat32(i * 4, true);
  return w;
}

// --------------------------------------------------------------------- the body
// Floor geometry, in the room's design pixels (the same numbers as room.js).
export const HOME = { x: 752, y: 338 };
const MIN_Y = 336;
const MAX_Y = 566;
const leftX = (y) => 130 - ((y - 292) / 308) * 160;
const rightX = (y) => 830 + ((y - 292) / 308) * 160;
// Furniture the cat walks round: centre and half-size of each footprint.
const OBSTACLES = [
  [322, 492, 66, 18], // table
  [136, 414, 64, 18], // armchairs
  [828, 404, 64, 18],
  [190, 334, 24, 9], // plants
  [60, 560, 22, 8],
  [905, 566, 26, 9],
];

const WALK_X = 42; // design px per second, sideways
const WALK_Y = 27; // slower in depth, as for Jell-omo
const MIN_DWELL = [20, 4, 4, 5]; // seconds an action is held before it can change

const clamp = (v, a, b) => (v < a ? a : v > b ? b : v);

export function clampFloor(p) {
  p.y = clamp(p.y, MIN_Y, MAX_Y);
  const pad = 30;
  // Home is allowed to be a little further back than the rest of the floor.
  p.x = clamp(p.x, leftX(p.y) + pad, rightX(p.y) - pad);
  for (const [cx, cy, rx, ry] of OBSTACLES) {
    const dx = (p.x - cx) / rx;
    const dy = (p.y - cy) / ry;
    const d = Math.hypot(dx, dy);
    if (d < 1) {
      if (d < 1e-4) { p.x = cx + rx; continue; }
      p.x = cx + (dx / d) * rx;
      p.y = cy + (dy / d) * ry;
    }
  }
  return p;
}

/**
 * The cat: drives, body and brain in one object, advanced by `advance(dt, world)`
 * whether that is a game frame or a simulator tick.
 *
 * world = { jx, jy, jmoving }: where Jell-omo is (design px).
 */
export class CatMind {
  constructor(weights, rng = Math.random) {
    this.brain = new CatBrain(weights);
    this.rng = rng;
    this.temp = 1;
    this.reset();
  }

  reset() {
    this.brain.reset();
    this.x = HOME.x;
    this.y = HOME.y;
    this.act = ACT.SLEEP;
    this.energy = 0.7;
    this.restless = 0.2;
    this.dwell = 0;
    this.acc = 0;
    this.gait = 0;
    this.hx = 0;
    this.hy = 0;
    this.pace = 0;
    this.face = -1; // -1 looks left, 1 looks right
    this.poked = 0;
    this.vx = 0;
    this.vy = 0;
    this.switched = false;
    this.input = new Float32Array(IN);
  }

  get atHome() {
    return Math.hypot(this.x - HOME.x, (this.y - HOME.y) * 1.6) < 14;
  }

  poke() {
    this.poked = 1;
  }

  /** Move time on by dt seconds. Returns true if the brain made a decision. */
  advance(dt, world) {
    this.dwell += dt;
    this._drives(dt);
    this._body(dt);
    this.acc += dt;
    if (this.acc >= STEP) {
      this.acc -= STEP;
      this._think(world);
      return true;
    }
    return false;
  }

  _drives(dt) {
    let e = this.energy;
    let r = this.restless;
    switch (this.act) {
      case ACT.SLEEP: e += (this.atHome ? 0.01 : 0.006) * dt; r += 0.0016 * dt; break;
      case ACT.SIT: e -= 0.002 * dt; r += 0.004 * dt; break;
      case ACT.GROOM: e -= 0.002 * dt; r -= 0.002 * dt; break;
      default: e -= 0.01 * dt * this.gait; r -= 0.025 * dt * this.gait; break;
    }
    this.energy = clamp(e, 0, 1);
    this.restless = clamp(r, 0, 1);
  }

  _body(dt) {
    const walking = this.act === ACT.WALK;
    this.gait += ((walking ? this.pace : 0) - this.gait) * Math.min(1, dt * 4);
    const prevX = this.x;
    const prevY = this.y;
    if (walking || this.gait > 0.02) {
      const m = Math.hypot(this.hx, this.hy) || 1;
      this.x += (this.hx / m) * WALK_X * this.gait * dt;
      this.y += (this.hy / m) * WALK_Y * this.gait * dt;
    } else if (!walking) {
      // Close to the cushion and not walking: settle onto it.
      const dx = HOME.x - this.x;
      const dy = HOME.y - this.y;
      const d = Math.hypot(dx, dy * 1.6);
      if (d < 34 && d > 0.5) {
        const k = Math.min(1, (60 * dt) / d);
        this.x += dx * k;
        this.y += dy * k;
      }
    }
    clampFloor(this);
    if (dt > 0) {
      this.vx = (this.x - prevX) / dt;
      this.vy = (this.y - prevY) / dt;
    }
    if (Math.abs(this.vx) > 1.5) this.face = this.vx > 0 ? 1 : -1;
  }

  _think(world) {
    const x = this.input;
    x[0] = this.energy;
    x[1] = this.restless;
    x[2] = this.atHome ? 1 : 0;
    x[3] = clamp((HOME.x - this.x) / 300, -1, 1);
    x[4] = clamp((HOME.y - this.y) / 110, -1, 1);
    x[5] = (this.x - 480) / 480;
    x[6] = (this.y - 450) / 120;
    const jdx = world.jx - this.x;
    const jdy = world.jy - this.y;
    x[7] = clamp(1 - Math.hypot(jdx, jdy * 1.5) / 300, 0, 1);
    x[8] = clamp(jdx / 300, -1, 1);
    x[9] = clamp(jdy / 110, -1, 1);
    x[10] = world.jmoving ? 1 : 0;
    for (let i = 0; i < 4; i++) x[11 + i] = this.act === i ? 1 : 0;
    x[15] = Math.min(this.dwell / 30, 1);
    x[16] = this.poked;
    x[17] = gauss(this.rng) * 0.4;
    this.poked *= 0.5;
    if (this.poked < 0.05) this.poked = 0;

    const o = this.brain.step(x);
    // Sample an action from the logits.
    let mx = -1e9;
    for (let i = 0; i < 4; i++) mx = Math.max(mx, o[i]);
    let sum = 0;
    const p = [0, 0, 0, 0];
    for (let i = 0; i < 4; i++) {
      p[i] = Math.exp((o[i] - mx) / this.temp);
      sum += p[i];
    }
    let u = this.rng() * sum;
    let pick = 3;
    for (let i = 0; i < 4; i++) {
      u -= p[i];
      if (u <= 0) { pick = i; break; }
    }
    this.switched = false;
    if (pick !== this.act && this.dwell >= MIN_DWELL[this.act]) {
      this.act = pick;
      this.dwell = 0;
      this.switched = true;
    }
    // Where to walk, and how briskly: the direction is the read-out's, the
    // pace is how long that vector is.
    const tx = Math.tanh(o[4]);
    const ty = Math.tanh(o[5]);
    this.hx += (tx - this.hx) * 0.6;
    this.hy += (ty - this.hy) * 0.6;
    this.pace = Math.min(1, Math.hypot(this.hx, this.hy) / 0.6);
  }
}
