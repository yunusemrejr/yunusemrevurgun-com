#!/usr/bin/env node
/**
 * YunoBot Knowledge Base builder.
 *
 * Fetches public site pages (static views + blog/update posts), extracts the
 * main content, splits it into sentences, and builds a compact binary pack:
 *
 *   vocab (term list) + df + postings (term -> sentence id/tf) + word-bigram
 *   LM counts + sentence offsets + sentence text.
 *
 * The pack is deflated and base64-embedded in assets/js/yunobot/knowledge-pack.js
 * as window.YUNOBOT_KB = { v, docs:[{page,url,title}], sentDoc:[u32], blob }.
 * assets/js/yunobot/kb.js inflates it and byte-copies it into the WASM module's
 * linear memory (see brain.c for the exact layout).
 *
 * Zero dependencies. Deterministic: sorts terms/bigrams before serialization.
 */
import { deflateRawSync } from 'node:zlib';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
const OUT = join(ROOT, 'assets/js/yunobot/knowledge-pack.js');

const BASE = 'https://yunusemrevurgun.com';
const STATIC_PAGES = ['about', 'portfolio', 'travel', 'gallery', 'contact', 'post-code', 'science-corner', 'music', 'rmrp', 'comedy', 'more', 'updates', 'blog'];

// ---------------------------------------------------------------
// Turkish diacritics -> ASCII (applied to corpus text AND queries)
// ---------------------------------------------------------------
const TR_MAP = { ç: 'c', Ç: 'C', ğ: 'g', Ğ: 'G', ı: 'i', I: 'i', İ: 'i', ö: 'o', Ö: 'O', ş: 's', Ş: 'S', ü: 'u', Ü: 'U', â: 'a', î: 'i', û: 'u' };

function normalizeText(s) {
    return String(s)
        .replace(/[çÇğĞıİöÖşŞüÜâîû]/g, (ch) => TR_MAP[ch] || ch)
        .toLowerCase();
}

// Stopwords: dropped at corpus build time (also never found from query side).
const STOP = new Set(('a,an,the,and,or,of,to,in,on,at,for,with,about,from,by,as,is,are,was,were,be,been,being,am,do,does,did,have,has,had,i,you,he,she,it,we,they,me,him,her,us,them,my,your,his,our,their,this,that,these,those,what,which,who,whom,whose,when,where,why,how,not,no,so,but,if,then,than,there,here,can,could,would,should,will,shall,may,might,must,its,one,just,tell,also,very,really,into,through,up,down,out,off,over,under,too,all,any,both,each,few,more,most,other,some,such,only,own,same,once,still,even,lot').split(','));

function tokenizeText(s) {
    // Keep [a-z0-9] token chars; everything else splits. Matches brain.c
    // (incl. the light suffix stemmer below).
    const lower = normalizeText(s);
    const toks = [];
    let cur = '';
    for (const ch of lower) {
        if ((ch >= 'a' && ch <= 'z') || (ch >= '0' && ch <= '9')) {
            cur += ch;
        } else {
            if (cur.length >= 2 && !STOP.has(cur)) toks.push(stem(cur));
            cur = '';
        }
    }
    if (cur.length >= 2 && !STOP.has(cur)) toks.push(stem(cur));
    return toks;
}

// Light suffix stemmer — MUST stay byte-identical with stem() in brain.c.
function stem(w) {
    if (w.length <= 4) return w;
    if (w.length > 5 && w.endsWith('ing')) return w.slice(0, -3);
    if (w.length > 4 && w.endsWith('ed')) return w.slice(0, -2);
    if (w.length > 4 && w.endsWith('es')) return w.slice(0, -2);
    if (w.length > 3 && w.endsWith('s') && !w.endsWith('ss')) return w.slice(0, -1);
    return w;
}

// ---------------------------------------------------------------
// Fetch + extraction
// ---------------------------------------------------------------
async function fetchHtml(url) {
    const res = await fetch(url, { headers: { 'user-agent': 'yunobot-kb-builder/1.0' }, signal: AbortSignal.timeout(15000) });
    if (!res.ok) return null;
    return res.text();
}

function stripHtml(html) {
    const main = html.match(/<main[^>]*>([\s\S]*?)<\/main>/i);
    let body = main ? main[1] : html;
    body = body
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<nav[\s\S]*?<\/nav>/gi, ' ')
        .replace(/<footer[\s\S]*?<\/footer>/gi, ' ')
        .replace(/<form[\s\S]*?<\/form>/gi, ' ')
        .replace(/<svg[\s\S]*?<\/svg>/gi, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#0?39;/g, "'")
        .replace(/&#x27;/g, "'")
        .replace(/&#8211;/g, '-')
        .replace(/&#8212;/g, '-')
        .replace(/&#8216;|&#8217;/g, "'")
        .replace(/&#8220;|&#8221;/g, '"')
        .replace(/&#8230;/g, '...')
        .replace(/\s+/g, ' ');
    return body.trim();
}

function titleOf(html) {
    const h1 = html.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i);
    if (h1) return stripHtml(h1[1]).slice(0, 80);
    const t = html.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
    return t ? stripHtml(t[1]).slice(0, 80) : '';
}

function splitSentences(text) {
    // Split on sentence terminators; keep fragments >= 25 chars of prose.
    const parts = text.split(/(?<=[.!?])\s+/);
    const sentences = [];
    let carry = '';
    for (let p of parts) {
        p = p.trim();
        if (!p) continue;
        const s = carry ? carry + ' ' + p : p;
        const words = s.split(/\s+/).length;
        if (words < 3 || s.length > 350) {
            // too short: try to merge with next; too long: hard-split at comma run
            if (s.length > 350 && words >= 3) {
                sentences.push(s.slice(0, s.lastIndexOf(',', 300) > 100 ? s.slice(0, s.lastIndexOf(',', 300)) : s.slice(0, 300)));
                carry = s.slice(s.lastIndexOf(',', 300) > 100 ? s.lastIndexOf(',', 300) + 1 : 300);
            } else {
                carry = s;
            }
            continue;
        }
        sentences.push(s);
        carry = '';
    }
    if (carry && carry.split(/\s+/).length >= 3 && carry.length <= 350) sentences.push(carry);
    return sentences;
}

// Likely prose: majority simple tokens and low digit/nav noise.
function isProse(s) {
    const letters = (s.match(/[a-zA-Z]/g) || []).length;
    const nonAscii = (s.match(/[^\x00-\x7F]/g) || []).length;
    if (s.length < 25) return false;
    if (letters < s.length * 0.55) return false;
    if (nonAscii > letters * 0.25) return false;
    if (/^(https?:|www\.|©|all rights reserved|privacy policy|toggle|read more)/i.test(s)) return false;
    return true;
}

async function collectDocs() {
    const docs = []; // {page, url, title, text}

    // Home page (redirects to /)
    const home = await fetchHtml(BASE + '/home');
    if (home) docs.push({ page: 'home', url: BASE + '/home', title: titleOf(home) || 'Home', text: stripHtml(home) });

    for (const slug of STATIC_PAGES) {
        const html = await fetchHtml(`${BASE}/${slug}`);
        if (!html) continue;
        docs.push({ page: slug, url: `${BASE}/${slug}`, title: titleOf(html) || slug, text: stripHtml(html) });
    }

    // Blog posts: gather slugs from listing pages, fetch each post.
    const slugs = new Set();
    for (let p = 1; p <= 6; p++) {
        const listing = await fetchHtml(`${BASE}/blog?page=${p}`);
        if (!listing) break;
        for (const m of listing.matchAll(/href="[^"]*\/blog\/([a-z0-9-]+)"/g)) slugs.add(m[1]);
    }
    for (const slug of slugs) {
        const html = await fetchHtml(`${BASE}/blog/${slug}`);
        if (!html) continue;
        docs.push({ page: 'blog', url: `${BASE}/blog/${slug}`, title: titleOf(html) || slug, text: stripHtml(html) });
    }

    // Updates: ids from the updates page.
    const upd = await fetchHtml(`${BASE}/updates`);
    if (upd) {
        const ids = new Set();
        for (const m of upd.matchAll(/href="[^"]*\/updates\/(\d+)"/g)) ids.add(m[1]);
        for (const id of ids) {
            const html = await fetchHtml(`${BASE}/updates/${id}`);
            if (!html) continue;
            docs.push({ page: 'updates', url: `${BASE}/updates/${id}`, title: titleOf(html) || `Update ${id}`, text: stripHtml(html) });
        }
    }

    // Final: sentence-level dedupe
    return docs.filter((d) => d.text && d.text.length > 80);
}

// ---------------------------------------------------------------
// Pack builder
// ---------------------------------------------------------------
function buildPack(docs) {
    const sentences = []; // { text }
    const sentDoc = []; // doc index per sentence
    const docMeta = []; // {page,url,title}

    const seenSents = new Set();
    for (let di = 0; di < docs.length; di++) {
        const d = docs[di];
        docMeta.push({ page: d.page, url: d.url, title: d.title });
        const sents = splitSentences(d.text).filter(isProse);
        for (const s of sents) {
            const key = normalizeText(s).replace(/[^a-z0-9 ]/g, '').slice(0, 160);
            if (seenSents.has(key)) continue;
            seenSents.add(key);
            sentences.push({ text: s });
            sentDoc.push(di);
        }
    }

    // Term stats over sentences.
    const termMap = new Map(); // term -> { df, tfPerSent: Map<sent, tf> }
    const countsPerSent = [];
    for (let si = 0; si < sentences.length; si++) {
        const toks = tokenizeText(sentences[si].text);
        // avgdl must be in BYTE length to match sent_len() in brain.c (BM25 uses
        // only the dl/avgdl ratio, so consistent units are what matters).
        countsPerSent.push(Buffer.byteLength(sentences[si].text, 'utf8'));
        const local = new Map();
        for (const t of toks) local.set(t, (local.get(t) || 0) + 1);
        for (const [t, tf] of local) {
            let info = termMap.get(t);
            if (!info) { info = { df: 0, tfPerSent: [] }; termMap.set(t, info); }
            info.df++;
            info.tfPerSent.push(si, tf);
        }
    }

    // Unique terms, alphabetical (C binary-searches the vocab blob).
    const terms = [...termMap.keys()].sort();
    const termId = new Map(terms.map((t, i) => [t, i]));

    const nTerms = terms.length;
    const nSents = sentences.length;
    const vocabBlob = Buffer.from(terms.join('\0') + '\0', 'ascii');

    const dfArr = new Uint32Array(nTerms);
    const postOff = new Uint32Array(nTerms + 1);
    const postSent = [];
    const postTf = [];
    for (let ti = 0; ti < nTerms; ti++) {
        const info = termMap.get(terms[ti]);
        dfArr[ti] = info.df;
        postOff[ti] = postSent.length;
        for (let k = 0; k < info.tfPerSent.length; k += 2) {
            postSent.push(info.tfPerSent[k]);
            postTf.push(Math.min(255, info.tfPerSent[k + 1]));
        }
    }
    postOff[nTerms] = postSent.length;

    // Word-bigram LM counts (within sentences, count >= 2, capped).
    const lmMap = new Map(); // key = w1*2^32? use string
    for (let si = 0; si < sentences.length; si++) {
        const toks = tokenizeText(sentences[si].text);
        for (let i = 0; i + 1 < toks.length; i++) {
            const a = termId.get(toks[i]);
            const b = termId.get(toks[i + 1]);
            if (a === undefined || b === undefined) continue;
            const key = a * 0x100000 + b; // terms < 2^20 each
            lmMap.set(key, (lmMap.get(key) || 0) + 1);
        }
    }
    const lmPairs = [...lmMap.entries()]
        .filter(([, c]) => c >= 2)
        .sort((x, y) => x[0] - y[0])
        .slice(0, 30000);
    const nPairs = lmPairs.length;
    const lmArr = new Uint16Array(nPairs * 3);
    lmPairs.forEach(([key, c], i) => {
        lmArr[i * 3] = (key / 0x100000) | 0;
        lmArr[i * 3 + 1] = key % 0x100000;
        lmArr[i * 3 + 2] = Math.min(65535, c);
    });

    // Sentence offsets + text blob (original casing/UTF-8 for display).
    const sentOff = new Uint32Array(nSents + 1);
    const textParts = [];
    let off = 0;
    for (let si = 0; si < nSents; si++) {
        sentOff[si] = off;
        const bytes = Buffer.from(sentences[si].text, 'utf8');
        textParts.push(bytes);
        off += bytes.length;
    }
    sentOff[nSents] = off;
    const textBlob = Buffer.concat(textParts);

    const avgdl = Math.round((countsPerSent.reduce((a, b) => a + b, 0) / Math.max(1, nSents)) * 100);

    // ------------------------------------------------------------
    // Serialize pack — offsets known upfront; header at 0.
    // u32 fields (little-endian):
    //  0 magic, 4 nTerms, 8 nPosts, 12 nSents, 16 avgdl100, 20 nPairs,
    // 24 vocabOff, 28 dfOff, 32 postOffOff, 36 postSentOff, 40 postTfOff,
    // 44 lmOff, 48 sentOffOff, 52 sentTextOff, 56 totalSize
    // ------------------------------------------------------------
    const u32 = (n) => { const b = Buffer.alloc(4); b.writeUInt32LE(n >>> 0); return b; };

    const vocabOff = 60;
    const dfOff = vocabOff + vocabBlob.length;
    const postOffOff = dfOff + nTerms * 4;
    const postSentOff = postOffOff + (nTerms + 1) * 4;
    const postTfOff = postSentOff + postSent.length * 4;
    const lmOff = postTfOff + postTf.length * 2;
    const sentOffOff = lmOff + lmArr.length * 2;
    const sentTextOff = sentOffOff + (nSents + 1) * 4;
    const totalSize = sentTextOff + textBlob.length;

    const parts = [
        u32(0x314b4b59), u32(nTerms), u32(postSent.length), u32(nSents), u32(avgdl), u32(nPairs),
        u32(vocabOff), u32(dfOff), u32(postOffOff), u32(postSentOff), u32(postTfOff),
        u32(lmOff), u32(sentOffOff), u32(sentTextOff), u32(totalSize),
        vocabBlob,
        Buffer.from(dfArr.buffer, dfArr.byteOffset, dfArr.byteLength),
        Buffer.from(postOff.buffer, postOff.byteOffset, postOff.byteLength),
        Buffer.from(new Uint32Array(postSent).buffer),
        Buffer.from(new Uint16Array(postTf).buffer),
        Buffer.from(lmArr.buffer, lmArr.byteOffset, lmArr.byteLength),
        Buffer.from(sentOff.buffer, sentOff.byteOffset, sentOff.byteLength),
        textBlob,
    ];
    const pack = Buffer.concat(parts);
    if (pack.length !== totalSize) throw new Error(`pack size mismatch ${pack.length} != ${totalSize}`);

    return { pack, docMeta, sentDoc, nSents };
}

// ---------------------------------------------------------------
// Emit knowledge-pack.js
// ---------------------------------------------------------------
const docs = await collectDocs();
console.log(`docs: ${docs.length}`);
const { pack, docMeta, sentDoc, nSents } = buildPack(docs);
console.log(`pack: ${pack.length} bytes, sentences: ${nSents}`);

const blob = deflateRawSync(pack).toString('base64');
const js = `/**
 * YunoBot knowledge pack (auto-generated by dev/yunobot-kb/build.mjs — DO NOT EDIT).
 * Docs: ${docMeta.length} · Sentences: ${nSents} · Pack: ${pack.length} bytes (deflated ${Math.round(blob.length * 0.75)} bytes base64).
 */
(function (g) { g.YUNOBOT_KB = {
    v: 1,
    sentDoc: [${sentDoc.join(',')}],
    docs: ${JSON.stringify(docMeta)},
    blob: '${blob}',
}; })(typeof window !== 'undefined' ? window : globalThis);
`;
mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, js);
console.log(`wrote ${OUT} (${js.length} bytes)`);
