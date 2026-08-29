/* YunoBot Knowledge Brain — freestanding WASM32, zero imports, zero libc.
 *
 * Ranks site sentences for a query with a hybrid of BM25, a Dirichlet-
 * smoothed unigram language model (LMIR) and a word-bigram context bonus.
 * The ML algorithms (BM25/LM) live here in C, compiled to a tiny wasm
 * binary; JS only tokenizes input (mirrors build.mjs), uploads the pack
 * and copies the answer back.
 *
 * Memory layout (JS-managed linear memory, grow()ed before use):
 *   [0, 64KB)        C stack (--stack-first)
 *   [64KB, 128KB)    C static globals (linker-placed; must not overlap)
 *   [128KB, +pack)   data pack (byte layout built by build.mjs)
 *   [pack_end, ...)  scratch (JS passes base via kb_setup)
 *
 * Exports: kb_setup(pack_base, scratch) kb_load() kb_nsents() kb_answer(q,qlen,out,cap)
 *
 * Build:  clang --target=wasm32 -O3 -fno-builtin -nostdlib -Wl,--no-entry  \
 *            -Wl,--stack-first -Wl,--initial-memory=131072 \
 *            -Wl,--export=kb_setup,-Wl,--export=kb_load,... -o brain.wasm
 */

#define PACK_BASE 131072 /* 0x20000 = end of module's initial memory; statics live in [64KB,128KB) */

typedef unsigned int u32;
typedef unsigned short u16;
typedef unsigned char u8;
typedef signed int i32;
typedef float f32;

static u32 SCRATCH = 0; /* set from JS: PACK_BASE + packSize (aligned) */

static u32 u32_at(u32 off) {
    const u8 *p = (const u8 *)(PACK_BASE + off);
    return (u32)p[0] | ((u32)p[1] << 8) | ((u32)p[2] << 16) | ((u32)p[3] << 24);
}
static u16 u16_at(u32 off) {
    const u8 *p = (const u8 *)(PACK_BASE + off);
    return (u16)(p[0] | ((u16)p[1] << 8));
}
static u8 u8_at(u32 off) { return *(const u8 *)(PACK_BASE + off); }

static void *scr(u32 off) { return (void *)(SCRATCH + off); } /* SCRATCH is absolute (JS: PACK_BASE + packSize, aligned) */
/* scratch layout: f32 scores[nSents max 60000] + u32 qids[64] + f32 idf[64]
 * + u32 sentTok[300] + u32 topIdx[16] + f32 topScore[16] + qtok[64*44] */
#define SCR_SENT 0
#define SCR_QIDS (SCR_SENT + 60000 * 4)
#define SCR_IDF (SCR_QIDS + 256)
#define SCR_TOK (SCR_IDF + 256)
#define SCR_TOPIDX (SCR_TOK + 1200)
#define SCR_TOPSC (SCR_TOPIDX + 64)
#define SCR_QTOK (SCR_TOPSC + 64)
#define SCR_SIZE (SCR_QTOK + MAX_QUERY_TOKENS * 44)

/* ---- pack header offsets (mirror build.mjs) ---- */
#define H_MAGIC 0
#define H_NTERMS 4
#define H_NPOSTS 8
#define H_NSENTS 12
#define H_AVGDL 16
#define H_NPAIRS 20
#define H_VOCAB 24
#define H_DF 28
#define H_POSTOFF 32
#define H_POSTSENT 36
#define H_POSTTF 40
#define H_LM 44
#define H_SENTOFF 48
#define H_SENTTEXT 52
#define H_TOTAL 56

#define MAX_QUERY_TOKENS 64
#define MAX_SENT_TOKENS 300
#define TOP_K 16

static u32 g_nTerms, g_nPosts, g_nSents, g_avgdl, g_nPairs;
static u32 g_vocab, g_df, g_postOff, g_postSent, g_postTf, g_lm, g_sentOff, g_sentText;

static u32 strlen_(const char *s) {
    u32 n = 0;
    while (s[n]) n++;
    return n;
}

/* ---- Unicode-aware lowercase + Turkish diacritics -> ASCII ----
 * ASCII [A-Za-z0-9] and digit are token chars; Turkish UTF-8 byte pairs
 * collapse to one ascii char; anything else is a separator.
 */
static u8 norm_char(const u8 *p, u32 *adv) {
    u8 c = p[0];
    *adv = 1;
    if (c >= 'A' && c <= 'Z') return c + 32;
    if (c < 0x80) return c;
    if (c >= 0xC2) {
        u8 d = p[1];
        *adv = 2;
        if (c == 0xC3) {
            switch (d) {
                case 0xA7: return 'c'; /* ç */
                case 0x87: return 'c'; /* Ç */
                case 0xB6: return 'o'; /* ö */
                case 0x96: return 'o'; /* Ö */
                case 0xBC: return 'u'; /* ü */
                case 0x9C: return 'u'; /* Ü */
                case 0xB1: return 'i'; /* ı */
                case 0xA2: return 'a'; /* â */
                case 0xAE: return 'i'; /* î */
                case 0xBB: return 'u'; /* û */
            }
        } else if (c == 0xC4) {
            switch (d) {
                case 0x9F: return 'g'; /* ğ */
                case 0x9E: return 'g'; /* Ğ */
                case 0xB1: return 'i'; /* ı */
                case 0xB0: return 'i'; /* İ */
            }
        } else if (c == 0xC5) {
            switch (d) {
                case 0x9F: return 's'; /* ş */
                case 0x9E: return 's'; /* Ş */
            }
        }
        return 0;
    }
    return 0;
}

/* tokenize s; writes (start,len) pairs into starts[]/lens[]; returns count.
 * Tokens are suffix-stemmed (stem() below) to match build.mjs.
 * starts[]/lens[] are offsets into the ORIGINAL s (unstemmed bytes). */
static u32 tokenize(const char *s, u32 len, u32 *starts, u32 *lens) {
    u32 n = 0, i = 0, tstart = 0, tlen = 0;
    while (i < len && s[i]) {
        u32 adv;
        u8 c = norm_char((const u8 *)s + i, &adv);
        if ((c >= 'a' && c <= 'z') || (c >= '0' && c <= '9')) {
            if (tlen == 0) tstart = i;
            tlen++;
        } else {
            if (tlen >= 2 && n < MAX_SENT_TOKENS) {
                starts[n] = tstart;
                lens[n] = tlen;
                n++;
            }
            tlen = 0;
        }
        i += adv;
    }
    if (tlen >= 2 && n < MAX_SENT_TOKENS) {
        starts[n] = tstart;
        lens[n] = tlen;
        n++;
    }
    return n;
}

/* Light suffix stemmer — MUST stay byte-identical with stem() in build.mjs.
 * Builds the stem into out[] and returns its length (<= inLen). */
static u32 stem(const u8 *w, u32 len, u8 *out) {
    for (u32 i = 0; i < len; i++) out[i] = w[i];
    if (len <= 4) return len;
    const u8 *end = w + len;
    if (len > 5 && end[-3] == 'i' && end[-2] == 'n' && end[-1] == 'g')
        len -= 3;
    else if (len > 4 && end[-2] == 'e' && end[-1] == 'd')
        len -= 2;
    else if (len > 4 && end[-2] == 'e' && end[-1] == 's')
        len -= 2;
    else if (len > 3 && end[-1] == 's' && !(end[-2] == 's'))
        len -= 1;
    /* y->i after consonant (study->studi, countries->countri); both sides must match */
    if (out[len - 1] == 'y') {
        u8 p = out[len - 2];
        if (p != 'a' && p != 'e' && p != 'i' && p != 'o' && p != 'u') out[len - 1] = 'i';
    }
    return len;
}

/* lowercase copy of tok (len <= 39); returns len. tokenize() returns offsets into
 * the ORIGINAL text, so every consumer must fold case before stem/vocab_find. */
static u32 tok_lower(const u8 *src, u32 len, u8 *dst) {
    for (u32 i = 0; i < len; i++) {
        u8 c = src[i];
        dst[i] = (c >= 'A' && c <= 'Z') ? c + 32 : c;
    }
    return len;
}

/* binary search vocab (NUL-separated, sorted); returns term id or -1 */
/* Corpus stopword list (must mirror STOP in build.mjs): dropped from the
 * vocab at build time, so an OOV stopword is "off-topic noise", not a miss —
 * it must not count against the coverage gate. OOV non-stopwords do. */
static const char *STOP_WORDS = "a,an,the,and,or,of,to,in,on,at,for,with,about,from,by,as,is,are,was,were,be,been,being,am,do,does,did,have,has,had,i,you,he,she,it,we,they,me,him,her,us,them,my,your,his,our,their,this,that,these,those,what,which,who,whom,whose,when,where,why,how,not,no,so,but,if,then,than,there,here,can,could,would,should,will,shall,may,might,must,its,one,just,tell,also,very,really,into,through,up,down,out,off,over,under,too,all,any,both,each,few,more,most,other,some,such,only,own,same,once,still,even,lot";
static u32 is_stop(const u8 *w, u32 len) {
    const char *p = STOP_WORDS;
    while (*p) {
        u32 n = 0;
        while (p[n] && p[n] != ',') n++;
        if (n == len) {
            u32 eq = 1;
            for (u32 k = 0; k < len; k++)
                if ((u8)p[k] != w[k]) { eq = 0; break; }
            if (eq) return 1;
        }
        p += n;
        if (*p) p++;
    }
    return 0;
}

static i32 vocab_find(const u8 *tok, u32 len) {
    u32 lo = 0, hi = g_nTerms;
    while (lo < hi) {
        u32 mid = (lo + hi) / 2;
        u32 off = g_vocab, t = 0;
        while (t < mid) { /* walk to term start */
            while (u8_at(off)) off++;
            off++;
            t++;
        }
        const u8 *term = (const u8 *)(PACK_BASE + off);
        u32 tl = strlen_((const char *)term);
        u32 m = len < tl ? len : tl;
        i32 cmp = 0;
        for (u32 k = 0; k < m; k++) {
            if (tok[k] != term[k]) { cmp = (i32)tok[k] - (i32)term[k]; break; }
        }
        if (cmp == 0) cmp = (i32)len - (i32)tl;
        if (cmp == 0) return (i32)mid;
        if (cmp < 0) hi = mid;
        else lo = mid + 1;
    }
    return -1;
}

/* natural log via exponent extraction + atanh series (error ~1e-6) */
static f32 logf_(f32 x) {
    union { f32 f; u32 u; } b = { .f = x };
    if (x <= 0) return -30.0f;
    i32 e = (i32)((b.u >> 23) & 0xFF) - 127;
    b.u = (b.u & 0x7FFFFF) | 0x3F800000;
    f32 m = b.f;
    f32 y = (m - 1.0f) / (m + 1.0f);
    f32 y2 = y * y;
    f32 lp = y * (2.0f + y2 * (2.0f / 3.0f + y2 * (2.0f / 5.0f + y2 * (2.0f / 7.0f + y2 * (2.0f / 9.0f)))));
    return (f32)e * 0.6931471805599453f + lp;
}

/* ---- exports ---- */
__attribute__((export_name("kb_setup")))
void kb_setup(u32 packBase, u32 scratchBase) { SCRATCH = scratchBase; }

__attribute__((export_name("kb_load")))
u32 kb_load(void) {
    if (u32_at(H_MAGIC) != 0x314b4b59u) return 0;
    g_nTerms = u32_at(H_NTERMS);
    g_nPosts = u32_at(H_NPOSTS);
    g_nSents = u32_at(H_NSENTS);
    g_avgdl = u32_at(H_AVGDL);
    g_nPairs = u32_at(H_NPAIRS);
    g_vocab = u32_at(H_VOCAB);
    g_df = u32_at(H_DF);
    g_postOff = u32_at(H_POSTOFF);
    g_postSent = u32_at(H_POSTSENT);
    g_postTf = u32_at(H_POSTTF);
    g_lm = u32_at(H_LM);
    g_sentOff = u32_at(H_SENTOFF);
    g_sentText = u32_at(H_SENTTEXT);
    if (g_nTerms == 0 || g_nSents == 0 || g_nSents > 60000) return 0;
    if (u32_at(H_TOTAL) < 60) return 0;
    return 1;
}

__attribute__((export_name("kb_nsents")))
u32 kb_nsents(void) { return g_nSents; }

static u32 sent_len(u32 sid) {
    return u32_at(g_sentOff + sid * 4 + 4) - u32_at(g_sentOff + sid * 4);
}
static const char *sent_text(u32 sid) {
    return (const char *)(PACK_BASE + g_sentText + u32_at(g_sentOff + sid * 4));
}

/* bigram pair count lookup (sorted u16 triplets w1,w2,cnt) */
static u32 lm_count(u32 a, u32 b) {
    u32 lo = 0, hi = g_nPairs;
    while (lo < hi) {
        u32 mid = (lo + hi) / 2;
        u32 w1 = u16_at(g_lm + mid * 6);
        u32 w2 = u16_at(g_lm + mid * 6 + 2);
        if (w1 < a || (w1 == a && w2 < b)) lo = mid + 1;
        else hi = mid;
    }
    if (lo < g_nPairs) {
        u32 w1 = u16_at(g_lm + lo * 6);
        u32 w2 = u16_at(g_lm + lo * 6 + 2);
        if (w1 == a && w2 == b) return u16_at(g_lm + lo * 6 + 4);
    }
    return 0;
}

/*
 * kb_answer(q, qlen, out, outCap) -> bytes written or 0.
 * out layout: [u32 sentId][f32 score][sentence utf8][0]
 */
__attribute__((export_name("kb_answer")))
u32 kb_answer(const char *q, u32 qlen, u8 *out, u32 outCap) {
    if (!g_nSents || !q || qlen == 0 || qlen > 8000) return 0;
    if (outCap < 12) return 0;

    u32 starts[MAX_QUERY_TOKENS];
    u32 lens[MAX_QUERY_TOKENS];
    u32 nq = tokenize(q, qlen, starts, lens);
    if (nq == 0) return 0;

    /* unique query term ids + idfs (keep only content terms: idf >= 1).
     * qtok[] records EVERY distinct content stem (found or OOV) so the
     * coverage gate can divide by the full query, not just vocab hits. */
    u32 *qids = (u32 *)scr(SCR_QIDS);
    f32 *qidf = (f32 *)scr(SCR_IDF);
    u8 *qtok = (u8 *)scr(SCR_QTOK); /* MAX_QUERY_TOKENS slots x 44B: [u32 len][40B stem] */
    u32 nqid = 0, nContent = 0;
    f32 sumIdf = 0;
    u8 stemBuf[40];
    for (u32 i = 0; i < nq; i++) {
        u32 tl = lens[i];
        if (tl > 39) tl = 39;
        tok_lower((const u8 *)q + starts[i], tl, stemBuf);
        tl = stem(stemBuf, tl, stemBuf);
        i32 id = vocab_find(stemBuf, tl);
        if (id < 0 && tl > 4) {
            /* compound-token fallback: nodejs -> node (longest prefix >= 4 in vocab) */
            u32 pl = tl - 1;
            while (pl >= 4 && (id = vocab_find(stemBuf, pl)) < 0) pl--;
            if (pl < 4) id = -1;
            else tl = pl; /* record the matched prefix as the coverage stem */
        }
        /* dedupe content stems (stopwords are noise, not coverage misses) */
        if (is_stop(stemBuf, tl)) continue;
        u32 seen = 0;
        for (u32 j = 0; j < nContent; j++) {
            u32 jl = *(u32 *)(qtok + j * 44);
            if (jl == tl) {
                u32 eq = 1;
                for (u32 b = 0; b < tl; b++)
                    if (qtok[j * 44 + 4 + b] != stemBuf[b]) { eq = 0; break; }
                if (eq) { seen = 1; break; }
            }
        }
        if (!seen && nContent < MAX_QUERY_TOKENS) {
            *(u32 *)(qtok + nContent * 44) = tl;
            for (u32 b = 0; b < tl; b++) qtok[nContent * 44 + 4 + b] = stemBuf[b];
            nContent++;
        }
        if (seen) continue;
        if (id < 0) continue; /* OOV non-stopword: counts against coverage, no postings */
        u32 df = u32_at(g_df + (u32)id * 4);
        f32 idf = logf_(1.0f + ((f32)g_nSents - (f32)df + 0.5f) / ((f32)df + 0.5f));
        if (idf < 1.0f) continue; /* stopword: dilutes ranking, never gates */
        qids[nqid] = (u32)id;
        qidf[nqid] = idf;
        sumIdf += idf;
        nqid++;
        if (nqid >= MAX_QUERY_TOKENS) break;
    }
    if (nqid == 0 || nContent == 0) return 0;

    /* ---- BM25 over all sentences ---- */
    f32 *scores = (f32 *)scr(SCR_SENT);
    const f32 k1 = 1.2f, b = 0.75f;
    const f32 avgdl = (f32)g_avgdl / 100.0f;
    for (u32 s = 0; s < g_nSents; s++) scores[s] = 0.0f;

    for (u32 t = 0; t < nqid; t++) {
        u32 id = qids[t];
        u32 off = u32_at(g_postOff + id * 4);
        u32 end = u32_at(g_postOff + (id + 1) * 4);
        for (u32 p = off; p < end; p++) {
            u32 sid = u32_at(g_postSent + p * 4);
            u32 tf = u16_at(g_postTf + p * 2);
            f32 dl = (f32)sent_len(sid);
            f32 denom = (f32)tf + k1 * (1.0f - b + b * dl / (avgdl > 0 ? avgdl : 1.0f));
            scores[sid] += qidf[t] * ((f32)tf * (k1 + 1.0f)) / denom;
        }
    }

    /* ---- top-K candidates by BM25 ---- */
    u32 *topIdx = (u32 *)scr(SCR_TOPIDX);
    f32 *topSc = (f32 *)scr(SCR_TOPSC);
    for (u32 k = 0; k < TOP_K; k++) { topIdx[k] = 0; topSc[k] = 0.0f; }
    for (u32 s = 0; s < g_nSents; s++) {
        f32 sc = scores[s];
        if (sc <= 0) continue;
        for (u32 k = 0; k < TOP_K; k++) {
            if (sc > topSc[k]) {
                for (u32 m = TOP_K - 1; m > k; m--) {
                    topIdx[m] = topIdx[m - 1];
                    topSc[m] = topSc[m - 1];
                }
                topIdx[k] = s;
                topSc[k] = sc;
                break;
            }
        }
    }

    /* ---- LM re-rank of candidates: Dirichlet unigram + bigram bonus ---- */
    const f32 MU = 100.0f;
    u32 bestSid = 0xFFFFFFFF;
    f32 bestScore = 0.0f;
    u32 bestSentTf[MAX_QUERY_TOKENS];
    u32 *tokIds = (u32 *)scr(SCR_TOK);
    for (u32 k = 0; k < TOP_K; k++) {
        u32 sid = topIdx[k];
        if (topSc[k] <= 0) continue;

        const char *stext = sent_text(sid);
        u32 slen = sent_len(sid);
        u32 ss[MAX_SENT_TOKENS], sl[MAX_SENT_TOKENS];
        u32 nst = tokenize(stext, slen < 4096 ? slen : 4096, ss, sl);
        if (nst == 0) continue;

        /* sentence term ids + tf per query id */
        u32 sentTf[MAX_QUERY_TOKENS];
        for (u32 t = 0; t < nqid; t++) sentTf[t] = 0;
        u32 ntok = 0;
        for (u32 i = 0; i < nst && ntok < MAX_SENT_TOKENS; i++) {
            u32 tl2 = sl[i];
            if (tl2 > 39) tl2 = 39;
            tok_lower((const u8 *)stext + ss[i], tl2, stemBuf);
            tl2 = stem(stemBuf, tl2, stemBuf);
            i32 id = vocab_find(stemBuf, tl2);
            tokIds[ntok] = (u32)id; /* -1 wraps; compare via cast */
            ntok++;
            if (id >= 0) {
                for (u32 t = 0; t < nqid; t++)
                    if (qids[t] == (u32)id) sentTf[t]++;
            }
        }

        /* unigram LM (query likelihood, Dirichlet) */
        f32 lmz = 0.0f;
        for (u32 t = 0; t < nqid; t++) {
            f32 c = (f32)sentTf[t];
            f32 pcoll = (f32)u32_at(g_df + qids[t] * 4) / (f32)g_nSents;
            f32 p = (c + MU * pcoll) / ((f32)ntok + MU);
            lmz += logf_(p);
        }

        /* bigram context bonus: query pairs co-present / adjacent */
        f32 bon = 0.0f;
        for (u32 t = 0; t + 1 < nqid; t++) {
            if (sentTf[t] > 0 && sentTf[t + 1] > 0) bon += 0.4f;
            for (u32 i = 0; i + 1 < ntok; i++) {
                if ((u32)tokIds[i] == qids[t] && (u32)tokIds[i + 1] == qids[t + 1]) {
                    bon += 0.8f;
                    break;
                }
            }
        }

        f32 lmzAvg = lmz / (f32)nqid; /* per-term log-prob, ~ -2..-7 */
        f32 merged = topSc[k] / (sumIdf > 0 ? sumIdf : 1.0f) + 0.10f * lmzAvg + bon;
        if (merged > bestScore) {
            bestScore = merged;
            bestSid = sid;
            for (u32 t = 0; t < nqid; t++) bestSentTf[t] = sentTf[t];
        }
    }

    if (bestSid == 0xFFFFFFFF) return 0;

    /* ---- threshold: coverage gate + confidence floor.
     * Coverage: answer must contain >= half the query's content stems
     * (OOV included in the denominator) — separates on-topic hits from
     * lexical coincidences; an absolute floor alone cannot. */
    f32 score01 = bestScore;
    if (nContent > 1) {
        u32 matched = 0;
        for (u32 t = 0; t < nqid; t++)
            if (bestSentTf[t] > 0) matched++;
        if (matched * 2 < nContent) return 0;
        if (score01 < 0.04f) return 0;
    } else {
        u32 df0 = u32_at(g_df + qids[0] * 4);
        if ((f32)df0 / (f32)g_nSents > 0.05f) return 0; /* common word alone is not a signal */
        if (qidf[0] < 4.6f) return 0;  /* single generic word (today/live/edge) is not a topic signal */
        if (score01 < 0.35f) return 0;
    }

    /* ---- write out: [u32 sid][f32 score][text\0] ---- */
    const char *best = sent_text(bestSid);
    u32 blen = sent_len(bestSid);
    if (blen > 400) blen = 400;
    u32 total = 8 + blen + 1;
    if (total > outCap) {
        blen = outCap - 9;
        while (blen > 0 && best[blen] != ' ') blen--;
        total = 8 + blen + 1;
    }
    out[0] = (u8)(bestSid & 0xFF);
    out[1] = (u8)((bestSid >> 8) & 0xFF);
    out[2] = (u8)((bestSid >> 16) & 0xFF);
    out[3] = (u8)((bestSid >> 24) & 0xFF);
    union { f32 f; u32 u; } sc = { .f = score01 };
    out[4] = (u8)(sc.u & 0xFF);
    out[5] = (u8)((sc.u >> 8) & 0xFF);
    out[6] = (u8)((sc.u >> 16) & 0xFF);
    out[7] = (u8)((sc.u >> 24) & 0xFF);
    for (u32 i = 0; i < blen; i++) out[8 + i] = (u8)best[i];
    out[8 + blen] = 0;
    return total;
}
