(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  function buildIntentAdjacency(intents, edgeList) {
    const indexByTag = {};
    intents.forEach(function (intent, idx) { indexByTag[intent.tag] = idx; });

    const adjacency = Array.from({ length: intents.length }, function () {
      return Array.from({ length: intents.length }, function () { return 0; });
    });

    (edgeList || []).forEach(function (edge) {
      const a = indexByTag[edge[0]];
      const b = indexByTag[edge[1]];
      const w = Number(edge[2]);
      if (typeof a !== 'number' || typeof b !== 'number' || !Number.isFinite(w)) return;
      adjacency[a][b] = w;
      adjacency[b][a] = w;
    });

    return { adjacency: adjacency, indexByTag: indexByTag };
  }

  function diffusionRefine(calibrated, adjacency, steps) {
    const totalSteps = Number.isFinite(steps) ? Math.max(1, Math.floor(steps)) : 4;
    const refined = calibrated.map(function (item) { return item.score; });
    const observation = calibrated.map(function (item) { return item.score; });

    // Graph-based smoothing with conserved signal mass.
    for (let step = 0; step < totalSteps; step += 1) {
      const alpha = 0.68 - (step * 0.1);
      const beta = 0.22 + (step * 0.03);
      const gamma = 1 - alpha - beta;
      const nextScores = refined.slice();

      for (let i = 0; i < refined.length; i += 1) {
        let neighborhood = 0;
        let norm = 1;

        for (let j = 0; j < refined.length; j += 1) {
          const w = adjacency[i][j];
          if (!w) continue;
          neighborhood += refined[j] * w;
          norm += w;
        }

        neighborhood /= norm;
        nextScores[i] = (alpha * refined[i]) + (beta * observation[i]) + (gamma * neighborhood);
      }

      for (let i = 0; i < refined.length; i += 1) {
        refined[i] = nextScores[i];
      }
    }

    return refined;
  }

  function selectBestIntent(refinedScores, intents) {
    let bestIdx = 0;
    let bestScore = refinedScores[0] || 0;

    for (let i = 1; i < refinedScores.length; i += 1) {
      if (refinedScores[i] > bestScore) {
        bestIdx = i;
        bestScore = refinedScores[i];
      }
    }

    return {
      tag: intents[bestIdx].tag,
      confidence: bestScore,
      scores: refinedScores
    };
  }

  window.YunoML.buildIntentAdjacency = buildIntentAdjacency;
  window.YunoML.diffusionRefine = diffusionRefine;
  window.YunoML.selectBestIntent = selectBestIntent;

  window.YunoMLFiles.push({
    id: 'refine',
    title: 'Diffusion Refiner',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-refine.js',
    source: [buildIntentAdjacency, diffusionRefine, selectBestIntent]
      .map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
