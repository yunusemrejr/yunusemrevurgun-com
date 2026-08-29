#!/usr/bin/env node
// Debug: inspect pack vocab/postings for probe terms.
import { readFileSync } from "node:fs";
import { inflateRawSync } from "node:zlib";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(dirname(dirname(dirname(fileURLToPath(import.meta.url)))));
await import("file://" + join(ROOT, "assets/js/yunobot/knowledge-pack.js"));
const KB = globalThis.YUNOBOT_KB;
const data = inflateRawSync(Buffer.from(KB.blob, "base64"));
const u32 = (o) => data.readUInt32LE(o);
const u16 = (o) => data.readUInt16LE(o);

const MAGIC = u32(0), NTERMS = u32(4), NPOSTS = u32(8), NSENTS = u32(12), AVGDL = u32(16), NPAIRS = u32(20);
const VOCAB = u32(24), DF = u32(28), POSTOFF = u32(32), POSTSENT = u32(36), POSTTF = u32(40), LM = u32(44), SENTOFF = u32(48), SENTTEXT = u32(52), TOTAL = u32(56);
console.log(`magic ok=${MAGIC === 0x314b4b59} nTerms=${NTERMS} nPosts=${NPOSTS} nSents=${NSENTS} avgdl=${(AVGDL/100).toFixed(1)} nPairs=${NPAIRS} total=${TOTAL} len=${data.length}`);

const vocabEnd = DF;
const vocab = data.subarray(VOCAB, vocabEnd).toString("ascii").split("\0");
vocab.length = NTERMS;
console.log("vocab sample:", vocab.slice(0, 8), "...", vocab.slice(-5));

const TR = { ç: "c", Ç: "C", ğ: "g", Ğ: "G", ı: "i", İ: "i", ö: "o", Ö: "O", ş: "s", Ş: "S", ü: "u", Ü: "U", â: "a", î: "i", û: "u" };
const norm = (s) => String(s).replace(/[çÇğĞıİöÖşŞüÜâîû]/g, (ch) => TR[ch] || ch).toLowerCase();
function stem(w) {
    if (w.length <= 4) return w;
    if (w.length > 5 && w.endsWith("ing")) return w.slice(0, -3);
    if (w.length > 4 && w.endsWith("ed")) return w.slice(0, -2);
    if (w.length > 4 && w.endsWith("es")) return w.slice(0, -2);
    if (w.length > 3 && w.endsWith("s") && !w.endsWith("ss")) return w.slice(0, -1);
    return w;
}

// sentence text reader
const sentText = (sid) => {
    const a = u32(SENTOFF + sid * 4), b = u32(SENTOFF + (sid + 1) * 4);
    return data.subarray(SENTTEXT + a, SENTTEXT + b).toString("utf8");
};

// probe terms (after build.mjs pipeline)
const probes = ["graphy", "node", "finetuneyuno", "countri", "univers", "istanbul", "edge", "blog", "writ", "visitt", "studi"];
for (const p of probes) {
    // binary search vocab (sorted)
    let lo = 0, hi = NTERMS, id = -1;
    while (lo < hi) {
        const mid = (lo + hi) >> 1;
        if (vocab[mid] < p) lo = mid + 1; else hi = mid;
    }
    if (lo < NTERMS && vocab[lo] === p) id = lo;
    if (id < 0) { console.log(`term "${p}": NOT IN VOCAB`); continue; }
    const df = u32(DF + id * 4);
    const off = u32(POSTOFF + id * 4), end = u32(POSTOFF + (id + 1) * 4);
    console.log(`term "${p}" id=${id} df=${df} postings=${end - off}`);
    for (let k = off; k < Math.min(end, off + 3); k++) {
        const sid = u32(POSTSENT + k * 4);
        const doc = KB.docs[KB.sentDoc[sid]];
        console.log(`   [${sid}] (${doc.page}) ${sentText(sid).slice(0, 100)}`);
    }
}
