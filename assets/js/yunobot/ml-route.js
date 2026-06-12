(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  function softmax(arr) {
    const max = Math.max.apply(null, arr);
    const exps = arr.map(function (v) { return Math.exp(v - max); });
    const sum = exps.reduce(function (a, b) { return a + b; }, 0) || 1;
    return exps.map(function (v) { return v / sum; });
  }

  function cosineSim(a, b) {
    let dot = 0;
    let an = 0;
    let bn = 0;
    for (let i = 0; i < a.length; i += 1) {
      dot += a[i] * b[i];
      an += a[i] * a[i];
      bn += b[i] * b[i];
    }
    const denom = Math.sqrt(an) * Math.sqrt(bn);
    if (!denom) return 0;
    return dot / denom;
  }

  function routeAttention(queryVector, allowedRoutes, pageRoutes, routeEmbeddings, HASH_DIM) {
    const keys = allowedRoutes.length ? allowedRoutes : pageRoutes.map(function (r) { return r.id; });
    const scores = [];

    for (let i = 0; i < keys.length; i += 1) {
      const id = keys[i];
      const score = cosineSim(queryVector, routeEmbeddings[id]) / Math.sqrt(HASH_DIM);
      scores.push(score);
    }

    const weights = softmax(scores);
    let bestRouteId = keys[0];
    let bestWeight = weights[0] || 0;

    for (let i = 1; i < keys.length; i += 1) {
      if (weights[i] > bestWeight) {
        bestWeight = weights[i];
        bestRouteId = keys[i];
      }
    }

    return { routeId: bestRouteId, confidence: bestWeight };
  }

  function resolveRouteCandidatesFromText(text, pageRoutes, normalizeText) {
    const lower = normalizeText(text);
    const candidates = [];

    for (let i = 0; i < pageRoutes.length; i += 1) {
      const route = pageRoutes[i];
      let score = 0;

      if (lower === route.id || lower === route.name.toLowerCase()) score += 8;
      if (lower.includes(route.id)) score += 4;
      if (lower.includes(route.name.toLowerCase())) score += 3;

      for (let j = 0; j < route.keywords.length; j += 1) {
        const key = route.keywords[j];
        if (lower === key) score += 3;
        else if (lower.includes(key)) score += 1.5;
      }

      if (score > 0) candidates.push({ route: route, score: score });
    }

    candidates.sort(function (a, b) { return b.score - a.score; });
    return candidates;
  }

  function resolveRouteFromText(text, pageRoutes, normalizeText) {
    const candidates = resolveRouteCandidatesFromText(text, pageRoutes, normalizeText);
    return candidates.length ? candidates[0].route : null;
  }

  window.YunoML.softmax = softmax;
  window.YunoML.cosineSim = cosineSim;
  window.YunoML.routeAttention = routeAttention;
  window.YunoML.resolveRouteCandidatesFromText = resolveRouteCandidatesFromText;
  window.YunoML.resolveRouteFromText = resolveRouteFromText;

  window.YunoMLFiles.push({
    id: 'route',
    title: 'Route Stage',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-route.js',
    source: [softmax, cosineSim, routeAttention, resolveRouteCandidatesFromText, resolveRouteFromText]
      .map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
