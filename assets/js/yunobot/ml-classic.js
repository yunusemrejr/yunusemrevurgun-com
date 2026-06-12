(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  function sigmoid(x) {
    return 1 / (1 + Math.exp(-x));
  }

  function buildNaiveBayes(intents, routeById) {
    const nbLikelihood = {};

    intents.forEach(function (intent) {
      const tokenCounts = new Map();
      let total = 0;

      intent.keywords.forEach(function (token) {
        tokenCounts.set(token, (tokenCounts.get(token) || 0) + 2);
        total += 2;
      });

      intent.routeBias.forEach(function (routeId) {
        const route = routeById[routeId];
        if (!route) return;
        route.keywords.forEach(function (token) {
          tokenCounts.set(token, (tokenCounts.get(token) || 0) + 1);
          total += 1;
        });
      });

      nbLikelihood[intent.tag] = { counts: tokenCounts, total: total || 1 };
    });

    return nbLikelihood;
  }

  function naiveBayesScore(tokens, tag, nbLikelihood, tokenDocFreq) {
    const data = nbLikelihood[tag];
    if (!data) return -10;

    const vocabSize = tokenDocFreq.size || 1;
    let score = 0;

    for (let i = 0; i < tokens.length; i += 1) {
      const t = tokens[i];
      const count = data.counts.get(t) || 0;
      score += Math.log((count + 1) / (data.total + vocabSize));
    }

    return score;
  }

  function classifyClassic(queryVector, tokens, intents, intentEmbeddings, cosineSim, nbLikelihood, tokenDocFreq) {
    const rawScores = intents.map(function (intent) {
      const embScore = cosineSim(queryVector, intentEmbeddings[intent.tag]);
      const nbScore = naiveBayesScore(tokens, intent.tag, nbLikelihood, tokenDocFreq);
      const lexicalScore = intent.keywords.reduce(function (acc, k) {
        return acc + (tokens.includes(k) ? 1 : 0);
      }, 0) / Math.max(1, intent.keywords.length);

      return {
        tag: intent.tag,
        embScore: embScore,
        nbScore: nbScore,
        lexicalScore: lexicalScore
      };
    });

    const nbMax = Math.max.apply(null, rawScores.map(function (s) { return s.nbScore; }));
    const nbMin = Math.min.apply(null, rawScores.map(function (s) { return s.nbScore; }));
    const nbRange = Math.max(1e-6, nbMax - nbMin);

    return rawScores.map(function (s) {
      const nbNorm = (s.nbScore - nbMin) / nbRange;
      const mix = (0.55 * s.embScore) + (0.3 * nbNorm) + (0.15 * s.lexicalScore);
      return { tag: s.tag, score: sigmoid(2.2 * mix - 0.9) };
    });
  }

  window.YunoML.sigmoid = sigmoid;
  window.YunoML.buildNaiveBayes = buildNaiveBayes;
  window.YunoML.naiveBayesScore = naiveBayesScore;
  window.YunoML.classifyClassic = classifyClassic;

  window.YunoMLFiles.push({
    id: 'classic',
    title: 'Classic ML Blend',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-classic.js',
    source: [sigmoid, buildNaiveBayes, naiveBayesScore, classifyClassic]
      .map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
