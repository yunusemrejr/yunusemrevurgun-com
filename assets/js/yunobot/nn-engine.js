/** Compact neural text classifier. Shared Unicode/subword features -> weighted
 * embedding bag -> softmax. Open-set class, probability margin and vocabulary
 * coverage gate acceptance. Embeddings also provide a small retrieval tie-break.
 * Network-free inference; model assets are loaded by the page.
 */
(function () {
    'use strict';

    var weights = window.YUNOBOT_NN_WEIGHTS;
    var api = { ready: false, match: function () { return []; } };
    window.YunoBotNN = api;
    if (!weights || !weights.vocab) return;

    // ============================================================
    // Decode base64 -> typed arrays and dequantize to Float32
    // ============================================================
    function b64ToBytes(b64) {
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
    }

    function dequantize(b64q, b64s) {
        var q = new Int8Array(b64ToBytes(b64q).buffer);
        var s = new Float32Array(b64ToBytes(b64s).buffer);
        var out = new Float32Array(q.length);
        for (var i = 0; i < q.length; i++) out[i] = q[i] * s[(i / weights.dim) | 0];
        return out;
    }

    function decodeF32(b64) {
        return new Float32Array(b64ToBytes(b64).buffer);
    }

    var DIM = weights.dim;
    var NUM_BUCKETS = weights.buckets;
    var WORD_WEIGHT = weights.wordWeight || 1;
    var Ev, Eb, W, B;
    var vocabIndex = new Map();
    try {
        Ev = dequantize(weights.evQ, weights.evS);
        Eb = dequantize(weights.ebQ, weights.ebS);
        W = decodeF32(weights.wF);
        B = decodeF32(weights.bW);
        for (var i = 0; i < weights.vocab.length; i++) vocabIndex.set(weights.vocab[i], i);
    } catch (e) {
        if (window.console) console.warn('[YunoBotNN] weight decode failed:', e);
        return;
    }
    var INTENTS = weights.intents;
    var N_INTENTS = INTENTS.length;

    // ============================================================
    // Tokenizer + features (identical to dev/yunobot-nn/train.mjs)
    // ============================================================
    if (!window.YunoBotText) return;
    var { tokenize, charTrigrams, hash32 } = window.YunoBotText;

    // ============================================================
    // Embed: mean of feature rows -> raw h (softmax) + normalized (cosine)
    // ============================================================
    var hRaw = new Float32Array(DIM);
    var hNorm = new Float32Array(DIM);

    function embed(text) {
        var toks = tokenize(text);
        hRaw.fill(0);
        var n = 0;
        for (var i = 0; i < toks.length; i++) {
            var t = toks[i];
            var id = vocabIndex.get(t);
            if (id !== undefined) {
                var base = id * DIM;
                for (var d = 0; d < DIM; d++) hRaw[d] += Ev[base + d] * WORD_WEIGHT;
                n += WORD_WEIGHT;
            }
            var grams = charTrigrams(t);
            for (var g = 0; g < grams.length; g++) {
                var bbase = (hash32('t' + grams[g]) % NUM_BUCKETS) * DIM;
                for (var d2 = 0; d2 < DIM; d2++) hRaw[d2] += Eb[bbase + d2];
                n++;
            }
            if (i > 0) {
                var wbase = (hash32('w' + toks[i - 1] + ' ' + t) % NUM_BUCKETS) * DIM;
                for (var d3 = 0; d3 < DIM; d3++) hRaw[d3] += Eb[wbase + d3];
                n++;
            }
        }
        if (n === 0) return false;
        var norm = 0;
        for (var d4 = 0; d4 < DIM; d4++) {
            hRaw[d4] /= n;
            norm += hRaw[d4] * hRaw[d4];
        }
        norm = Math.sqrt(norm) || 1;
        for (var d5 = 0; d5 < DIM; d5++) hNorm[d5] = hRaw[d5] / norm;
        return true;
    }

    // ============================================================
    // Scorers
    // ============================================================
    var logits = new Float32Array(N_INTENTS);

    function softmaxTop(k) {
        var maxL = -Infinity;
        for (var c = 0; c < N_INTENTS; c++) {
            var s = B[c];
            var base = c * DIM;
            for (var d = 0; d < DIM; d++) s += W[base + d] * hRaw[d];
            logits[c] = s;
            if (s > maxL) maxL = s;
        }
        var sum = 0;
        for (var c2 = 0; c2 < N_INTENTS; c2++) {
            logits[c2] = Math.exp(logits[c2] - maxL);
            sum += logits[c2];
        }
        var out = [];
        for (var c3 = 0; c3 < N_INTENTS; c3++) out.push({ idx: c3, p: logits[c3] / sum });
        out.sort(function (a, b) { return b.p - a.p; });
        return out.slice(0, k);
    }

    api.classify = function (text) {
        if (!api.ready || !text || !embed(String(text))) return { candidates: [], margin: 0, oos: 1 };
        var soft = softmaxTop(N_INTENTS);
        var oos = soft.find(row => INTENTS[row.idx].type === 'oos');
        var candidates = soft.filter(row => INTENTS[row.idx].type !== 'oos').slice(0, 6).map(row => ({
            ...INTENTS[row.idx], confidence: row.p,
        }));
        // Probability is not inflated by cosine similarity. OOS competes in the
        // same normalized distribution; the runner-up margin signals ambiguity.
        const margin = soft.length > 1 ? soft[0].p - soft[1].p : 0;
        const known = tokenize(text).filter(token => vocabIndex.has(token)).length / Math.max(1, tokenize(text).length);
        return { candidates, oos: oos ? oos.p : 0,
            accepted: INTENTS[soft[0].idx].type !== 'oos' && soft[0].p >= .43 && margin >= .12 && known >= .5,
            margin: soft.length > 1 ? soft[0].p - soft[1].p : 0,
            rejected: INTENTS[soft[0].idx].type === 'oos' };
    };
    api.match = function (text, topN) {
        var result = api.classify(text);
        return !result.accepted ? [] : result.candidates.slice(0, topN || 6);
    };
    api.embed = function (text) {
        return embed(text) ? new Float32Array(hNorm) : new Float32Array(DIM);
    };

    api.ready = true;
})();
