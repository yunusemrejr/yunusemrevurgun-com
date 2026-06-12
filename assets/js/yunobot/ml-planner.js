(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  /**
   * Detect user goal from tokens (IMPROVED with more categories)
   */
  function detectGoal(tokens) {
    const joined = tokens.join(' ');
    if (/\b(now|quick|quickly|fast|asap|immediately|instant|right\s*away)\b/.test(joined)) return 'speed';
    if (/\b(compare|difference|vs|versus|versus|different|contrast)\b/.test(joined)) return 'compare';
    if (/\b(learn|understand|deep|theory|explain|tutorial|guide|how\s*to|teach)\b/.test(joined)) return 'learn';
    if (/\b(show|find|locate|where|display|view|see|browse)\b/.test(joined)) return 'discover';
    if (/\b(history|timeline|when|date|born|age|old)\b/.test(joined)) return 'temporal';
    if (/\b(list|all|every|complete|full)\b/.test(joined)) return 'comprehensive';
    if (/\b(best|top|recommended|favorite|popular)\b/.test(joined)) return 'recommendation';
    if (/\b(help|support|contact|reach|email|message)\b/.test(joined)) return 'contact';
    if (/\b(work|job|hire|freelance|contract|available|opportunity)\b/.test(joined)) return 'professional';
    return 'general';
  }

  /**
   * Detect user's preferred response style (IMPROVED)
   */
  function detectAim(text, sessionAim, normalizeText) {
    const lower = normalizeText(text);
    if (/\b(action|execute|do it|direct|route now|navigate now|just\s*(go|show|open))\b/.test(lower)) return 'action';
    if (/\b(deep|detailed|explain|analysis|breakdown|comprehensive|thorough|in[- ]depth)\b/.test(lower)) return 'deep';
    if (/\b(short|quick|brief|fast|summary|summarize|tl;dr|tldr)\b/.test(lower)) return 'action';
    if (/\b(simple|basic|beginner|easy|explain\s*like)\b/.test(lower)) return 'simple';
    if (/\b(technical|advanced|expert|detailed|code)\b/.test(lower)) return 'deep';
    return sessionAim || 'balanced';
  }

  /**
   * Plan response policy with adaptive thresholds (IMPROVED)
   */
  function planPolicy(aim, goal, confidence) {
    const bounded = Math.max(0, Math.min(1, confidence));

    // Adaptive auto-execution policy based on aim and goal
    let autoTool = false;
    if (aim === 'action' && bounded > 0.55) autoTool = true;
    if (goal === 'discover' && bounded > 0.60) autoTool = true;
    if (goal === 'speed' && bounded > 0.50) autoTool = true;
    if (aim === 'deep' && bounded > 0.70) autoTool = true;
    if (goal === 'contact' || goal === 'professional') autoTool = false; // Never auto-navigate for contact/professional

    return {
      aim: aim,
      goal: goal,
      confidence: bounded,
      autoTool: autoTool
    };
  }

  /**
   * Detect if response needs boilerplate (IMPROVED with more patterns)
   */
  function detectBoilerplateNeed(tokens) {
    const hasWho = tokens.includes('who');
    const hasYunus = tokens.includes('yunus') || tokens.includes('yun');
    const hasSite = tokens.includes('site') || tokens.includes('website');
    const hasWhat = tokens.includes('what');
    const hasYou = tokens.includes('you') || tokens.includes('assistant') || tokens.includes('bot');
    const hasAbout = tokens.includes('about');
    const hasThis = tokens.includes('this');

    if (hasWho && hasYou && !hasYunus) return 'assistant';
    if (hasWho && hasYunus) return 'identity';
    if (hasSite && (hasWhat || hasThis || hasAbout)) return 'site';
    if (hasWhat && hasAbout && hasSite) return 'site';
    if (hasWhat && hasYou && (hasAbout || hasThis)) return 'assistant';
    return '';
  }

  function stripPoliteness(text, normalizeText) {
    return normalizeText(text)
      .replace(/\b(hey|hi|hello|greetings)\s+yunobot\b/gi, '')
      .replace(/\b(please|kindly|can you|could you|would you|for me)\b/gi, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  /**
   * Parse natural language directives (IMPROVED with more patterns)
   */
  function parseNaturalDirective(message, state, ctx) {
    const raw = ctx.canonicalizeText(message);
    const rawOriginal = ctx.normalizeText(message);
    const text = stripPoliteness(raw, ctx.normalizeText);
    if (!text) return null;

    // Handle style preference updates
    if (/\b(be|stay|keep)\s+(brief|short|concise|quick)\b/i.test(text)) {
      state.aim = 'action';
      return { response: 'Aim updated: action (brief responses).', confidence: 1, intentTag: 'help' };
    }

    if (/\b(be|stay|go)\s+(deep|detailed|analytical|thorough|comprehensive)\b/i.test(text)) {
      state.aim = 'deep';
      return { response: 'Aim updated: deep (detailed responses).', confidence: 1, intentTag: 'help' };
    }

    if (/\b(be|stay|go)\s+(simple|basic|beginner|easy)\b/i.test(text)) {
      state.aim = 'simple';
      return { response: 'Aim updated: simple (easy-to-understand responses).', confidence: 1, intentTag: 'help' };
    }

    // Handle anaphoric navigation (referencing previous context)
    if (/\b(show|open|take|bring|send|route|navigate)\b/i.test(text) && /\b(it|there|that page|that one|that section)\b/i.test(text) && state.lastRoute) {
      const route = ctx.routeById[state.lastRoute];
      if (route) {
        return {
          response: 'Tool call -> navigate: ' + route.name,
          confidence: 1,
          intentTag: 'navigate',
          toolCall: { name: 'navigate', args: { url: route.url, page: route.id }, auto: true }
        };
      }
    }

    // Enhanced search pattern matching
    const searchPattern = /\b(search|find|look up|lookup|query)\b\s*(?:for\s+)?(.+?)(?:\s+(?:in|on|within|at)\s+(blog|updates|portfolio|all|about|gallery|travel))?$/i;
    const searchMatch = text.match(searchPattern);
    if (searchMatch) {
      const query = (searchMatch[2] || '').trim();
      const type = ctx.normalizeText(searchMatch[3] || 'all');
      const allowedTypes = ['all', 'blog', 'updates', 'portfolio', 'about', 'gallery', 'travel'];
      if (query && query.length >= 2) {
        const finalType = allowedTypes.includes(type) ? type : 'all';
        const url = ctx.BASE_PATH + 'search?q=' + encodeURIComponent(query) + '&type=' + encodeURIComponent(finalType);
        return {
          response: 'Tool call -> site_search: "' + query + '" (' + finalType + ')',
          confidence: 1,
          intentTag: 'help',
          toolCall: { name: 'site_search', args: { url: url, query: query, type: finalType }, auto: true }
        };
      }
    }

    // Enhanced navigation pattern matching
    const navPattern = /\b(go|open|take me|bring me|send me|navigate|route|jump|head)\b(?:\s+me)?(?:\s+to|\s+into|\s+towards|\s+to\s+the)?\s+(.+)/i;
    const navMatch = text.match(navPattern);
    if (navMatch) {
      const routeText = (navMatch[2] || '').replace(/\b(page|section|tab|screen)\b/gi, '').trim();
      const candidates = ctx.resolveRouteCandidatesFromText(routeText);
      if (!candidates.length) return null;

      if (candidates.length > 1 && (candidates[0].score - candidates[1].score) < 1.2) {
        const options = candidates.slice(0, 3).map(function (c) { return c.route.id; }).join(', ');
        return { response: 'Ambiguous destination. Did you mean: ' + options + '?', confidence: 0.72, intentTag: 'navigate' };
      }

      const route = candidates[0].route;
      return {
        response: 'Tool call -> navigate: ' + route.name,
        confidence: 1,
        intentTag: 'navigate',
        toolCall: { name: 'navigate', args: { url: route.url, page: route.id }, auto: true }
      };
    }

    // Handle help/capability questions
    if (/\bwhat can you do|what else can you do|how do you work|help me|what are your commands|what are you capable of\b/i.test(rawOriginal) || /\bwhat can you do|how do you work|help me\b/i.test(raw)) {
      return {
        response: ctx.buildCompositionalResponse('help', state, ctx.routeById.yunobot || ctx.routeById.home, {
          tokens: ctx.tokenize(rawOriginal),
          goal: state.goal || 'general'
        }),
        confidence: 0.95,
        intentTag: 'help'
      };
    }

    // Handle reset/clear context requests
    if (/\b(reset|clear|forget|start over|new conversation)\b/i.test(text)) {
      state.lastRoute = null;
      state.lastIntent = null;
      state.goal = 'general';
      state.aim = 'balanced';
      return { response: 'Context cleared. How can I help you?', confidence: 1, intentTag: 'help' };
    }

    return null;
  }

  window.YunoML.detectGoal = detectGoal;
  window.YunoML.detectAim = detectAim;
  window.YunoML.planPolicy = planPolicy;
  window.YunoML.detectBoilerplateNeed = detectBoilerplateNeed;
  window.YunoML.parseNaturalDirective = parseNaturalDirective;

  window.YunoMLFiles.push({
    id: 'planner',
    title: 'Planner Stage',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-planner.js',
    source: [detectGoal, detectAim, planPolicy, detectBoilerplateNeed, stripPoliteness, parseNaturalDirective]
      .map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
