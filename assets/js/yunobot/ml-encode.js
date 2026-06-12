(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  function normalizeText(text) {
    return String(text || '').toLowerCase().trim();
  }

  function tokenize(text) {
    return normalizeText(text)
      .replace(/[^a-z0-9\s-]/g, ' ')
      .split(/\s+/)
      .filter(Boolean);
  }

  function hashToken(token) {
    let h = 2166136261;
    for (let i = 0; i < token.length; i += 1) {
      h ^= token.charCodeAt(i);
      h = (h * 16777619) >>> 0;
    }
    return h >>> 0;
  }

  function editDistance(a, b) {
    const m = a.length;
    const n = b.length;
    if (!m) return n;
    if (!n) return m;

    const dp = Array.from({ length: m + 1 }, function () { return new Array(n + 1).fill(0); });
    for (let i = 0; i <= m; i += 1) dp[i][0] = i;
    for (let j = 0; j <= n; j += 1) dp[0][j] = j;

    for (let i = 1; i <= m; i += 1) {
      for (let j = 1; j <= n; j += 1) {
        const cost = a[i - 1] === b[j - 1] ? 0 : 1;
        dp[i][j] = Math.min(dp[i - 1][j] + 1, dp[i][j - 1] + 1, dp[i - 1][j - 1] + cost);
      }
    }
    return dp[m][n];
  }

  function normalizeTokenNoise(token) {
    let t = String(token || '');

    // Compress repeated characters in noisy text like "whoooo".
    t = t.replace(/(.)\1{2,}/g, '$1$1');
    t = t.replace(/[^a-z0-9-]/g, '');
    t = t.replace(/z{2,}$/g, 's');

    if (/^who+/.test(t)) return 'who';
    if (/^wh+a+t+/.test(t)) return 'what';
    if (/^yun/.test(t)) return 'yunus';
    if (/^sitee+|^web+site+/.test(t)) return 'site';
    return t;
  }

  function createCanonicalizer(vocabulary) {
    const knownTerms = new Set(vocabulary || []);

    function canonicalizeToken(token) {
      const t = normalizeTokenNoise(token);
      if (!t) return '';
      if (knownTerms.has(t)) return t;
      if (t.length <= 3) return t;

      let best = '';
      let bestDist = Infinity;
      knownTerms.forEach(function (term) {
        const d = editDistance(t, term);
        if (d < bestDist) {
          bestDist = d;
          best = term;
        }
      });

      // Conservative fuzzy matching keeps intent stable and avoids wrong rewrites.
      const maxDist = t.length >= 9 ? 2 : 1;
      const ratio = best ? (bestDist / Math.max(t.length, best.length)) : 1;
      if (bestDist <= maxDist && ratio <= 0.28) return best;
      return t;
    }

    function canonicalizeText(text) {
      const rawTokens = tokenize(text);
      return rawTokens.map(canonicalizeToken).filter(Boolean).join(' ');
    }

    return {
      knownTerms: knownTerms,
      canonicalizeToken: canonicalizeToken,
      canonicalizeText: canonicalizeText
    };
  }

  function buildDocFreq(corpus) {
    const tokenDocFreq = new Map();
    corpus.forEach(function (item) {
      const unique = new Set(tokenize(item.text));
      unique.forEach(function (token) {
        tokenDocFreq.set(token, (tokenDocFreq.get(token) || 0) + 1);
      });
    });
    return tokenDocFreq;
  }

  function encode(text, tokenDocFreq, totalDocs, HASH_DIM) {
    const tokens = tokenize(text);
    const tf = new Map();

    for (let i = 0; i < tokens.length; i += 1) {
      const t = tokens[i];
      tf.set(t, (tf.get(t) || 0) + 1);
    }

    const v = new Float32Array(HASH_DIM);
    tf.forEach(function (count, token) {
      const df = tokenDocFreq.get(token) || 0.5;
      const idf = Math.log((totalDocs + 1) / (df + 1)) + 1;
      const weight = count * idf;
      const h = hashToken(token);
      const idx = h % HASH_DIM;
      const sign = (h & 1) ? 1 : -1;
      v[idx] += sign * weight;
    });

    return { vector: v, tokens: tokens };
  }

  window.YunoML.normalizeText = normalizeText;
  window.YunoML.tokenize = tokenize;
  window.YunoML.hashToken = hashToken;
  window.YunoML.createCanonicalizer = createCanonicalizer;
  window.YunoML.buildDocFreq = buildDocFreq;
  window.YunoML.encode = encode;

  window.YunoMLFiles.push({
    id: 'encode',
    title: 'Encode Stage',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-encode.js',
    source: [
      normalizeText,
      tokenize,
      hashToken,
      normalizeTokenNoise,
      createCanonicalizer,
      buildDocFreq,
      encode
    ].map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
