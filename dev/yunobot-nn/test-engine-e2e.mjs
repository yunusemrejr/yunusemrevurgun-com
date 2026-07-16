/**
 * End-to-end behavioral test: ml-engine.js + nn-engine.js + nn-weights.js
 * wired exactly as in the browser. Exercises navigation, Q&A, tools,
 * Turkish input, OOS rejection, follow-ups, and latency.
 *
 * Usage: node dev/yunobot-nn/test-engine-e2e.mjs
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// ---- Browser shims ----
const windowObj = {};
windowObj.window = windowObj;
windowObj.FULL_BASE_PATH = '/';
const timeouts = [];
const shim = {
  window: windowObj,
  document: {},
  navigator: {},
  Worker: class { addEventListener() {} postMessage() {} terminate() {} },
  setTimeout: (fn) => { timeouts.push(fn); return 0; },
  setInterval: () => 0,
  clearTimeout() {}, clearInterval() {},
  atob: (s) => Buffer.from(s, 'base64').toString('binary'),
  console,
};
function load(path) {
  const src = readFileSync(join(ROOT, path), 'utf8');
  new Function('window', 'document', 'navigator', 'Worker', 'setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'atob', 'console', src)(
    shim.window, shim.document, shim.navigator, shim.Worker, shim.setTimeout, shim.setInterval, shim.clearTimeout, shim.clearInterval, shim.atob, shim.console);
}
load('assets/js/yunobot/nn-weights.js');
load('assets/js/yunobot/nn-engine.js');
load('assets/js/yunobot/ml-engine.js');

const Engine = windowObj.YunoBotMLEngine;
if (!Engine) { console.error('engine missing'); process.exit(1); }
const engine = new Engine();
if (!engine.workerReady) { console.error('FAIL: engine not ready (NN missing)'); process.exit(1); }

let pass = 0, fail = 0;
async function ask(q, check) {
  const r = await engine.process(q);
  const ok = check(r);
  if (ok) pass++;
  else { fail++; console.log(`FAIL: "${q}" -> intent=${r.intent} conf=${r.confidence?.toFixed?.(2)} :: ${(r.response || '').slice(0, 90)}`); }
  return r;
}

const has = (r, ...words) => words.some(w => (r.response || '').toLowerCase().includes(w));

const tests = [
  // navigation (regex layer)
  ['take me to the about section', r => r.intent === 'navigate' && r.url === '/about'],
  ['show me the portfolio', r => r.intent === 'navigate' && r.url === '/portfolio'],
  ['gallery', r => r.intent === 'navigate' && r.url === '/gallery'],
  // Q&A regex layer
  ['who is yunus', r => has(r, 'software developer', 'it specialist')],
  ['where is yunus', r => has(r, 'istanbul')],
  ['how old is yunus', r => has(r, 'february 2000', 'mid-20')],
  // tools (English — previously broken)
  ['what time is it', r => r.intent === 'tool_time' && has(r, 'current time')],
  ['calculate 12 * 5', r => r.intent === 'tool_calculator' && has(r, '60')],
  ['what is 2+2', r => r.intent === 'tool_calculator' && has(r, '4')],
  // tools (Turkish)
  ['saat kaç', r => r.intent === 'tool_time'],
  // Turkish QA
  ['yunus nerede', r => has(r, 'stanbul')],
  // NN semantic (paraphrases that regex does not cover)
  ['which programming languages does he work with', r => r.intent === 'yunus_technologies' || has(r, 'php')],
  ['how many countries did he go to', r => has(r, '21 countries')],
  ['when was he born', r => has(r, 'february 2000', '2000')],
  ['are you an llm', r => r.intent === 'bot_llm' && has(r, 'custom neural network')],
  ['what kind of ai are you', r => has(r, 'custom neural network')],
  ['how do you work internally', r => has(r, 'fasttext', 'int8')],
  ['do you load the model from hugging face', r => has(r, 'nothing to download', 'no hugging face')],
  // OOS → fallback (must NOT answer confidently wrong)
  ['what is the capital of france', r => r.confidence < 0.7 && !has(r, 'paris is')],
  ['tell me a joke', r => r.confidence < 0.7],
  // follow-up context
  ['does he like php', r => has(r, 'php')],
];

(async () => {
  for (const [q, check] of tests) await ask(q, check);

  // latency over full process() (regex+NN)
  const t0 = performance.now();
  const N = 200;
  for (let i = 0; i < N; i++) await engine.process('which technologies does he know ' + i);
  const dt = (performance.now() - t0) / N;
  console.log(`process() latency: ${dt.toFixed(3)} ms/query`);

  console.log(`\n${pass} passed, ${fail} failed`);
  process.exit(fail === 0 ? 0 : 1);
})();
