#!/usr/bin/env node
// Debug: replicate brain.c scoring in JS to see per-probe internals.
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
const NTERMS = u32(4), NSENTS = u32(12), AVGDL = u32(16), NPAIRS = u32(20);
const VOCAB = u32(24), DF = u32(28), POSTOFF = u32(32), POSTSENT = u32(36), POSTTF = u32(40), LM = u32(44), SENTOFF = u32(48), SENTTEXT = u32(52);

const vocab = data.subarray(VOCAB, DF).toString("ascii").split("\0");
vocab.length = NTERMS;
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
const STOP = new Set(('a,an,the,and,or,of,to,in,on,at,for,with,about,from,by,as,is,are,was,were,be,been,being,am,do,does,did,have,has,had,i,you,he,she,it,we,they,me,him,her,us,them,my,your,his,our,their,this,that,these,those,what,which,who,whom,whose,when,where,why,how,not,no,so,but,if,then,than,there,here,can,could,would,should,will,shall,may,might,must,its,one,just,tell,also,very,really,into,through,up,down,out,off,over,under,too,all,any,both,each,few,more,most,other,some,such,only,own,same,once,still,even,lot').split(','));
const qtoks = (q) => norm(q).split(/[^a-z0-9]+/).filter((t) => t.length >= 2 && !STOP.has(t)).map(stem);
const sentText = (sid) => data.subarray(SENTTEXT + u32(SENTOFF + sid * 4), SENTTEXT + u32(SENTOFF + (sid + 1) * 4)).toString("utf8");

function probe(q) {
    const qts = qtoks(q);
    const qids = [];
    const qidf = [];
    let sumIdf = 0;
    for (const t of qts) {
        const id = vocab.indexOf(t);
        if (id < 0) continue;
        if (qids.includes(id)) continue;
        const df = u32(DF + id * 4);
        const idf = Math.log(1 + (NSENTS - df + 0.5) / (df + 0.5));
        if (idf < 1) continue;
        qids.push(id); qidf.push(idf); sumIdf += idf;
    }
    if (!qids.length) return { q, why: "no vocab terms" };
    const avgdl = AVGDL / 100;
    const scores = new Float32Array(NSENTS);
    for (let t = 0; t < qids.length; t++) {
        const off = u32(POSTOFF + qids[t] * 4), end = u32(POSTOFF + (qids[t] + 1) * 4);
        for (let p = off; p < end; p++) {
            const sid = u32(POSTSENT + p * 4), tf = u16(POSTTF + p * 2);
            const dl = u32(SENTOFF + (sid + 1) * 4) - u32(SENTOFF + sid * 4);
            const denom = tf + 1.2 * (1 - 0.75 + 0.75 * dl / avgdl);
            scores[sid] += qidf[t] * (tf * 2.2) / denom;
        }
    }
    const top = [...scores.keys()].filter((s) => scores[s] > 0).sort((a, b) => scores[b] - scores[a]).slice(0, 16);
    let best = null;
    for (const sid of top) {
        const toks = qtoks(sentText(sid));
        const sentTf = qids.map((qid) => toks.filter((t) => t === vocab[qid]).length);
        let lmz = 0;
        for (let t = 0; t < qids.length; t++) {
            const pcoll = u32(DF + qids[t] * 4) / NSENTS;
            const p = (sentTf[t] + 100 * pcoll) / (toks.length + 100);
            lmz += Math.log(p);
        }
        let bon = 0;
        for (let t = 0; t + 1 < qids.length; t++) {
            if (sentTf[t] > 0 && sentTf[t + 1] > 0) bon += 0.4;
        }
        const lmzAvg = lmz / qids.length;
        const merged = scores[sid] / (sumIdf || 1) + 0.10 * lmzAvg + bon;
        if (!best || merged > best.merged) best = { sid, merged, ratio: scores[sid] / sumIdf, lmzAvg, bon, text: sentText(sid).slice(0, 80) };
    }
    if (!best) return { q, why: "no bm25 hits" };
    const floor = qids.length <= 1 ? 0.55 : 0.40;
    const cut = best.merged < floor ? `CUT (floor ${floor})` : "PASS";
    return { q, floor, cut, ...best };
}
for (const q of ["tell me about the mr graphy project", "what does he write about in his blog", "is there a post about nodejs", "what is finetuneyuno", "which countries has he visited", "what did he study at university", "does he work in industrial automation", "tell me about the post code era", "what does he think about edge ai", "where does he live", "how do i fix my car engine"]) {
    const r = probe(q);
    if (r.why) console.log(JSON.stringify(r));
    else console.log(`${r.cut} | "${r.q}" merged=${r.merged.toFixed(3)} ratio=${r.ratio.toFixed(2)} lmz=${r.lmzAvg.toFixed(2)} bon=${r.bon}\n      [${r.sid}] ${r.text}`);
}
