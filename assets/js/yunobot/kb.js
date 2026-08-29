/**
 * YunoBot Knowledge Engine loader (WASM).
 *
 * Loads `brain.wasm` (freestanding C: BM25 + Dirichlet-LM ranker) and the
 * inline `knowledge-pack.js` (`window.YUNOBOT_KB`), wires them into wasm
 * linear memory, and exposes YunoBotKB.answer(q) for open content questions.
 *
 * Zero dependencies: WebAssembly for the brain, DecompressionStream for the
 * deflated pack. If either is unavailable the engine silently disables and the
 * bot falls back to its pattern/NN logic.
 */
((global) => {
  const scriptSrc =
    global.document && global.document.currentScript
      ? global.document.currentScript.src
      : "";
  // cache-bust brain.wasm with the mtime the PHP template stamps on the script tag
  const wasmUrl =
    (global.document &&
      global.document.currentScript &&
      global.document.currentScript.dataset.wasm) ||
    (scriptSrc ? new URL("brain.wasm", scriptSrc).href : "");

  const TR = {
    ç: "c",
    Ç: "C",
    ğ: "g",
    Ğ: "G",
    ı: "i",
    İ: "i",
    ö: "o",
    Ö: "O",
    ş: "s",
    Ş: "S",
    ü: "u",
    Ü: "U",
    â: "a",
    î: "i",
    û: "u",
  };
  const norm = (s) =>
    String(s)
      .toLowerCase()
      .replace(/[çÇğĞıİöÖşŞüÜâîû]/g, (ch) => TR[ch] || ch);

  let ins = null,
    mem = null,
    ready = null;
  let answerOutOff = 0,
    queryOutOff = 0;
  const PACK_BASE = 131072; // must stay in sync with brain.c (past module statics/initial memory)

  function base64ToBytes(b64) {
    const bin = atob(b64);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return bytes;
  }

  async function inflateRaw(bytes) {
    const stream = new Blob([bytes])
      .stream()
      .pipeThrough(new DecompressionStream("deflate-raw"));
    return new Uint8Array(await new Response(stream).arrayBuffer());
  }

  async function load() {
    if (ready) return ready;
    ready = (async () => {
      if (
        typeof WebAssembly === "undefined" ||
        typeof DecompressionStream === "undefined"
      ) {
        ready = null;
        return false;
      }
      const KB = global.YUNOBOT_KB;
      if (!KB || !KB.blob || !wasmUrl) {
        ready = null;
        return false;
      }

      const wasm = await WebAssembly.compile(
        await (await fetch(wasmUrl)).arrayBuffer(),
      );

      const pack = await inflateRaw(base64ToBytes(KB.blob));
      const packLen = Math.ceil(pack.length / 4) * 4 + 4;
      const scratch = PACK_BASE + packLen;
      const total = scratch + 1024 * 1024;

      ins = new WebAssembly.Instance(wasm, {}).exports;
      const curPages = ins.memory.buffer.byteLength / 65536;
      const pages = Math.ceil(total / 65536);
      if (ins.memory.grow(pages - curPages) < 0)
        throw new Error("WASM memory grow failed");
      mem = new Uint8Array(ins.memory.buffer); // view AFTER grow (buffer detaches)
      mem.set(pack, PACK_BASE);
      ins.kb_setup(PACK_BASE, scratch);
      if (!ins.kb_load()) throw new Error("kb_load failed");

      answerOutOff = scratch + 0x100000 - 8192;
      queryOutOff = answerOutOff - 4096;
      return true;
    })().catch((e) => {
      console.warn("[YunoBot KB] load failed:", e);
      ready = null;
      return false;
    });
    return ready;
  }

  function answer(q) {
    if (!mem || !ins || !answerOutOff) return null;
    const bytes = new TextEncoder().encode(norm(q));
    if (!bytes.length || bytes.length > 8000) return null;

    mem.set(bytes, queryOutOff);
    const n = ins.kb_answer(queryOutOff, bytes.length, answerOutOff, 4096);
    if (!n) return null;

    const dv = new DataView(ins.memory.buffer);
    const sentId = dv.getUint32(answerOutOff, true);
    const score = dv.getFloat32(answerOutOff + 4, true);
    const text = new TextDecoder().decode(
      mem.subarray(answerOutOff + 8, answerOutOff + n - 1),
    );
    const KB = global.YUNOBOT_KB;
    const doc = KB.docs[KB.sentDoc[sentId]] || {};
    return {
      sentId,
      score: +score.toFixed(3),
      sentence: text,
      page: doc.page,
      url: doc.url,
      title: doc.title,
    };
  }

  global.YunoBotKB = { load, answer };
})(typeof window === "undefined" ? globalThis : window);
