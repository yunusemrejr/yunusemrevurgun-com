(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  function createBrain(BASE_PATH) {
    const Y = window.YunoML || {};

    const HASH_DIM = 128;
    const pageRoutes = [
      { id: 'home', name: 'Home', url: BASE_PATH + 'home', keywords: ['home', 'start', 'landing', 'main'] },
      { id: 'about', name: 'About', url: BASE_PATH + 'about', keywords: ['about', 'bio', 'who', 'yunus', 'profile', 'background'] },
      { id: 'portfolio', name: 'Portfolio', url: BASE_PATH + 'portfolio', keywords: ['portfolio', 'projects', 'work', 'showcase', 'case'] },
      { id: 'gallery', name: 'Gallery', url: BASE_PATH + 'gallery', keywords: ['gallery', 'images', 'photos', 'visual'] },
      { id: 'travel', name: 'Travel', url: BASE_PATH + 'travel', keywords: ['travel', 'map', 'countries', 'routes', 'locations'] },
      { id: 'blog', name: 'Blog', url: BASE_PATH + 'blog', keywords: ['blog', 'journal', 'posts', 'articles', 'writing'] },
      { id: 'updates', name: 'Updates', url: BASE_PATH + 'updates', keywords: ['updates', 'micro', 'news', 'logs', 'change'] },
      { id: 'post-code', name: 'Post-Code', url: BASE_PATH + 'post-code', keywords: ['post-code', 'math', 'fundamentals', 'architecture', 'theory'] },
      { id: 'contact', name: 'Contact', url: BASE_PATH + 'contact', keywords: ['contact', 'reach', 'email', 'message'] },
      { id: 'yunobot', name: 'YunoBot', url: BASE_PATH + 'yunobot', keywords: ['yunobot', 'assistant', 'terminal', 'ai'] }
    ];

    const intents = [
      { tag: 'greet', keywords: ['hello', 'hi', 'hey', 'yo', 'sup'], routeBias: ['home', 'about'] },
      { tag: 'identity', keywords: ['who', 'yunus', 'bio', 'background', 'education', 'history'], routeBias: ['about'] },
      { tag: 'portfolio', keywords: ['project', 'projects', 'portfolio', 'work', 'build', 'featured', 'stack'], routeBias: ['portfolio'] },
      { tag: 'writing', keywords: ['blog', 'journal', 'article', 'post', 'long form', 'write'], routeBias: ['blog'] },
      { tag: 'updates', keywords: ['updates', 'micro', 'recent', 'changelog', 'quick', 'latest'], routeBias: ['updates'] },
      { tag: 'visual', keywords: ['gallery', 'image', 'photo', 'visual', 'pictures'], routeBias: ['gallery'] },
      { tag: 'travel', keywords: ['travel', 'country', 'countries', 'map', 'route', 'visited'], routeBias: ['travel'] },
      { tag: 'learning', keywords: ['math', 'foundation', 'architecture', 'theory', 'post-code', 'learning'], routeBias: ['post-code', 'blog'] },
      { tag: 'contact', keywords: ['contact', 'email', 'reach', 'collab', 'consult', 'message'], routeBias: ['contact'] },
      { tag: 'navigate', keywords: ['go', 'open', 'take', 'route', 'navigate', 'bring'], routeBias: ['home', 'about', 'portfolio', 'blog', 'updates', 'travel', 'gallery', 'post-code', 'contact'] },
      { tag: 'help', keywords: ['help', 'commands', 'how', 'options', 'tools'], routeBias: ['yunobot'] }
    ];

    const routeById = {};
    pageRoutes.forEach(function (r) { routeById[r.id] = r; });

    const vocabulary = [];
    intents.forEach(function (intent) {
      vocabulary.push(intent.tag);
      intent.keywords.forEach(function (k) { vocabulary.push(k); });
      intent.routeBias.forEach(function (k) { vocabulary.push(k); });
    });
    pageRoutes.forEach(function (route) {
      vocabulary.push(route.id);
      route.keywords.forEach(function (k) { vocabulary.push(k); });
    });

    const canonicalizer = Y.createCanonicalizer(vocabulary);

    const corpus = [];
    intents.forEach(function (intent) {
      corpus.push({ type: 'intent', id: intent.tag, text: intent.keywords.join(' ') + ' ' + intent.routeBias.join(' ') });
    });
    pageRoutes.forEach(function (route) {
      corpus.push({ type: 'route', id: route.id, text: route.keywords.join(' ') + ' ' + route.name.toLowerCase() });
    });

    const tokenDocFreq = Y.buildDocFreq(corpus);
    const totalDocs = corpus.length || 1;

    const intentEmbeddings = {};
    intents.forEach(function (intent) {
      intentEmbeddings[intent.tag] = Y.encode(intent.keywords.join(' ') + ' ' + intent.routeBias.join(' '), tokenDocFreq, totalDocs, HASH_DIM).vector;
    });

    const routeEmbeddings = {};
    pageRoutes.forEach(function (route) {
      routeEmbeddings[route.id] = Y.encode(route.keywords.join(' ') + ' ' + route.name.toLowerCase(), tokenDocFreq, totalDocs, HASH_DIM).vector;
    });

    const nbLikelihood = Y.buildNaiveBayes(intents, routeById);
    const adjacencyGraph = Y.buildIntentAdjacency(intents, [
      ['navigate', 'portfolio', 0.8], ['navigate', 'writing', 0.8], ['navigate', 'updates', 0.75],
      ['navigate', 'visual', 0.75], ['navigate', 'travel', 0.75], ['navigate', 'contact', 0.8],
      ['identity', 'help', 0.35], ['learning', 'writing', 0.55], ['portfolio', 'learning', 0.5],
      ['greet', 'help', 0.65], ['greet', 'navigate', 0.4], ['help', 'navigate', 0.7], ['updates', 'writing', 0.45]
    ]);

    const responseBanks = Y.buildResponseBanks();

    const explicitNavPatterns = [
      /\b(go to|open|take me to|navigate to|route to)\s+([a-z0-9- ]+)/i,
      /^\/go\s+([a-z0-9- ]+)/i
    ];

    function parseCommand(input, state) {
      const trimmed = String(input || '').trim();
      if (!trimmed.startsWith('/')) return null;

      const parts = trimmed.split(/\s+/);
      const cmd = Y.normalizeText(parts[0]);
      const args = parts.slice(1);

      if (cmd === '/help' || cmd === '/tools') {
        return { response: 'Commands: /go <page>, /search <query> [all|blog|updates|portfolio], /aim <balanced|action|deep>, /where.', confidence: 1, intentTag: 'help' };
      }

      if (cmd === '/where') {
        return {
          response: 'Current aim=' + state.aim + ', goal=' + state.goal + '. Available pages: ' + pageRoutes.map(function (p) { return p.id; }).join(', '),
          confidence: 1,
          intentTag: 'help'
        };
      }

      if (cmd === '/aim') {
        const nextAim = Y.normalizeText(args[0] || '');
        if (!['balanced', 'action', 'deep'].includes(nextAim)) {
          return { response: 'Invalid aim. Use: balanced, action, deep.', confidence: 1, intentTag: 'help' };
        }
        state.aim = nextAim;
        return { response: 'Aim updated: ' + nextAim + '.', confidence: 1, intentTag: 'help' };
      }

      if (cmd === '/go' || cmd === '/goto' || cmd === '/open') {
        const route = Y.resolveRouteFromText(args.join(' '), pageRoutes, Y.normalizeText);
        if (!route) return { response: 'Unknown page. Try /tools for valid targets.', confidence: 0.8, intentTag: 'navigate' };
        return {
          response: 'Tool call -> navigate: ' + route.name,
          confidence: 1,
          intentTag: 'navigate',
          toolCall: { name: 'navigate', args: { url: route.url, page: route.id }, auto: true }
        };
      }

      if (cmd === '/search' || cmd === '/find') {
        const typeHint = Y.normalizeText(args[args.length - 1] || '');
        const allowedTypes = ['all', 'blog', 'updates', 'portfolio'];
        const type = allowedTypes.includes(typeHint) ? typeHint : 'all';
        const queryParts = allowedTypes.includes(typeHint) ? args.slice(0, -1) : args;
        const query = queryParts.join(' ').trim();
        if (!query) return { response: 'Usage: /search <query> [all|blog|updates|portfolio]', confidence: 1, intentTag: 'help' };

        const url = BASE_PATH + 'search?q=' + encodeURIComponent(query) + '&type=' + encodeURIComponent(type);
        return {
          response: 'Tool call -> site_search: "' + query + '" (' + type + ')',
          confidence: 1,
          intentTag: 'help',
          toolCall: { name: 'site_search', args: { url: url, query: query, type: type }, auto: true }
        };
      }

      return { response: 'Unknown command. Use /help.', confidence: 1, intentTag: 'help' };
    }

    function infer(message, state) {
      const repair = Y.handleConversationRepair(message, state, routeById, Y.tokenize, Y.normalizeText, function (intentTag, st, route, sig) {
        return Y.buildCompositionalResponse(intentTag, st, route, sig, Y.hashToken, responseBanks);
      });
      if (repair) return repair;

      const command = parseCommand(message, state);
      if (command) {
        if (command.toolCall && command.toolCall.args && command.toolCall.args.page) state.lastRoute = command.toolCall.args.page;
        if (command.intentTag) state.lastIntent = command.intentTag;
        return command;
      }

      const naturalDirective = Y.parseNaturalDirective(message, state, {
        BASE_PATH: BASE_PATH,
        normalizeText: Y.normalizeText,
        tokenize: Y.tokenize,
        canonicalizeText: canonicalizer.canonicalizeText,
        routeById: routeById,
        resolveRouteCandidatesFromText: function (text) {
          return Y.resolveRouteCandidatesFromText(text, pageRoutes, Y.normalizeText);
        },
        buildCompositionalResponse: function (intentTag, st, route, sig) {
          return Y.buildCompositionalResponse(intentTag, st, route, sig, Y.hashToken, responseBanks);
        }
      });

      if (naturalDirective) {
        if (naturalDirective.toolCall && naturalDirective.toolCall.args && naturalDirective.toolCall.args.page) state.lastRoute = naturalDirective.toolCall.args.page;
        if (naturalDirective.intentTag) state.lastIntent = naturalDirective.intentTag;
        return naturalDirective;
      }

      const canonical = canonicalizer.canonicalizeText(message);
      const lower = Y.normalizeText(canonical);

      for (let i = 0; i < explicitNavPatterns.length; i += 1) {
        const match = lower.match(explicitNavPatterns[i]);
        if (!match) continue;
        const routeText = match[2] || match[1] || '';
        const route = Y.resolveRouteFromText(routeText, pageRoutes, Y.normalizeText);
        if (route) {
          state.lastRoute = route.id;
          state.lastIntent = 'navigate';
          return { response: 'Tool call -> navigate: ' + route.name, confidence: 1, toolCall: { name: 'navigate', args: { url: route.url, page: route.id }, auto: true } };
        }
      }

      const encoded = Y.encode(canonical, tokenDocFreq, totalDocs, HASH_DIM);
      const tokens = encoded.tokens;
      if (!tokens.length) {
        return { response: Y.conversationalFallback(message, state, Y.normalizeText), confidence: 0.45 };
      }

      state.aim = Y.detectAim(message, state.aim, Y.normalizeText);
      state.goal = Y.detectGoal(tokens);

      const classic = Y.classifyClassic(encoded.vector, tokens, intents, intentEmbeddings, Y.cosineSim, nbLikelihood, tokenDocFreq);
      const refinedScores = Y.diffusionRefine(classic, adjacencyGraph.adjacency, 4);
      const intentResult = Y.selectBestIntent(refinedScores, intents);
      const intent = intents.find(function (item) { return item.tag === intentResult.tag; }) || intents[0];
      const attention = Y.routeAttention(encoded.vector, intent.routeBias, pageRoutes, routeEmbeddings, HASH_DIM);
      const route = routeById[attention.routeId] || routeById.home;

      const boilerplateKind = Y.detectBoilerplateNeed(tokens);
      if (boilerplateKind) {
        const built = Y.composeBoilerplate(boilerplateKind, {
          tokens: tokens,
          aim: state.aim,
          goal: state.goal,
          intentScore: intentResult.confidence,
          routeScore: attention.confidence
        }, Y.hashToken);

        if (built) {
          state.lastRoute = boilerplateKind === 'identity' ? 'about' : 'home';
          state.lastIntent = intentResult.tag;
          return { response: built, confidence: Math.max(0.78, intentResult.confidence) };
        }
      }

      const conf = Math.max(0, Math.min(1, (0.75 * intentResult.confidence) + (0.25 * attention.confidence)));
      const policy = Y.planPolicy(state.aim, state.goal, conf);
      let response = Y.buildCompositionalResponse(intent.tag, state, route, { tokens: tokens, goal: state.goal }, Y.hashToken, responseBanks);

      const wantsTool = /\b(open|go|navigate|take|route|show me)\b/.test(lower) || intent.tag === 'navigate';
      const toolCall = wantsTool ? { name: 'navigate', args: { url: route.url, page: route.id }, auto: policy.autoTool } : null;

      if (!wantsTool && conf < 0.48) {
        return { response: Y.conversationalFallback(message, state, Y.normalizeText), confidence: conf };
      }

      if (toolCall && !toolCall.auto) {
        response += ' Suggestion: type /go ' + route.id + ' to execute.';
      }

      // Runtime memory layer for conversational continuity.
      state.lastRoute = route.id;
      state.lastIntent = intent.tag;

      return { response: response, confidence: conf, toolCall: toolCall };
    }

    return {
      infer: infer,
      routes: pageRoutes.map(function (r) { return { id: r.id, name: r.name }; })
    };
  }

  window.YunoML.createBrain = createBrain;

  window.YunoMLFiles.push({
    id: 'runtime',
    title: 'Tool + Memory Runtime',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-runtime.js',
    source: [createBrain].map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
