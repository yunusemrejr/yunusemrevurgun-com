(function () {
  'use strict';

  window.YunoML = window.YunoML || {};
  window.YunoMLFiles = window.YunoMLFiles || [];

  function pickBySeed(arr, seed, shift) {
    if (!arr || !arr.length) return '';
    return arr[(seed + shift) % arr.length];
  }

  /**
   * Build response banks with expanded variety (IMPROVED)
   */
  function buildResponseBanks() {
    return {
      greet: {
        opener: ['I am online.', 'YunoBot is active.', 'System ready.', 'Hello! YunoBot here.', 'Ready to assist.'],
        core: ['You can ask naturally or use commands.', 'I understand both free text and direct commands.', 'I can parse intent from normal conversation.', 'Natural language or commands - I handle both.'],
        action: ['Try "open portfolio" or "search OT in blog".', 'Start with a goal and I will route you.', 'Say what you want and I will execute the next step.', 'Just tell me where you want to go.']
      },
      help: {
        opener: ['I can do four things fast:', 'My core capabilities are:', 'Here is what I can do:', 'I specialize in:', 'My functions include:'],
        core: ['navigate pages, run site search, explain sections, and adapt answer depth.', 'natural language routing, command execution, scoped search, and context-aware follow-ups.', 'intent understanding, page/tool routing, search tooling, and adaptive response style.', 'page navigation, content search, contextual answers, and adaptive responses.'],
        action: ['Commands also work: /go, /search, /aim, /where.', 'If you want direct mode, use /help for command syntax.', 'For deterministic control, use command mode with /go and /search.', 'Type /where to see current state.']
      },
      identity: {
        opener: ['Yunus is a software developer and IT specialist.', 'Yunus is a systems-focused builder.', 'Yunus works across software engineering and applied IT.', 'Yunus is a full-stack developer and systems architect.'],
        core: ['His profile centers on practical execution and architecture thinking.', 'The work emphasizes delivery, technical depth, and real-world systems.', 'His background combines engineering, automation, and productized solutions.', 'He focuses on computational intelligence and operational technology.'],
        action: ['Open About for full profile detail.', 'Use About to see timeline and links.', 'About has the full background and context.', 'Check the About page for the complete story.']
      },
      assistant: {
        opener: ['I am YunoBot, the in-browser assistant for this site.', 'I am YunoBot, Yunus\'s on-site AI helper.', 'I am the site assistant running in your browser session.', 'I\'m YunoBot, your guide through this site.'],
        core: ['I read intent, route pages, and support search/tool actions.', 'I handle both normal conversation and command-based control.', 'I can respond conversationally and trigger navigation/search when needed.', 'I use semantic embeddings to understand your questions.'],
        action: ['Tell me your goal and I will pick the best next action.', 'Give me a target and I will route you there.', 'Ask directly and I will execute the proper page/tool step.', 'What would you like to explore?']
      },
      portfolio: {
        opener: ['Portfolio is the project archive.', 'Portfolio is the best project view.', 'Portfolio is optimized for scanning work quickly.', 'The Portfolio section showcases Yunus\'s work.'],
        core: ['It is structured with filters, tags, and chronology.', 'It supports fast triage by year, domain, and signal.', 'You can inspect projects by scope and technology quickly.', 'Projects are organized by technology and domain.'],
        action: ['Want me to open Portfolio now?', 'Say "open portfolio" to jump there.', 'I can route you there immediately.', 'Shall I navigate to Portfolio?']
      },
      writing: {
        opener: ['Blog is the long-form channel.', 'Blog is where deep writing lives.', 'Blog is the detailed narrative layer.', 'The Blog contains Yunus\'s technical writing.'],
        core: ['It contains architecture notes and technical breakdowns.', 'Entries are higher-context and more analytical.', 'Use it for deeper explanations and structured writing.', 'Topics range from neural networks to industrial automation.'],
        action: ['Say "open blog" if you want to read now.', 'I can open Blog or run a scoped search.', 'If you have a topic, I can search it in Blog.', 'Want me to take you there?']
      },
      updates: {
        opener: ['Updates is the fast activity stream.', 'Updates is the short-form log.', 'Updates focuses on recency and quick status.', 'The Updates section tracks recent activity.'],
        core: ['It is best for rapid timeline checks.', 'You get concise entries instead of long analysis.', 'Use it when freshness matters most.', 'Think of it as a micro-blog or activity feed.'],
        action: ['Say "open updates" to continue.', 'I can route you to Updates now.', 'If needed, I can also search within updates.', 'Ready to navigate?']
      },
      visual: {
        opener: ['Gallery is the visual archive.', 'Gallery is image-first.', 'Gallery is optimized for visual browsing.', 'The Gallery showcases visual content.'],
        core: ['It supports quick preview and modal inspection.', 'The experience is built for rapid visual scan.', 'Use it when you want media instead of text.', 'Images are organized by category and date.'],
        action: ['Say "open gallery" to continue.', 'I can route you to Gallery now.', 'If you want map-based visuals, Travel is also available.', 'Want to see the pictures?']
      },
      travel: {
        opener: ['Travel is the map-driven section.', 'Travel combines map and media.', 'Travel is geo-contextual exploration.', 'The Travel section documents Yunus\'s journeys.'],
        core: ['It links locations with visual drill-down.', 'You can inspect places through interactive markers.', 'It blends route context with image context.', '21 countries are documented with photos and stories.'],
        action: ['Say "open travel" to continue.', 'I can navigate to Travel now.', 'If you want the fastest route, I can open it now.', 'Ready to explore the map?']
      },
      contact: {
        opener: ['Contact is the direct communication endpoint.', 'Contact is the quickest way to reach out.', 'Contact is built for structured inquiries.', 'The Contact page is where you can reach Yunus.'],
        core: ['Use it for collaboration, project, or consulting requests.', 'It is the primary channel for serious messages.', 'It is the right endpoint for actionable outreach.', 'Responses are typically prompt for professional inquiries.'],
        action: ['Say "open contact" and I will route you.', 'I can take you to Contact now.', 'If ready, I can open Contact immediately.', 'Shall we go there?']
      },
      navigate: {
        opener: ['Navigation intent detected.', 'Routing mode is active.', 'I can navigate directly.', 'I understand you want to go somewhere.'],
        core: ['Name the destination and I will route instantly.', 'I can map your phrase to the closest valid page.', 'I will convert your request into a deterministic route call.', 'Just say the page name and I\'ll take you there.'],
        action: ['Try "open about", "open portfolio", or "open blog".', 'You can also use /go <page>.', 'If ambiguous, I will ask for a specific target.', 'Which section would you like to visit?']
      },
      learning: {
        opener: ['Post-Code is the fundamentals track.', 'Post-Code is concept-first learning.', 'Post-Code focuses on durable engineering foundations.', 'The Post-Code section covers core concepts.'],
        core: ['It emphasizes math, architecture, and reasoning.', 'It is intended for deeper conceptual grounding.', 'Use it when you want first-principles understanding.', 'Topics include algorithms, computational theory, and system design.'],
        action: ['Say "open post-code" to continue.', 'I can route you to Post-Code now.', 'If needed, Blog can extend depth after Post-Code.', 'Ready for the deep dive?']
      },
      site: {
        opener: ['This site is Yunus Emre Vurgun\'s platform.', 'This is Yunus\'s portfolio and writing hub.', 'You are on Yunus\'s personal engineering site.', 'Welcome to Yunus\'s digital presence.'],
        core: ['It combines profile, projects, writing, updates, and experiments.', 'Sections are organized for rapid intent-based navigation.', 'You can move across identity, work, writing, and tools.', 'Every section serves a specific purpose in the ecosystem.'],
        action: ['Tell me your goal and I will route you.', 'I can take you to the exact section now.', 'Say the target page and I will open it.', 'What are you looking for?']
      },
      // New response banks for additional intents
      yunus_github: {
        opener: ['Yunus is very active on GitHub.', 'GitHub is where Yunus shares most of his work.', 'You can find Yunus at github.com/yunusemrejr.'],
        core: ['He has over 100 repositories covering various technologies.', 'Most of his code is open-source with permissive licenses.', 'The repositories include tools, libraries, and full applications.'],
        action: ['Want me to show you his GitHub?', 'Check the About page for the direct link.', 'I can navigate you to a page with GitHub info.']
      },
      yunus_certifications: {
        opener: ['Yunus values continuous learning.', 'Yunus has pursued various certifications.', 'Professional development is important to Yunus.'],
        core: ['He has certifications from Google Cloud and Cisco.', 'He regularly takes courses to stay current.', 'His learning spans cloud, networking, and software development.'],
        action: ['The About page has the full list.', 'Check About for detailed credentials.', 'Open About to see all certifications.']
      },
      bot_privacy: {
        opener: ['Privacy is built into my design.', 'I run entirely in your browser.', 'No data leaves your device.'],
        core: ['All processing happens locally - no server calls.', 'The embedding model is cached in your browser.', 'I don\'t store conversation history or track you.'],
        action: ['You can verify this in your browser\'s network tab.', 'Everything runs client-side.', 'No external APIs are called during conversation.']
      },
      bot_capabilities: {
        opener: ['I have several capabilities.', 'Here\'s what I can do:', 'My features include:'],
        core: ['I understand natural language questions about Yunus.', 'I can navigate you to any section of the site.', 'I handle follow-up questions with context.', 'I use semantic embeddings for robust understanding.'],
        action: ['Try asking "who is yunus" or "show me portfolio".', 'You can also use commands like /go and /search.', 'What would you like to try?']
      },
      unknown: {
        opener: ['I\'m not sure I understand.', 'I didn\'t quite catch that.', 'I\'m having trouble parsing that.'],
        core: ['I can help with questions about Yunus, navigation, or my own functionality.', 'Try rephrasing or being more specific.', 'I understand both natural questions and direct commands.'],
        action: ['Try "who is yunus", "show portfolio", or "what can you do".', 'Use simple, direct language.', 'Check the example questions for ideas.']
      },
    };
  }

  function composeBoilerplate(kind, signal, hashToken) {
    const seed = Math.abs(hashToken(signal.tokens.join('|') + '|' + kind + '|' + signal.aim + '|' + signal.goal));

    function pick(blocks, shift) {
      const idx = (seed + shift + Math.round(signal.intentScore * 100) + Math.round(signal.routeScore * 100)) % blocks.length;
      return blocks[idx];
    }

    if (kind === 'site') {
      const subject = pick(['This site is Yunus Emre Vurgun\'s personal platform.', 'You are on Yunus\'s portfolio and writing hub.', 'This website is a structured profile plus project ecosystem.'], 7);
      const structure = pick(['It combines pages for About, Portfolio, Blog, Updates, Travel, and experiments like YunoBot.', 'Core sections include identity, project archive, long-form writing, rapid updates, and interactive demos.', 'The layout is organized for fast navigation across profile, work, writing, and experimental interfaces.'], 13);
      const intent = pick(['I can route you to the exact section based on your intent.', 'Tell me your goal and I will direct you to the best page immediately.', 'If you share what you need, I will map it to the most relevant page.'], 19);
      if (signal.aim === 'action') return subject + ' ' + structure + ' Fast next step: say "open portfolio" or "open about".';
      if (signal.aim === 'deep') return subject + ' ' + structure + ' ' + intent + ' Current inference: goal=' + signal.goal + '.';
      return subject + ' ' + structure + ' ' + intent;
    }

    if (kind === 'identity') {
      const subject = pick(['Yunus is a software developer and IT specialist.', 'Yunus is a builder focused on practical software and systems work.', 'Yunus works at the intersection of software engineering and applied IT.'], 5);
      const scope = pick(['His profile spans engineering, automation, and project execution.', 'The profile emphasizes hands-on delivery, technical depth, and real project outcomes.', 'His work combines architecture thinking with implementation speed.'], 11);
      const routeHint = pick(['For full context, open the About page.', 'The About page has detailed background and timeline.', 'Use About for the complete profile and links.'], 17);
      if (signal.aim === 'action') return subject + ' ' + routeHint;
      if (signal.aim === 'deep') return subject + ' ' + scope + ' ' + routeHint + ' Current inference: goal=' + signal.goal + '.';
      return subject + ' ' + scope + ' ' + routeHint;
    }

    if (kind === 'assistant') {
      const subject = pick(['I am YunoBot, the in-browser assistant for this site.', 'I am YunoBot, designed to understand natural questions and commands.', 'I am the site AI assistant running locally in your browser session.'], 3);
      const core = pick(['I map your intent to pages, search, and direct actions.', 'I handle routing, search, and context-aware answers.', 'I interpret goals and trigger the right page or tool behavior.'], 9);
      const action = pick(['Tell me what you want and I will handle the next step.', 'If you want execution, name the destination and I will route it.', 'Ask naturally and I will convert it into a concrete action when needed.'], 15);
      if (signal.aim === 'action') return subject + ' ' + action;
      return subject + ' ' + core + ' ' + action;
    }

    return null;
  }

  /**
   * Build compositional response with improved variety (IMPROVED)
   */
  function buildCompositionalResponse(intentTag, state, route, signals, hashToken, banks) {
    const seed = Math.abs(hashToken(signals.tokens.join('|') + '|' + intentTag + '|' + state.aim + '|' + state.goal));
    const bank = (banks || buildResponseBanks())[intentTag] || (banks || buildResponseBanks()).help;

    let response = pickBySeed(bank.opener, seed, 3) + ' ' + pickBySeed(bank.core, seed, 7);
    if (state.aim === 'action' || signals.goal === 'discover' || signals.goal === 'speed') {
      response += ' ' + pickBySeed(bank.action, seed, 11);
    }
    if (state.aim === 'deep') {
      response += ' Inference: intent=' + intentTag + ', goal=' + signals.goal + ', route=' + route.id + '.';
    }
    if (state.aim === 'simple') {
      // For simple mode, keep it shorter
      response = pickBySeed(bank.opener, seed, 1) + ' ' + pickBySeed(bank.action, seed, 5);
    }
    return response.trim();
  }

  /**
   * Improved conversational fallback (IMPROVED)
   */
  function conversationalFallback(message, state, normalizeText) {
    const text = normalizeText(message);
    if (!text) return 'Give me a direction and I will adapt.';
    if (/\b(thanks|thank you|ty|appreciate)\b/i.test(text)) return 'Anytime! Want to continue with a page route or search?';
    if (/\b(how are you|you good|what\'s up|how\s*goes\s*it)\b/i.test(text)) return 'Running stable. Share your goal and I will optimize the next step.';
    if (/\b(hi|hello|hey|yo|greetings)\b/i.test(text)) return 'Hi there! Tell me what you want to do: navigate, search, or learn about Yunus.';
    if (/\b(i am lost|not sure|confused|idk|i\s*don't\s*know)\b/i.test(text)) return 'No problem. Start with intent: "show projects", "latest updates", "who is yunus", or "contact".';
    if (/\b(help|hlp)\b/i.test(text)) return 'I can help! Try: "open portfolio", "who is yunus", "search AI in blog", or type /help for commands.';
    if (/\b(bye|goodbye|see ya|later|gtg)\b/i.test(text)) return 'Goodbye! Feel free to come back anytime you have questions.';
    if (/\b(yes|yeah|yep|sure|ok|okay)\b/i.test(text)) {
      if (state.lastRoute) return 'Great! Want me to open ' + state.lastRoute + ' or would you like to explore something else?';
      return 'Great! What would you like to do next?';
    }
    if (/\b(no|nope|nah)\b/i.test(text)) return 'No problem! Let me know if you need anything else.';
    if (state.lastRoute) return 'Need another step? I can reopen ' + state.lastRoute + ' or run a targeted search.';
    return 'I can handle natural directives like "open about", "take me to blog", or "search cyber defense in updates".';
  }

  /**
   * Handle conversation repair with better context awareness (IMPROVED)
   */
  function handleConversationRepair(message, state, routeById, tokenize, normalizeText, buildCompositionalResponseFn) {
    const t = normalizeText(message);
    if (!t) return null;

    // User is correcting/rejecting previous response
    if (/\b(i did(n't| not) ask|not what i asked|wrong|not that|neither|that's not|i didn't|i did not|that's wrong|incorrect|no no|nope wrong)\b/i.test(t)) {
      // Clear context to reset
      const prevIntent = state.lastIntent;
      state.lastIntent = null;
      state.lastQuery = null;
      return {
        response: 'Understood, I missed your intent. Let\'s start fresh. You can ask directly about Yunus (background, skills, projects), request navigation ("open portfolio"), or ask about me ("what are you").',
        confidence: 1
      };
    }

    // User wants more information on the same topic
    if (/\bwhat else|anything else|tell me more|expand|elaborate|go on|continue\b/i.test(t)) {
      if (state.lastIntent) {
        const route = routeById[state.lastRoute] || routeById.home;
        return {
          response: buildCompositionalResponseFn(state.lastIntent, state, route, {
            tokens: tokenize(t + ' ' + state.lastIntent),
            goal: state.goal || 'general'
          }),
          confidence: 0.9
        };
      }
      return { response: 'Tell me the topic you want to expand, and I will continue from there.', confidence: 0.86 };
    }

    // User is repeating or rephrasing
    if (/\b(i mean|i said|i asked|let me rephrase|to clarify|what i meant)\b/i.test(t)) {
      return {
        response: 'Got it - please go ahead with your rephrased question. I\'m listening.',
        confidence: 0.95
      };
    }

    // User is asking for clarification
    if (/\b(what do you mean|clarify|explain|can you elaborate)\b/i.test(t)) {
      if (state.lastIntent) {
        return {
          response: 'Let me explain more about ' + state.lastIntent + '. What specifically would you like me to clarify?',
          confidence: 0.85
        };
      }
      return { response: 'What would you like me to clarify?', confidence: 0.8 };
    }

    return null;
  }

  window.YunoML.pickBySeed = pickBySeed;
  window.YunoML.buildResponseBanks = buildResponseBanks;
  window.YunoML.composeBoilerplate = composeBoilerplate;
  window.YunoML.buildCompositionalResponse = buildCompositionalResponse;
  window.YunoML.conversationalFallback = conversationalFallback;
  window.YunoML.handleConversationRepair = handleConversationRepair;

  window.YunoMLFiles.push({
    id: 'compose',
    title: 'Compositional Constructor',
    url: 'https://yunusemrevurgun.com/assets/js/yunobot/ml-compose.js',
    source: [pickBySeed, buildResponseBanks, composeBoilerplate, buildCompositionalResponse, conversationalFallback, handleConversationRepair]
      .map(function (fn) { return String(fn); }).join('\n\n')
  });
})();
