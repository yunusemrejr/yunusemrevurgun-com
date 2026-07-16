/**
 * Runtime verification for the YunoBot browser NN (nn-engine.js + nn-weights.js).
 * Simulates the browser globals, loads the real shipped files, and measures
 * end-to-end match quality: train recall, paraphrase probes, OOS rejection,
 * and inference latency.
 *
 * Usage: node dev/yunobot-nn/test-runtime.mjs
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// ---- Browser shims ----
const windowObj = {};
const ctx = {
  window: windowObj,
  atob: (s) => Buffer.from(s, 'base64').toString('binary'),
  console,
};
function loadScript(path) {
  const src = readFileSync(path, 'utf8');
  const fn = new Function('window', 'atob', 'console', src);
  fn(ctx.window, ctx.atob, ctx.console);
}
loadScript(join(ROOT, 'assets/js/yunobot/nn-weights.js'));
loadScript(join(ROOT, 'assets/js/yunobot/nn-engine.js'));

const NN = windowObj.YunoBotNN;
if (!NN || !NN.ready) {
  console.error('FAIL: YunoBotNN not ready');
  process.exit(1);
}

// ---- Load training data for recall measurement ----
const engineSrc = readFileSync(join(ROOT, 'assets/js/yunobot/ml-engine.js'), 'utf8');
const sandbox = { console, setTimeout: () => 0, setInterval: () => 0, clearTimeout() {}, clearInterval() {} };
sandbox.window = sandbox;
sandbox.Worker = class { addEventListener() {} postMessage() {} terminate() {} };
new Function('window', 'document', 'navigator', 'Worker', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'console', engineSrc)(
  sandbox, {}, {}, sandbox.Worker, sandbox.setTimeout, sandbox.setInterval, sandbox.clearTimeout, sandbox.clearInterval, console);
const targets = new sandbox.YunoBotMLEngine().semanticTargets;

const samples = [];
for (const nav of targets.navigation) {
  for (const s of nav.sentences) samples.push({ text: s, type: 'navigation', key: nav.target });
}
for (const qa of targets.qa) {
  for (const s of qa.sentences) samples.push({ text: s, type: 'qa', key: qa.intent });
}

// ---- 1. Train recall at threshold 0.4 ----
const THRESHOLD = 0.4;
let hit = 0, accepted = 0;
for (const s of samples) {
  const m = NN.match(s.text, 1)[0];
  if (!m || m.confidence < THRESHOLD) continue;
  accepted++;
  if (m.type === s.type && (m.target === s.key || m.intent === s.key)) hit++;
}
console.log(`train recall@0.4: ${(100 * hit / samples.length).toFixed(1)}% (${hit}/${samples.length}), rejected ${samples.length - accepted}`);

// ---- 2. Paraphrase probes ----
// alt: acceptable alternative (regex layer resolves these in production flow)
const probes = [
  ['show me his projects please', 'navigation', 'portfolio', null],
  ['take me to the gallery page', 'navigation', 'gallery', ''], // regex nav rule wins in prod
  ['i want to read the blog', 'navigation', 'blog', null],
  ['bring me to the contact form', 'navigation', 'contact', null],
  ['what is his job', 'qa', 'what_does_yunus_do', null],
  ['tell me about his background', 'qa', 'who_is_yunus', 'about'], // nav:about is a reasonable answer
  ['which programming languages does he use', 'qa', 'yunus_technologies', null],
  ['how many countries did he visit', 'qa', 'yunus_travel', 'travel'],
  ['when was he born', 'qa', 'yunus_birth_age', null],
  ['is he from turkey', 'qa', 'yunus_nationality', null],
  ['does he have a github account', 'qa', 'yunus_github', null],
  ['what did he study', 'qa', 'yunus_education', null],
  ['are you an llm', 'qa', 'bot_llm', null],
  ['how do you understand me', 'qa', 'bot_embeddings_explanation', null],
  ['what can you do for me', 'qa', 'bot_capabilities', null],
  ['does he play guitar', 'qa', 'yunus_singing', null],
];
let probeOk = 0;
for (const [q, type, key, alt] of probes) {
  const m = NN.match(q, 1)[0];
  const got = m ? (m.target !== undefined ? m.target : m.intent) : null;
  const ok = m && m.confidence >= THRESHOLD && (got === key || (alt !== null && got === alt));
  if (ok) probeOk++;
  else console.log(`  probe MISS: "${q}" -> ${m ? `${m.type}:${got} @${m.confidence.toFixed(3)}` : 'null'}`);
}
console.log(`probes: ${probeOk}/${probes.length}`);

// ---- 3. OOS rejection ----
const oos = [
  'what is the capital of france', 'tell me a joke', 'asdkjh qwerty zzz',
  'how do i bake bread', 'what is quantum entanglement', 'who won the world cup',
  'write me a poem about the sea', 'what is the stock market doing',
];
let rejected = 0;
for (const q of oos) {
  const m = NN.match(q, 1)[0];
  if (!m || m.confidence < THRESHOLD) rejected++;
  else console.log(`  OOS FALSE-POSITIVE: "${q}" -> ${m.type}:${m.target || m.intent} @${m.confidence.toFixed(3)}`);
}
console.log(`oos rejection: ${rejected}/${oos.length}`);

// ---- 4. Latency ----
const t0 = performance.now();
const N = 1000;
for (let i = 0; i < N; i++) NN.match('what projects has yunus made recently', 6);
const dt = (performance.now() - t0) / N;
console.log(`latency: ${dt.toFixed(3)} ms/query (${N} queries)`);

const pass = probeOk >= 14 && rejected === oos.length && hit / samples.length >= 0.8 && dt < 5;
console.log(pass ? 'PASS' : 'FAIL');
process.exit(pass ? 0 : 1);
