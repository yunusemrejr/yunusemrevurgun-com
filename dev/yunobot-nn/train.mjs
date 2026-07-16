/**
 * YunoBot custom neural network trainer (offline, Node.js, zero dependencies).
 *
 * Trains a compact fastText-style supervised embedding network on the
 * semantic targets declared in assets/js/yunobot/ml-engine.js and exports
 * int8-quantized weights as assets/js/yunobot/nn-weights.js.
 *
 * Architecture (runs fully in the browser at inference):
 *   tokens -> word ids (vocab) + char-trigram buckets + word-bigram buckets
 *          -> mean of embedding rows (embedding bag) -> L2-normalized vector
 *   Training head: softmax classifier over intents (shapes the embedding
 *   space so intents cluster); inference: cosine similarity vs per-intent
 *   centroid vectors (centroid = mean of all sentence embeddings of an intent).
 *
 * Usage: node dev/yunobot-nn/train.mjs
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const ENGINE_PATH = join(ROOT, 'assets/js/yunobot/ml-engine.js');
const OUT_PATH = join(ROOT, 'assets/js/yunobot/nn-weights.js');

// ============================================================
// 1. Load semantic targets from ml-engine.js (browser-free shim)
// ============================================================
function loadSemanticTargets() {
  const src = readFileSync(ENGINE_PATH, 'utf8');
  const sandbox = {
    window: {},
    console,
    setTimeout: () => 0,
    setInterval: () => 0,
    clearTimeout: () => {},
    clearInterval: () => {},
  };
  sandbox.window = sandbox; // window.FULL_BASE_PATH etc. resolve to undefined-safe
  sandbox.Worker = class { addEventListener() {} postMessage() {} terminate() {} };
  const fn = new Function('window', 'document', 'navigator', 'Worker', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'console', src);
  fn(sandbox, {}, {}, sandbox.Worker, sandbox.setTimeout, sandbox.setInterval, sandbox.clearTimeout, sandbox.clearInterval, console);
  const Engine = sandbox.YunoBotMLEngine;
  if (!Engine) throw new Error('YunoBotMLEngine not exported by ml-engine.js');
  const engine = new Engine();
  return engine.semanticTargets;
}

// ============================================================
// 2. Tokenizer + feature extraction (MUST match browser runtime)
// ============================================================
function tokenize(text) {
  return text
    .toLowerCase()
    .replace(/[^\p{L}\p{N}+#\s]/gu, ' ')
    .split(/\s+/)
    .filter(Boolean);
}

function charTrigrams(word) {
  const w = '<' + word + '>';
  const grams = [];
  for (let i = 0; i <= w.length - 3; i++) grams.push(w.slice(i, i + 3));
  return grams;
}

// FNV-1a 32-bit hash — deterministic across JS engines
function hash32(str) {
  let h = 0x811c9dc5;
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 0x01000193);
  }
  return h >>> 0;
}

// ============================================================
// 4b. Hyperparameters
// ============================================================
const DIM = 64;
const NUM_BUCKETS = 1536;      // shared hash space for trigrams+bigrams
const WORD_WEIGHT = 2;         // vocab word rows count double vs subword rows
const EPOCHS = 80;
const LR0 = 0.08;
const MIN_COUNT = 1;

// ============================================================
// 5. Build dataset
// ============================================================
const targets = loadSemanticTargets();

/** @type {{label:string,type:'navigation'|'qa',intent?:string,target?:string,text:string}[]} */
const samples = [];
const intentMeta = []; // {label,type,target,intent}
const labelToIdx = new Map();

function addIntent(meta) {
  if (!labelToIdx.has(meta.label)) {
    labelToIdx.set(meta.label, intentMeta.length);
    intentMeta.push(meta);
  }
  return labelToIdx.get(meta.label);
}

for (const nav of targets.navigation) {
  const label = 'nav:' + (nav.target === '' ? 'home' : nav.target);
  const idx = addIntent({ label, type: 'navigation', target: nav.target });
  for (const s of nav.sentences) samples.push({ idx, text: s });
}
for (const qa of targets.qa) {
  const label = 'qa:' + qa.intent;
  const idx = addIntent({ label, type: 'qa', intent: qa.intent });
  for (const s of qa.sentences) samples.push({ idx, text: s });
}

// Out-of-scope (OOS) negative class: teaches the softmax head to reject
// generic/off-topic questions so the engine falls through to its fallback
// chain instead of answering confidently wrong.
const OOS_SENTENCES = [
  'what is the capital of france', 'who won the world cup', 'tell me a joke', 'how do i bake bread',
  'what is the weather like today', 'will it rain tomorrow', 'what time does the store open',
  'how do i change a tire', 'what is quantum entanglement', 'explain photosynthesis',
  'who wrote hamlet', 'what is the tallest mountain', 'how far is the moon',
  'what is the stock market doing', 'who is the president', 'what is bitcoin worth',
  'how do i lose weight', 'what is a good recipe for pasta', 'how do i tie a tie',
  'what is the meaning of life', 'write me a poem about the sea', 'sing me a song',
  'what is 25 percent of 80', 'how many days until christmas', 'what year did world war two end',
  'who painted the mona lisa', 'what is the speed of light', 'how do bees make honey',
  'what is the best phone to buy', 'how do i reset my password', 'what is a mortgage',
  'how do i learn to swim', 'what is the population of japan', 'who discovered america',
  'what is the boiling point of water', 'how do i make coffee', 'what is a black hole',
  'who invented the telephone', 'what is the largest ocean', 'how do planes fly',
  'what is the difference between a virus and bacteria', 'how do i start a garden',
  'what is the currency of brazil', 'who sang thriller', 'what is a solar eclipse',
  'how do i fix a leaky faucet', 'what is the deepest lake', 'who built the pyramids',
  'what is the smallest country', 'how do i meditate', 'what is a good book to read',
  'how do i improve my credit score', 'what is the freezing point of alcohol',
  'who was the first person on the moon', 'what is a tsunami', 'how do volcanoes erupt',
  'what is the best way to learn guitar', 'how do i cook rice', 'what is a leap year',
  'who directed titanic', 'what is the longest river', 'how do magnets work',
  'what is the square root of 144', 'how do i get a passport', 'what is a metaphor',
  'who won the last super bowl', 'what is the price of gold', 'how do i start running',
  'what is the capital of australia', 'how do i make pancakes', 'what is a noun',
  'who is the richest person alive', 'what is the fastest animal', 'how do i knit a scarf',
  'what is the ph of water', 'how do i remove stains', 'what is the imperial system',
  'who wrote the declaration of independence', 'what is a lunar eclipse', 'how do fish breathe',
  'what is the best dog breed', 'how do i quit smoking', 'what is an adjective',
  'when is the next olympics', 'what is the distance to mars', 'how do i shave properly',
  'what is the chemical symbol for gold', 'who invented the internet', 'what is a verb',
  'how do i parallel park', 'what is the population of india', 'what is an atom made of',
  'how do i make iced tea', 'what is the currency of japan', 'who wrote romeo and juliet',
];
const oosIdx = addIntent({ label: 'oos', type: 'oos' });
for (const s of OOS_SENTENCES) samples.push({ idx: oosIdx, text: s });

// Vocab from training tokens
const df = new Map();
for (const s of samples) for (const t of new Set(tokenize(s.text))) df.set(t, (df.get(t) || 0) + 1);
const vocab = [...df.entries()].filter(([, c]) => c >= MIN_COUNT).sort((a, b) => b[1] - a[1]).map(([w]) => w);
const wordId = new Map(vocab.map((w, i) => [w, i]));

console.log(`samples=${samples.length} intents=${intentMeta.length} vocab=${vocab.length}`);

// Feature extraction: returns array of {v: vocabId|-1, b: bucket|-1}
function features(text) {
  const toks = tokenize(text);
  const out = [];
  for (let i = 0; i < toks.length; i++) {
    const t = toks[i];
    const id = wordId.get(t);
    if (id !== undefined) out.push({ v: id, b: -1 });
    for (const g of charTrigrams(t)) out.push({ v: -1, b: hash32('t' + g) % NUM_BUCKETS });
    if (i > 0) out.push({ v: -1, b: hash32('w' + toks[i - 1] + ' ' + t) % NUM_BUCKETS });
  }
  return out;
}

// Precompute flat feature rows per sample (duplicates kept — pooling must
// be a weighted mean over ALL occurrences, identical to the browser runtime)
const sampleFeats = samples.map(s => {
  const rows = [];
  let total = 0;
  for (const f of features(s.text)) {
    const isVocab = f.v >= 0;
    const w = isVocab ? WORD_WEIGHT : 1;
    rows.push({ isVocab, id: isVocab ? f.v : f.b, w });
    total += w;
  }
  return { rows, total };
});

// ============================================================
// 5. Train (softmax over mean embedding, plain SGD)
// ============================================================
const V = vocab.length;
const Ev = new Float32Array(V * DIM);           // vocab embeddings
const Eb = new Float32Array(NUM_BUCKETS * DIM); // bucket embeddings
const W = new Float32Array(intentMeta.length * DIM); // classifier
const B = new Float32Array(intentMeta.length);       // bias

// Deterministic PRNG (mulberry32) — reproducible builds
let _rngState = 0x9e3779b9;
function rng() {
  _rngState |= 0; _rngState = (_rngState + 0x6D2B79F5) | 0;
  let t = Math.imul(_rngState ^ (_rngState >>> 15), 1 | _rngState);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
}

function randInit(arr, scale) {
  for (let i = 0; i < arr.length; i++) arr[i] = (rng() * 2 - 1) * scale;
}
randInit(Ev, 0.1);
randInit(Eb, 0.1);
randInit(W, 0.1);

const order = samples.map((_, i) => i);
const h = new Float32Array(DIM);
const grad = new Float32Array(intentMeta.length);
const gEmb = new Float32Array(DIM);

for (let epoch = 0; epoch < EPOCHS; epoch++) {
  // Shuffle (deterministic PRNG)
  for (let i = order.length - 1; i > 0; i--) {
    const j = (rng() * (i + 1)) | 0;
    [order[i], order[j]] = [order[j], order[i]];
  }
  const lr = LR0 * (1 - epoch / EPOCHS) + 0.005;
  let lossSum = 0;
  let correctTrain = 0;

  for (const si of order) {
    const { idx } = samples[si];
    const { rows, total } = sampleFeats[si];
    if (total === 0) continue;

    // h = weighted mean embedding over all feature occurrences
    h.fill(0);
    for (const r of rows) {
      const table = r.isVocab ? Ev : Eb;
      const base = r.id * DIM;
      for (let d = 0; d < DIM; d++) h[d] += table[base + d] * r.w;
    }
    for (let d = 0; d < DIM; d++) h[d] /= total;

    // logits
    let maxLogit = -Infinity;
    for (let c = 0; c < intentMeta.length; c++) {
      let s = B[c];
      for (let d = 0; d < DIM; d++) s += W[c * DIM + d] * h[d];
      grad[c] = s;
      if (s > maxLogit) maxLogit = s;
    }
    // softmax + cross-entropy
    let sumExp = 0;
    for (let c = 0; c < intentMeta.length; c++) {
      grad[c] = Math.exp(grad[c] - maxLogit);
      sumExp += grad[c];
    }
    lossSum += -Math.log(grad[idx] / sumExp + 1e-12);
    let argmax = 0, argmaxP = 0;
    for (let c = 0; c < intentMeta.length; c++) {
      grad[c] = grad[c] / sumExp - (c === idx ? 1 : 0);
      if (grad[c] + (c === idx ? 1 : 0) > argmaxP) { argmaxP = grad[c] + (c === idx ? 1 : 0); argmax = c; }
    }
    if (argmax === idx) correctTrain++;

    // gEmb = dL/dh = sum_c grad[c] * W[c]   (computed BEFORE updating W)
    gEmb.fill(0);
    for (let c = 0; c < intentMeta.length; c++) {
      const g = grad[c];
      if (g === 0) continue;
      for (let d = 0; d < DIM; d++) gEmb[d] += g * W[c * DIM + d];
    }

    // Update W, B
    for (let c = 0; c < intentMeta.length; c++) {
      const g = grad[c];
      if (g === 0) continue;
      for (let d = 0; d < DIM; d++) W[c * DIM + d] -= lr * g * h[d];
      B[c] -= lr * g;
    }

    // Update embedding rows: row -= lr * gEmb * w / total
    for (const r of rows) {
      const table = r.isVocab ? Ev : Eb;
      const base = r.id * DIM;
      for (let d = 0; d < DIM; d++) table[base + d] -= (lr * gEmb[d] * r.w) / total;
    }
  }
  if ((epoch + 1) % 10 === 0) console.log(`epoch ${epoch + 1}/${EPOCHS} loss=${(lossSum / samples.length).toFixed(4)} trainAcc=${(100 * correctTrain / samples.length).toFixed(1)}%`);
}

// ============================================================
// 6. Sentence embedding + per-intent centroids
// ============================================================
function embed(text) {
  const toks = tokenize(text);
  const v = new Float32Array(DIM);
  let n = 0;
  for (let i = 0; i < toks.length; i++) {
    const t = toks[i];
    const rows = [];
    const id = wordId.get(t);
    if (id !== undefined) rows.push({ table: Ev, base: id * DIM, w: WORD_WEIGHT });
    for (const g of charTrigrams(t)) rows.push({ table: Eb, base: (hash32('t' + g) % NUM_BUCKETS) * DIM, w: 1 });
    if (i > 0) rows.push({ table: Eb, base: (hash32('w' + toks[i - 1] + ' ' + t) % NUM_BUCKETS) * DIM, w: 1 });
    for (const r of rows) {
      for (let d = 0; d < DIM; d++) v[d] += r.table[r.base + d] * r.w;
      n += r.w;
    }
  }
  if (n === 0) return v;
  for (let d = 0; d < DIM; d++) v[d] /= n;
  // L2 normalize
  let norm = 0;
  for (let d = 0; d < DIM; d++) norm += v[d] * v[d];
  norm = Math.sqrt(norm) || 1;
  for (let d = 0; d < DIM; d++) v[d] /= norm;
  return v;
}

function cosine(a, b) {
  let s = 0;
  for (let d = 0; d < DIM; d++) s += a[d] * b[d];
  return s; // both L2-normalized
}

// Centroid per intent = normalized mean of its sentence embeddings
const centroids = intentMeta.map(() => new Float32Array(DIM));
const centroidN = new Int32Array(intentMeta.length);
for (const s of samples) {
  const e = embed(s.text);
  for (let d = 0; d < DIM; d++) centroids[s.idx][d] += e[d];
  centroidN[s.idx]++;
}
for (let c = 0; c < centroids.length; c++) {
  let norm = 0;
  for (let d = 0; d < DIM; d++) { centroids[c][d] /= Math.max(1, centroidN[c]); norm += centroids[c][d] ** 2; }
  norm = Math.sqrt(norm) || 1;
  for (let d = 0; d < DIM; d++) centroids[c][d] /= norm;
}

function bestMatch(text) {
  const q = embed(text);
  let best = -1, bestScore = -2;
  for (let c = 0; c < centroids.length; c++) {
    const s = cosine(q, centroids[c]);
    if (s > bestScore) { bestScore = s; best = c; }
  }
  return { best, score: bestScore };
}

// ============================================================
// 7. Evaluation
// ============================================================
let correct = 0;
const scoreBuckets = { hi: 0, mid: 0, lo: 0 };
const misses = [];
for (const s of samples) {
  const { best, score } = bestMatch(s.text);
  if (best === s.idx) correct++;
  else if (misses.length < 15) misses.push({ text: s.text, want: intentMeta[s.idx].label, got: intentMeta[best].label, score: score.toFixed(3) });
  if (score > 0.8) scoreBuckets.hi++; else if (score > 0.6) scoreBuckets.mid++; else scoreBuckets.lo++;
}
console.log(`train intent accuracy: ${(100 * correct / samples.length).toFixed(1)}% (${correct}/${samples.length}) score>0.8:${scoreBuckets.hi} 0.6-0.8:${scoreBuckets.mid} <0.6:${scoreBuckets.lo}`);
if (misses.length) { console.log('sample misses:'); for (const m of misses) console.log('  ', JSON.stringify(m)); }

// Held-out paraphrase probes (never seen in training sentences)
const probes = [
  ['show me his projects please', 'nav:portfolio'],
  ['take me to the gallery page', 'nav:gallery'],
  ['i want to read the blog', 'nav:blog'],
  ['bring me to the contact form', 'nav:contact'],
  ['where can i see his trips', 'nav:travel'],
  ['what is his job', 'qa:what_does_yunus_do'],
  ['tell me about his background', 'qa:who_is_yunus'],
  ['which programming languages does he use', 'qa:yunus_technologies'],
  ['how many countries did he visit', 'qa:yunus_travel'],
  ['when was he born', 'qa:yunus_birth_age'],
  ['is he from turkey', 'qa:yunus_nationality'],
  ['does he have a github account', 'qa:yunus_github'],
  ['what did he study', 'qa:yunus_education'],
  ['are you an llm', 'qa:bot_llm'],
  ['how do you understand me', 'qa:bot_embeddings_explanation'],
  ['can i hire him', 'qa:yunus_availability'],
  ['what kind of music does he like', 'qa:yunus_interests'],
  ['does he know machine learning', 'qa:yunus_skills'],
  ['what can you do for me', 'qa:bot_capabilities'],
  ['is my data sent anywhere', 'qa:bot_privacy'],
  ['does he play guitar', 'qa:yunus_singing'],
  ['how can i reach him', 'qa:yunus_contact_method'],
];
let probeOk = 0;
for (const [q, want] of probes) {
  const { best, score } = bestMatch(q);
  const got = intentMeta[best].label;
  const ok = got === want;
  if (ok) probeOk++;
  console.log(`probe ${ok ? 'OK ' : 'MISS'} score=${score.toFixed(3)} want=${want} got=${got} :: ${q}`);
}
console.log(`probe accuracy: ${probeOk}/${probes.length}`);

// ============================================================
// 7b. Scoring-mode comparison: softmax head vs centroid cosine
//     (open-set rejection decides which ships)
// ============================================================
function softmaxScores(text) {
  const toks = tokenize(text);
  const v = new Float32Array(DIM);
  let n = 0;
  for (let i = 0; i < toks.length; i++) {
    const t = toks[i];
    const id = wordId.get(t);
    const rows = [];
    if (id !== undefined) rows.push({ table: Ev, base: id * DIM, w: WORD_WEIGHT });
    for (const g of charTrigrams(t)) rows.push({ table: Eb, base: (hash32('t' + g) % NUM_BUCKETS) * DIM, w: 1 });
    if (i > 0) rows.push({ table: Eb, base: (hash32('w' + toks[i - 1] + ' ' + t) % NUM_BUCKETS) * DIM, w: 1 });
    for (const r of rows) { for (let d = 0; d < DIM; d++) v[d] += r.table[r.base + d] * r.w; n += r.w; }
  }
  if (n === 0) return { idx: -1, prob: 0 };
  for (let d = 0; d < DIM; d++) v[d] /= n;
  const logits = new Float32Array(intentMeta.length);
  let maxL = -Infinity;
  for (let c = 0; c < intentMeta.length; c++) {
    let s = B[c];
    for (let d = 0; d < DIM; d++) s += W[c * DIM + d] * v[d];
    logits[c] = s;
    if (s > maxL) maxL = s;
  }
  let sum = 0;
  for (let c = 0; c < intentMeta.length; c++) { logits[c] = Math.exp(logits[c] - maxL); sum += logits[c]; }
  let best = 0, bp = 0;
  for (let c = 0; c < intentMeta.length; c++) { const p = logits[c] / sum; if (p > bp) { bp = p; best = c; } }
  return { idx: best, prob: bp };
}

const offTopic = [
  'what is the capital of france',
  'tell me a joke',
  'asdkjh qwerty zzz',
  'how do i bake bread',
  'what is quantum entanglement',
  'who won the world cup',
  'write me a poem about the sea',
  'what is the stock market doing',
];
console.log('\n--- scoring mode comparison ---');
let softProbeOk = 0, centProbeOk = 0;
for (const [q, want] of probes) {
  const s = softmaxScores(q);
  const c = bestMatch(q);
  if (intentMeta[s.idx]?.label === want) softProbeOk++;
  if (intentMeta[c.best]?.label === want) centProbeOk++;
}
console.log(`probes: softmax=${softProbeOk}/${probes.length} centroid=${centProbeOk}/${probes.length}`);
console.log('off-topic confidence (should be LOW):');
for (const q of offTopic) {
  const s = softmaxScores(q);
  const c = bestMatch(q);
  console.log(`  softmax=${s.prob.toFixed(3)} centroid=${c.score.toFixed(3)} (${intentMeta[s.idx]?.label}|${intentMeta[c.best]?.label}) :: ${q}`);
}
console.log('in-scope confidence (should be HIGH):');
for (const [q] of probes.slice(0, 6)) {
  const s = softmaxScores(q);
  const c = bestMatch(q);
  console.log(`  softmax=${s.prob.toFixed(3)} centroid=${c.score.toFixed(3)} :: ${q}`);
}

// ============================================================
// 8. Quantize + export
// ============================================================
function quantizeRows(table, rows) {
  const q = new Int8Array(rows * DIM);
  const scales = new Float32Array(rows);
  for (let r = 0; r < rows; r++) {
    let amax = 0;
    for (let d = 0; d < DIM; d++) amax = Math.max(amax, Math.abs(table[r * DIM + d]));
    const s = amax / 127 || 1;
    scales[r] = s;
    for (let d = 0; d < DIM; d++) q[r * DIM + d] = Math.round(table[r * DIM + d] / s);
  }
  return { q, scales };
}

function b64(buf) {
  return Buffer.from(buf.buffer, buf.byteOffset, buf.byteLength).toString('base64');
}

const qv = quantizeRows(Ev, V);
const qb = quantizeRows(Eb, NUM_BUCKETS);
const centroidFlat = new Float32Array(centroids.length * DIM);
centroids.forEach((c, i) => centroidFlat.set(c, i * DIM));

// Classifier head + centroids are tiny — keep full precision (no drift)
const payload = {
  v: 3,
  dim: DIM,
  buckets: NUM_BUCKETS,
  wordWeight: WORD_WEIGHT,
  vocab,
  evQ: b64(qv.q), evS: b64(qv.scales),
  ebQ: b64(qb.q), ebS: b64(qb.scales),
  intents: intentMeta.map(({ label, type, target, intent }) => ({ label, type, ...(target !== undefined ? { target } : {}), ...(intent ? { intent } : {}) })),
  cF: b64(centroidFlat),
  wF: b64(W),
  bW: b64(new Float32Array(B)),
};

const out = `/**
 * YunoBot custom neural network weights (auto-generated — DO NOT EDIT BY HAND).
 * Generated by dev/yunobot-nn/train.mjs on ${new Date().toISOString()}.
 * fastText-style supervised embedding network, int8-quantized.
 */
window.YUNOBOT_NN_WEIGHTS = ${JSON.stringify(payload)};
`;
writeFileSync(OUT_PATH, out);
console.log(`wrote ${OUT_PATH} (${(out.length / 1024).toFixed(1)} KB)`);

// Quantized-roundtrip sanity: accuracy with dequantized tables
function dequant(q, s, rows) {
  const t = new Float32Array(rows * DIM);
  for (let r = 0; r < rows; r++) for (let d = 0; d < DIM; d++) t[r * DIM + d] = q[r * DIM + d] * s[r];
  return t;
}
const Ev2 = dequant(qv.q, qv.scales, V);
const Eb2 = dequant(qb.q, qb.scales, NUM_BUCKETS);
function embed2(text) {
  const feats = features(text);
  const v = new Float32Array(DIM);
  if (!feats.length) return v;
  let n = 0;
  for (const f of feats) {
    const w = f.v >= 0 ? WORD_WEIGHT : 1;
    const table = f.v >= 0 ? Ev2 : Eb2;
    const base = (f.v >= 0 ? f.v : f.b) * DIM;
    for (let d = 0; d < DIM; d++) v[d] += table[base + d] * w;
    n += w;
  }
  for (let d = 0; d < DIM; d++) v[d] /= n;
  let norm = 0; for (let d = 0; d < DIM; d++) norm += v[d] * v[d];
  norm = Math.sqrt(norm) || 1;
  for (let d = 0; d < DIM; d++) v[d] /= norm;
  return v;
}
let correct2 = 0;
for (const s of samples) {
  const q = embed2(s.text);
  let best = -1, bs = -2;
  for (let c = 0; c < centroids.length; c++) {
    let sc = 0;
    for (let d = 0; d < DIM; d++) sc += q[d] * centroidFlat[c * DIM + d];
    if (sc > bs) { bs = sc; best = c; }
  }
  if (best === s.idx) correct2++;
}
console.log(`quantized train accuracy: ${(100 * correct2 / samples.length).toFixed(1)}%`);

// DEBUG: softmax using DEQUANTIZED Ev/Eb (exact runtime conditions)
function softmaxDequant(text) {
  const toks = tokenize(text);
  const v = new Float32Array(DIM);
  let n = 0;
  for (let i = 0; i < toks.length; i++) {
    const t = toks[i];
    const id = wordId.get(t);
    if (id !== undefined) { for (let d = 0; d < DIM; d++) v[d] += Ev2[id * DIM + d] * WORD_WEIGHT; n += WORD_WEIGHT; }
    for (const g of charTrigrams(t)) { const b = (hash32('t' + g) % NUM_BUCKETS) * DIM; for (let d = 0; d < DIM; d++) v[d] += Eb2[b + d]; n++; }
    if (i > 0) { const b = (hash32('w' + toks[i - 1] + ' ' + t) % NUM_BUCKETS) * DIM; for (let d = 0; d < DIM; d++) v[d] += Eb2[b + d]; n++; }
  }
  if (!n) return { idx: -1, prob: 0 };
  for (let d = 0; d < DIM; d++) v[d] /= n;
  const logits = new Float32Array(intentMeta.length);
  let mx = -Infinity;
  for (let c = 0; c < intentMeta.length; c++) { let s = B[c]; for (let d = 0; d < DIM; d++) s += W[c * DIM + d] * v[d]; logits[c] = s; if (s > mx) mx = s; }
  let sum = 0; for (let c = 0; c < intentMeta.length; c++) { logits[c] = Math.exp(logits[c] - mx); sum += logits[c]; }
  let bi = 0, bp = 0;
  for (let c = 0; c < intentMeta.length; c++) { const p = logits[c] / sum; if (p > bp) { bp = p; bi = c; } }
  return { idx: bi, prob: bp };
}
console.log('dequantized softmax on off-topic:');
for (const q of offTopic.slice(0, 4)) {
  const r = softmaxDequant(q);
  console.log(`  p=${r.prob.toFixed(3)} top=${intentMeta[r.idx]?.label} :: ${q}`);
}
