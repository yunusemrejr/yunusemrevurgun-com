#!/usr/bin/env node
/*
 * Trains the Jello Shop cat's brain (assets/jelloshop/js/catbrain.js).
 *
 *   node dev/scripts/train-cat-brain.mjs [generations] [--eval]
 *
 * Evolution strategies (antithetic sampling, centred-rank fitness, Adam) over
 * the 1,194 weights of a 12-unit GRU. Each candidate lives several simulated
 * three-minute stretches of the café using the game's own CatMind (the same
 * drives, body and floor), and is scored on how well it looks after itself:
 *
 *   + sleeping on its cushion, a little; sitting up when poked; keeping
 *     Jell-omo company when it is rested
 *   - being worn out, being restless, oversleeping, sleeping away from the
 *     cushion, wandering far, flickering between actions
 *
 * Writes assets/jelloshop/js/catweights.js. With --eval it only reports how the
 * current weights behave.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CatMind, ACT, HOME, N_PARAMS, STEP, mulberry32, gauss, clampFloor } from '../../assets/jelloshop/js/catbrain.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const OUT_FILE = path.resolve(here, '../../assets/jelloshop/js/catweights.js');

const GENS = Number(process.argv[2]) > 0 ? Number(process.argv[2]) : 260;
const EVAL_ONLY = process.argv.includes('--eval');
const POP = 64; // even: antithetic pairs
const SIGMA0 = 0.09;
const SIGMA1 = 0.04; // mutation size anneals from SIGMA0 to SIGMA1
const LR = 0.035;
const EPISODES = 6;
const DECISIONS = 480; // 240 simulated seconds
const TICK = 0.1;

// ----------------------------------------------------------------- simulator
function episode(w, seed, collect) {
  const rng = mulberry32(seed);
  const mind = new CatMind(w, rng);
  mind.energy = 0.3 + rng() * 0.7;
  mind.restless = rng() * 0.6;
  // Half the episodes start awake somewhere on the floor.
  if (rng() < 0.5) {
    mind.act = rng() < 0.5 ? ACT.SIT : ACT.WALK;
    mind.x = 200 + rng() * 600;
    mind.y = 350 + rng() * 200;
    clampFloor(mind);
  }
  const j = { x: 400, y: 450, tx: 400, ty: 450, wait: 0 };
  let total = 0;
  const stats = { acts: [0, 0, 0, 0], sleepHome: 0, sleeps: 0, walkBouts: 0, switches: 0, walkLens: [], maxFar: 0, trace: [] };
  let prevAct = mind.act;
  let bout = 0;
  for (let d = 0; d < DECISIONS; d++) {
    // Jell-omo ambles about: walks to a spot, waits, repeats.
    for (let t = 0; t < STEP; t += TICK) {
      const dx = j.tx - j.x;
      const dy = j.ty - j.y;
      const dist = Math.hypot(dx, dy);
      if (dist > 4) {
        j.x += (dx / dist) * Math.min(150 * TICK, dist);
        j.y += (dy / dist) * Math.min(100 * TICK, dist);
      } else if ((j.wait -= TICK) <= 0) {
        j.tx = 120 + rng() * 720;
        j.ty = 350 + rng() * 200;
        j.wait = 1 + rng() * 14;
      }
      mind.advance(TICK, { jx: j.x, jy: j.y, jmoving: dist > 8 });
    }
    // Now and then someone pokes the cat.
    if (rng() < 0.006) mind.poke();

    const { energy: e, restless: r, act } = mind;
    const far = Math.hypot(mind.x - HOME.x, (mind.y - HOME.y) * 1.6);
    const jn = Math.max(0, 1 - Math.hypot(j.x - mind.x, (j.y - mind.y) * 1.5) / 300);
    let R = 0;
    R -= 1.5 * Math.max(0, 0.25 - e);
    R -= 0.6 * r * r;
    if (act === ACT.SLEEP) {
      R += mind.atHome ? 0.12 : -0.35;
      if (e > 0.95) R -= 0.09;
    }
    if (act === ACT.WALK) {
      R -= 0.03;
      if (r < 0.1) R -= 0.08; // no walking about for the sake of it
      if (e < 0.12) R -= 0.4;
      if (far > 380) R -= 0.15;
    }
    // Short bouts of sitting up and grooming are nice; a long one is a rut.
    if ((act === ACT.GROOM || act === ACT.SIT) && e > 0.5 && r > 0.15) R += mind.dwell < 6 ? 0.04 : -0.06;
    // Getting up needs a reason: restlessness, or someone poking it.
    if (prevAct === ACT.SLEEP && act !== ACT.SLEEP && mind.poked < 0.3 && r < 0.25) R -= 0.5;
    if (mind.switched) R -= 0.22;
    if (mind.poked > 0.4 && act !== ACT.SLEEP) R += 0.25;
    if (jn > 0.5 && act !== ACT.SLEEP && e > 0.4) R += 0.06;
    total += R;

    if (collect) {
      stats.acts[act]++;
      if (mind.switched) stats.switches++;
      if (act === ACT.WALK) bout++;
      if (prevAct === ACT.WALK && act !== ACT.WALK) { stats.walkBouts++; stats.walkLens.push(bout * STEP); bout = 0; }
      if (act === ACT.SLEEP && prevAct !== ACT.SLEEP) { stats.sleeps++; if (mind.atHome) stats.sleepHome++; }
      stats.maxFar = Math.max(stats.maxFar, far);
      if (d % 4 === 0) stats.trace.push(['S', 'i', 'W', 'g'][act]);
    }
    prevAct = act;
  }
  return { total: total / DECISIONS, stats };
}

function fitness(w, gen) {
  let s = 0;
  for (let k = 0; k < EPISODES; k++) s += episode(w, gen * 101 + k * 7919 + 13).total;
  return s / EPISODES;
}

// ------------------------------------------------------------------ weights
function initWeights(seed) {
  const rng = mulberry32(seed);
  const w = new Float32Array(N_PARAMS);
  for (let i = 0; i < N_PARAMS; i++) w[i] = gauss(rng) * 0.25;
  return w;
}

function load() {
  try {
    const src = fs.readFileSync(OUT_FILE, 'utf8');
    const m = src.match(/CAT_WEIGHTS = '([^']+)'/);
    if (!m) return null;
    const buf = Buffer.from(m[1], 'base64');
    return new Float32Array(buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.length));
  } catch {
    return null;
  }
}

function save(w, note) {
  const buf = Buffer.from(w.buffer, w.byteOffset, w.byteLength);
  const src = `/* Trained by dev/scripts/train-cat-brain.mjs. ${note}\n   ${N_PARAMS} float32 weights for the GRU in catbrain.js, little-endian, base64. */\nexport const CAT_WEIGHTS = '${buf.toString('base64')}';\n`;
  fs.writeFileSync(OUT_FILE, src);
}

function report(w, label) {
  const eps = [];
  for (let s = 0; s < 6; s++) eps.push(episode(w, 900000 + s * 31, true));
  const tot = eps.reduce((a, e) => a + e.stats.acts.reduce((x, y) => x + y, 0), 0);
  const frac = [0, 1, 2, 3].map((i) => eps.reduce((a, e) => a + e.stats.acts[i], 0) / tot);
  const bouts = eps.reduce((a, e) => a + e.stats.walkBouts, 0);
  const lens = eps.flatMap((e) => e.stats.walkLens);
  const sleeps = eps.reduce((a, e) => a + e.stats.sleeps, 0);
  const home = eps.reduce((a, e) => a + e.stats.sleepHome, 0);
  const sw = eps.reduce((a, e) => a + e.stats.switches, 0);
  const mean = (a) => (a.length ? a.reduce((x, y) => x + y, 0) / a.length : 0);
  console.log(`\n${label}`);
  console.log(`  reward/decision  ${(eps.reduce((a, e) => a + e.total, 0) / eps.length).toFixed(3)}`);
  console.log(`  time asleep ${(frac[0] * 100).toFixed(0)}%  sitting ${(frac[1] * 100).toFixed(0)}%  walking ${(frac[2] * 100).toFixed(0)}%  grooming ${(frac[3] * 100).toFixed(0)}%`);
  console.log(`  walks per 4 min ${(bouts / eps.length).toFixed(1)}  mean walk ${mean(lens).toFixed(1)}s  longest ${Math.max(0, ...lens).toFixed(1)}s`);
  console.log(`  sleeps begun on the cushion ${home}/${sleeps}   switches per 4 min ${(sw / eps.length).toFixed(0)}`);
  console.log('  ' + eps[0].stats.trace.join('') + '   (S sleep, i sit, W walk, g groom; 2s per glyph)');
  console.log('  ' + eps[1].stats.trace.join(''));
}

// ------------------------------------------------------------------ training
if (EVAL_ONLY) {
  const w = load();
  if (!w) { console.error('no weights to evaluate'); process.exit(1); }
  report(w, 'current weights');
  process.exit(0);
}

let theta = load() || initWeights(1);
const m = new Float64Array(N_PARAMS);
const v = new Float64Array(N_PARAMS);
let best = { f: -Infinity, w: theta.slice() };
const t0 = Date.now();
const rng = mulberry32(2024);

for (let g = 1; g <= GENS; g++) {
  const SIGMA = SIGMA0 + (SIGMA1 - SIGMA0) * ((g - 1) / Math.max(1, GENS - 1));
  const eps = [];
  const fits = [];
  for (let i = 0; i < POP / 2; i++) {
    const e = new Float32Array(N_PARAMS);
    for (let k = 0; k < N_PARAMS; k++) e[k] = gauss(rng);
    for (const sign of [1, -1]) {
      const cand = new Float32Array(N_PARAMS);
      for (let k = 0; k < N_PARAMS; k++) cand[k] = theta[k] + sign * SIGMA * e[k];
      fits.push(fitness(cand, g));
      eps.push(sign === 1 ? e : e.map((x) => -x));
    }
  }
  // Centred ranks.
  const order = fits.map((f, i) => [f, i]).sort((a, b) => a[0] - b[0]);
  const rank = new Float64Array(POP);
  order.forEach(([, i], r) => { rank[i] = r / (POP - 1) - 0.5; });
  const grad = new Float64Array(N_PARAMS);
  for (let i = 0; i < POP; i++) for (let k = 0; k < N_PARAMS; k++) grad[k] += rank[i] * eps[i][k];
  for (let k = 0; k < N_PARAMS; k++) {
    const gk = grad[k] / (POP * SIGMA) - 0.002 * theta[k]; // a little weight decay
    m[k] = 0.9 * m[k] + 0.1 * gk;
    v[k] = 0.999 * v[k] + 0.001 * gk * gk;
    const mh = m[k] / (1 - 0.9 ** g);
    const vh = v[k] / (1 - 0.999 ** g);
    theta[k] += (LR * mh) / (Math.sqrt(vh) + 1e-8);
  }
  const cur = fitness(theta, g + 5000) * 0.5 + fitness(theta, g + 9000) * 0.5;
  if (cur > best.f) best = { f: cur, w: theta.slice() };
  if (g % 10 === 0 || g === 1) {
    const meanF = fits.reduce((a, b) => a + b, 0) / POP;
    console.log(`gen ${String(g).padStart(3)}  pop mean ${meanF.toFixed(3)}  theta ${cur.toFixed(3)}  best ${best.f.toFixed(3)}  ${((Date.now() - t0) / 1000).toFixed(0)}s`);
  }
  if (g % 40 === 0) save(best.w, `Generation ${g}, reward ${best.f.toFixed(3)}.`);
}

save(best.w, `${GENS} generations of evolution strategies, reward ${best.f.toFixed(3)} per decision.`);
report(best.w, 'trained weights');
console.log(`\nwrote ${path.relative(process.cwd(), OUT_FILE)}`);
