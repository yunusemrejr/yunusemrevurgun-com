#!/usr/bin/env node
// Debug: call kb_answer directly, print raw return + trap info.
import { readFileSync } from "node:fs";
import { inflateRawSync } from "node:zlib";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(dirname(dirname(dirname(fileURLToPath(import.meta.url)))));
await import("file://" + join(ROOT, "assets/js/yunobot/knowledge-pack.js"));
const KB = globalThis.YUNOBOT_KB;
const wasm = await WebAssembly.compile(readFileSync(join(ROOT, "assets/js/yunobot/brain.wasm")));
const PACK_BASE = 131072;
const data = inflateRawSync(Buffer.from(KB.blob, "base64"));
const packLen = Math.ceil(data.length / 4) * 4 + 4;
const scratch = PACK_BASE + packLen;
const total = scratch + 1024 * 1024;
const ins = new WebAssembly.Instance(wasm, {}).exports;
const pages = Math.ceil(total / 65536);
if (ins.memory.grow(pages - ins.memory.buffer.byteLength / 65536) < 0) throw new Error("grow failed");
const mem = new Uint8Array(ins.memory.buffer);
mem.set(data, PACK_BASE);
ins.kb_setup(PACK_BASE, scratch);
console.log("kb_load:", ins.kb_load(), "nsents:", ins.kb_nsents());

const TR = { ç: "c", Ç: "C", ğ: "g", Ğ: "G", ı: "i", İ: "i", ö: "o", Ö: "O", ş: "s", Ş: "S", ü: "u", Ü: "U", â: "a", î: "i", û: "u" };
const norm = (s) => String(s).replace(/[çÇğĞıİöÖşŞüÜâîû]/g, (ch) => TR[ch] || ch).toLowerCase();
const OUT_OFF = scratch + 0x100000 - 8192;
const Q_OFF = scratch + 0x100000 - 8192 - 4096;
function ask(q) {
    const qb = new TextEncoder().encode(norm(q));
    mem.set(qb, Q_OFF);
    try {
        const n = ins.kb_answer(Q_OFF, qb.length, OUT_OFF, 4096);
        if (!n) return null;
        const dv = new DataView(ins.memory.buffer);
        const sentId = dv.getUint32(OUT_OFF, true);
        const score = dv.getFloat32(OUT_OFF + 4, true);
        const text = new TextDecoder().decode(mem.subarray(OUT_OFF + 8, OUT_OFF + n - 1));
        return { sentId, score: +score.toFixed(3), text: text.slice(0, 90) };
    } catch (e) {
        return { TRAP: e.message };
    }
}
for (const q of ["graphy", "graphy project", "tell me about the mr graphy project", "istanbul", "finetuneyuno", "node", "how do i fix my car engine"]) {
    console.log(JSON.stringify(q), "->", JSON.stringify(ask(q)));
}
