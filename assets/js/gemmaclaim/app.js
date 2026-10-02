import { Wllama } from './wllama/index.min.js';
import { buildPrompt, looksNonEnglish } from './lang.js';

const BASE = window.GC_BASE || './';
const MODEL_URL = 'https://huggingface.co/yunusemrejr/GemmaClaim-270M/resolve/main/gguf/gemmaclaim-270m-q4_k_m.gguf';
const N_CTX = 2048;
const MAX_NEW_TOKENS = 512;
const SEEN_KEY = 'gc-model-cached';

const $ = (id) => document.getElementById(id);
const loadBtn = $('gcLoad'), bar = $('gcBar'), barFill = $('gcBarFill'), stateEl = $('gcState');
const log = $('gcLog'), form = $('gcForm'), input = $('gcInput'), sendBtn = $('gcSend');
const clearBtn = $('gcClear'), examples = $('gcExamples');

let wllama = null;
let modelReady = false;
let loading = false;
let busy = false;

const el = (tag, cls, text) => {
  const n = document.createElement(tag);
  if (cls) n.className = cls;
  if (text != null) n.textContent = text;
  return n;
};
const remember = (v) => { try { v ? localStorage.setItem(SEEN_KEY, '1') : localStorage.removeItem(SEEN_KEY); } catch { /* storage blocked */ } };
const wasCached = () => { try { return localStorage.getItem(SEEN_KEY) === '1'; } catch { return false; } };

function scrollDown() { log.scrollTop = log.scrollHeight; }

// ---- rendering: one brick per section of the answer ----
const HEAD = /^([A-Z][A-Z /-]*):\s*$/;
const ITEM = /^\s*(?:[-*]|\d+[.)])\s+(.*)$/;

function parseSections(text) {
  const sections = [];
  let cur = null;
  for (const line of text.split('\n')) {
    const m = line.trim().match(HEAD);
    if (m) { cur = { head: m[1], lines: [] }; sections.push(cur); continue; }
    if (!cur) { cur = { head: null, lines: [] }; sections.push(cur); }
    cur.lines.push(line);
  }
  return sections;
}

function fillBrick(brick, sec) {
  brick.textContent = '';
  if (sec.head) brick.appendChild(el('h3', 'gc-brick-head', sec.head));
  let list = null;
  for (const raw of sec.lines) {
    const line = raw.trim();
    if (!line) { list = null; continue; }
    const item = raw.match(ITEM);
    if (item) {
      if (!list) { list = el('ul'); brick.appendChild(list); }
      list.appendChild(el('li', null, item[1]));
    } else {
      list = null;
      brick.appendChild(el('p', null, line));
    }
  }
}

function renderReply(container, text) {
  const sections = parseSections(text.replace(/\s+$/, ''));
  while (container.children.length > sections.length) container.lastChild.remove();
  sections.forEach((sec, i) => {
    let brick = container.children[i];
    if (!brick) { brick = el('div', 'gc-brick'); container.appendChild(brick); }
    fillBrick(brick, sec);
  });
}

function addUser(text) {
  const n = el('div', 'gc-user', text);
  log.appendChild(n);
  // Anchor the view on the new question so the answer grows below it, top-first.
  log.scrollTop = Math.max(0, n.offsetTop - 12);
}
function addSystem(text) {
  const n = el('p', 'gc-system', text);
  log.appendChild(n);
  scrollDown();
}

// ---- model ----
function setState(text) { stateEl.textContent = text; }
function setProgress(pct) {
  const p = Math.max(0, Math.min(100, Math.round(pct)));
  barFill.style.width = p + '%';
  bar.setAttribute('aria-valuenow', String(p));
}
function ready(ok) {
  input.disabled = !ok;
  sendBtn.disabled = !ok || busy;
}

async function loadModel() {
  if (loading || modelReady) return;
  loading = true;
  loadBtn.disabled = true;
  bar.hidden = false;
  setProgress(0);
  setState('Starting the runtime…');
  const t0 = performance.now();
  try {
    wllama = new Wllama({ default: BASE + 'wllama/wllama.wasm' });
    await wllama.loadModelFromUrl(MODEL_URL, {
      n_ctx: N_CTX,
      useCache: true,
      progressCallback: ({ loaded, total }) => {
        if (total > 0) setProgress((loaded / total) * 100);
        setState(`Downloading ${(loaded / 1e6).toFixed(0)} of ${(total / 1e6).toFixed(0)} MB`);
      },
    });
    modelReady = true;
    setProgress(100);
    bar.hidden = true;
    remember(true);
    const secs = ((performance.now() - t0) / 1000).toFixed(1);
    const gpu = navigator.gpu ? 'WebGPU if available' : 'CPU (WebAssembly)';
    setState(`Ready in ${secs}s. Running locally on ${gpu}. Nothing you type leaves this page.`);
    loadBtn.textContent = 'Model loaded';
    examples.hidden = false;
    ready(true);
    input.focus({ preventScroll: true });
  } catch (e) {
    console.error(e);
    wllama = null;
    modelReady = false;
    remember(false);
    bar.hidden = true;
    loadBtn.disabled = false;
    loadBtn.textContent = 'Retry loading';
    setState('Could not load the model. Check your connection and try again.');
    addSystem('Model load failed: ' + (e && e.message ? e.message : e));
  } finally {
    loading = false;
  }
}

async function analyze(text) {
  if (!modelReady || busy) return;
  text = text.trim();
  if (!text) return;
  busy = true;
  ready(true);
  sendBtn.disabled = true;
  addUser(text);
  input.value = '';

  const reply = el('div', 'gc-reply');
  log.appendChild(reply);

  if (looksNonEnglish(text)) {
    renderReply(reply, 'I only analyze claims written in English.');
    busy = false; ready(true);
    return;
  }

  reply.appendChild(el('p', 'gc-system', 'Splitting…'));
  const t0 = performance.now();
  let full = '';
  try {
    const stream = await wllama.createCompletion({
      prompt: buildPrompt(text),
      max_tokens: MAX_NEW_TOKENS,
      temperature: 0,
      stream: true,
    });
    for await (const chunk of stream) {
      const piece = chunk?.choices?.[0]?.text ?? '';
      if (piece) {
        if (!full) reply.textContent = '';
        full += piece;
        renderReply(reply, full);
      }
      if (chunk?.choices?.[0]?.finish_reason) break;
    }
    if (!full.trim()) {
      reply.textContent = '';
      reply.appendChild(el('p', 'gc-system', 'The model returned nothing. Try rephrasing the claim.'));
    } else {
      reply.appendChild(el('p', 'gc-meta', `${((performance.now() - t0) / 1000).toFixed(1)}s`));
    }
  } catch (e) {
    console.error(e);
    reply.textContent = '';
    reply.appendChild(el('p', 'gc-system', 'Inference failed: ' + (e && e.message ? e.message : e)));
  } finally {
    busy = false;
    ready(true);
  }
}

// ---- wiring ----
loadBtn.addEventListener('click', loadModel);
form.addEventListener('submit', (e) => { e.preventDefault(); analyze(input.value); });
input.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); analyze(input.value); }
});
clearBtn.addEventListener('click', () => { if (!busy) { log.textContent = ''; input.value = ''; input.focus(); } });
examples.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-claim]');
  if (!b) return;
  if (!modelReady) { input.value = b.dataset.claim; setState('Load the model, then press Split.'); return; }
  analyze(b.dataset.claim);
});

// A returning visitor already has the file in the browser cache: load without a click.
if (wasCached()) loadModel();
