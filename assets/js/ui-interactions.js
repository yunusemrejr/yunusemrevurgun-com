(() => {
  

  const BASE_PATH = window.FULL_BASE_PATH || '/';

  function q(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qa(selector, root) {
    return Array.from((root || document).querySelectorAll(selector));
  }

  function normalizeText(text) {
    return (text || '').toLowerCase().trim();
  }

  function tokenize(text) {
    return normalizeText(text)
      .replace(/[^a-z0-9\s-]/g, ' ')
      .split(/\s+/)
      .filter(Boolean);
  }

  function initMobileMenu() {
    const menu = q('#uiMobileMenu');
    if (!menu) return;

    const openBtn = q('[data-mobile-menu-open]');
    const closeItems = qa('[data-mobile-menu-close]', menu);
    let prevOverflow = '';

    function open() {
      prevOverflow = document.body.style.overflow;
      menu.classList.add('is-open');
      menu.setAttribute('aria-hidden', 'false');
      if (openBtn) {
        openBtn.setAttribute('aria-expanded', 'true');
        openBtn.setAttribute('aria-label', 'Close menu');
        openBtn.classList.add('active');
      }
      document.body.classList.add('menu-open');
      document.body.style.overflow = 'hidden';
    }

    function close() {
      menu.classList.remove('is-open');
      menu.setAttribute('aria-hidden', 'true');
      if (openBtn) {
        openBtn.setAttribute('aria-expanded', 'false');
        openBtn.setAttribute('aria-label', 'Open menu');
        openBtn.classList.remove('active');
      }
      document.body.classList.remove('menu-open');
      document.body.style.overflow = prevOverflow || '';
    }

    if (openBtn) openBtn.addEventListener('click', open);
    closeItems.forEach((el) => {
      el.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && menu.classList.contains('is-open')) {
        close();
      }
    });

    var resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (window.innerWidth > 768 && menu.classList.contains('is-open')) {
          close();
        }
      }, 100);
    });
  }

  function initAboutCommandCenter() {
    const cards = qa('.ui-command-card');
    if (!cards.length) return;

    cards.forEach((card, index) => {
      const trigger = q('.ui-command-trigger', card);
      const panel = q('.ui-command-panel', card);
      if (!trigger || !panel) return;

      function setOpen(isOpen) {
        card.classList.toggle('is-open', isOpen);
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        panel.style.maxHeight = isOpen ? panel.scrollHeight + 'px' : '0px';
      }

      if (index === 0) setOpen(true);

      trigger.addEventListener('click', () => {
        const isOpen = card.classList.contains('is-open');
        cards.forEach((c) => {
          const t = q('.ui-command-trigger', c);
          const p = q('.ui-command-panel', c);
          if (!t || !p) return;
          c.classList.remove('is-open');
          t.setAttribute('aria-expanded', 'false');
          p.style.maxHeight = '0px';
        });
        setOpen(!isOpen);
      });
    });
  }

  function initPortfolioFilters() {
    const bar = q('.ui-archive-filters');
    if (!bar) return;

    const pills = qa('.ui-filter-pill', bar);
    const cards = qa('.ui-project-card');

    function applyFilter(filter) {
      pills.forEach((pill) => pill.classList.toggle('is-active', pill.dataset.filter === filter));

      cards.forEach((card) => {
        const year = card.dataset.year || '';
        const featured = card.dataset.featured === '1';
        const category = card.dataset.category || '';
        let show = true;

        if (filter === 'featured') show = featured;
        else if (filter.startsWith('y')) show = year === filter.slice(1);
        else if (filter.startsWith('c:')) show = category === filter.slice(2);
        else if (filter !== 'all') show = false;

        card.classList.toggle('ui-project-hidden', !show);
      });
    }

    pills.forEach((pill) => {
      pill.addEventListener('click', () => {
        applyFilter(pill.dataset.filter || 'all');
      });
    });

    applyFilter('all');
  }

  function initSlideshow() {
    const roots = qa('[data-slideshow]');
    if (!roots.length) return;

    const instances = [];

    roots.forEach((root) => {
      const track = q('.ui-slideshow-track', root);
      const prevBtn = q('[data-slideshow-prev]', root);
      const nextBtn = q('[data-slideshow-next]', root);
      const currentEl = q('[data-slideshow-current]', root);
      const captionEl = q('[data-slideshow-caption]', root);
      const slides = track ? qa('.ui-slideshow-slide', track) : [];
      const total = slides.length;
      if (!track || total === 0) return;

      let index = 0;

      function render() {
        index = (index + total) % total;
        track.style.transform = 'translateX(-' + index * 100 + '%)';
        if (currentEl) currentEl.textContent = String(index + 1);
        if (captionEl) captionEl.textContent = slides[index].dataset.title || '';
        if (prevBtn) prevBtn.disabled = false;
        if (nextBtn) nextBtn.disabled = false;
      }

      function go(dir) {
        index += dir;
        render();
      }

      if (prevBtn) prevBtn.addEventListener('click', () => { go(-1); });
      if (nextBtn) nextBtn.addEventListener('click', () => { go(1); });

      // Touch swipe (mobile)
      let startX = 0, startY = 0, touching = false;
      const viewport = q('.ui-slideshow-viewport', root);
      if (viewport) {
        viewport.addEventListener('touchstart', (e) => {
          if (e.touches.length !== 1) return;
          touching = true;
          startX = e.touches[0].clientX;
          startY = e.touches[0].clientY;
        }, { passive: true });
        viewport.addEventListener('touchend', (e) => {
          if (!touching) return;
          touching = false;
          const dx = (e.changedTouches[0] ? e.changedTouches[0].clientX : 0) - startX;
          const dy = (e.changedTouches[0] ? e.changedTouches[0].clientY : 0) - startY;
          if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(dx < 0 ? 1 : -1);
        }, { passive: true });
      }

      render();
      instances.push({ root: root, go: go });
    });

    // Arrow keys: drive the slideshow that is most visible in the viewport
    // (never hijacks keys while typing in a form field).
    if (instances.length) {
      document.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        const target = event.target;
        if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) return;

        let best = null;
        let bestVisible = 0;
        instances.forEach((inst) => {
          if (!inst.root.getBoundingClientRect) return;
          const rect = inst.root.getBoundingClientRect();
          const visible = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
          if (visible > bestVisible) {
            bestVisible = visible;
            best = inst;
          }
        });
        if (!best) return;
        if (event.key === 'ArrowLeft') { best.go(-1); }
        else { best.go(1); }
      });
    }
  }

  // Dynamic favicon — cycles the three landing-page portraits.
  function initDynamicFavicon() {
    var links = qa('link[rel~="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]');
    if (!links.length) return;

    /* Weighted favicon rotation: 5 slots, default (favicon.svg) = 2/5 (40%),
       each alternative = 1/5 (20%) */
    var favicons = [
      { src: BASE_PATH + 'assets/images/favicon.svg',         weight: 2 },
      { src: BASE_PATH + 'assets/images/favicon-blackhole.svg',  weight: 1 },
      { src: BASE_PATH + 'assets/images/favicon-editorial.svg',  weight: 1 },
      { src: BASE_PATH + 'assets/images/favicon-pfp.png',        weight: 1 }
    ];

    // Build weighted array: each src repeated weight times
    var weighted = [];
    favicons.forEach((f) => {
      for (var i = 0; i < f.weight; i++) weighted.push(f.src);
    });

    function pickRandom() {
      return weighted[Math.floor(Math.random() * weighted.length)];
    }

    function setFavicon(src) {
      links.forEach((l) => { l.href = src; });
    }

    // Set initial favicon (default has highest probability)
    setFavicon(favicons[0].src);

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reducedMotion) return;

    // Rotate every 5 seconds with weighted randomization
    setInterval(() => {
      setFavicon(pickRandom());
    }, 5000);
  }



  function initProfileImageLoaders() {
    const frames = qa('[data-profile-loader]');
    if (!frames.length) return;

    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    frames.forEach((frame) => {
      if (frame.dataset.loaderInit === '1') return;
      frame.dataset.loaderInit = '1';

      const img = q('.ui-profile-image', frame);
      if (!img) {
        frame.classList.add('is-image-loaded');
        return;
      }

      if (reducedMotion) {
        frame.classList.add('is-image-loaded');
        return;
      }

      const minMs = Number.parseInt(frame.getAttribute('data-loader-min-ms') || '5000', 10);
      const minLoaderMs = Number.isFinite(minMs) ? Math.max(0, minMs) : 5000;
      const start = (window.performance && typeof window.performance.now === 'function')
        ? window.performance.now()
        : Date.now();

      const reveal = () => {
        const now = (window.performance && typeof window.performance.now === 'function')
          ? window.performance.now()
          : Date.now();
        const elapsed = now - start;
        const wait = Math.max(0, minLoaderMs - elapsed);
        window.setTimeout(() => {
          frame.classList.add('is-image-loaded');
        }, wait);
      };

      if (img.complete && img.naturalWidth > 0) {
        reveal();
      } else {
        img.addEventListener('load', reveal, { once: true });
        img.addEventListener('error', () => {
          frame.classList.remove('is-image-loaded');
        }, { once: true });
      }
    });
  }

  function createHybridYunoBrain() {
    if (window.YunoML && typeof window.YunoML.createBrain === 'function') {
      return window.YunoML.createBrain(BASE_PATH);
    }

    // Small fallback so UI stays responsive if modular ML files fail to load.
    return {
      infer: () => ({
          response: 'YunoBot ML modules are loading. Please retry in a second.',
          confidence: 0.2
        }),
      routes: []
    };
  }

  function initYunobotTerminal() {
    const form = q('#yunobotTerminalForm');
    const input = q('#yunobotTerminalInput');
    const messages = q('#yunobotTerminalMessages');
    if (!form || !input || !messages) return;

    const brain = createHybridYunoBrain();
    const session = { aim: 'balanced', goal: 'general', lastRoute: '', lastIntent: '', turns: 0 };

    function appendLine(text, type) {
      const line = document.createElement('div');
      line.className = 'ui-terminal-line ' + (type === 'user' ? 'ui-terminal-line-user' : 'ui-terminal-line-yuno');
      const prompt = document.createElement('span');
      prompt.className = 'ui-terminal-line-prompt';
      prompt.textContent = type === 'user' ? '>' : '$';

      const content = document.createElement('span');
      content.className = 'ui-terminal-line-content';
      content.textContent = text;

      if (type === 'user' && text.trim().startsWith('/')) {
        line.classList.add('is-command');
      }

      line.appendChild(prompt);
      line.appendChild(content);
      messages.appendChild(line);
      messages.scrollTop = messages.scrollHeight;
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const text = input.value.trim();
      if (!text) return;

      appendLine(text, 'user');
      input.value = '';

      const result = brain.infer(text, session);
      session.turns += 1;
      window.setTimeout(() => {
        appendLine(result.response, 'yuno');
        if (result.toolCall && result.toolCall.auto && result.toolCall.args && result.toolCall.args.url) {
          window.setTimeout(() => {
            // Same-origin only: relative URLs or absolute URLs on this host.
            const url = result.toolCall.args.url;
            const a = document.createElement('a');
            a.href = url;
            // pi-lens-ignore: ast-grep:no-open-redirect-js
            if (a.origin === window.location.origin) window.location.href = url;
          }, 520);
        }
      }, 180);
    });
  }

  function initYunobotArchitectureModal() {
    const openBtn = q('#yunobotArchInfoBtn');
    const modal = q('#yunobotArchModal');
    if (!openBtn || !modal) return;

    const closeItems = qa('[data-yunobot-arch-close]', modal);
    const explain = q('#yunobotArchExplain', modal);
    const nodes = qa('[data-arch-node]', modal);
    const codeBtn = q('#yunobotArchCodeBtn', modal);
    const quickBtn = q('#yunobotArchQuickBtn', modal);
    const codePanel = q('#yunobotArchCodePanel', modal);
    const codeBody = q('#yunobotArchCodeBody', modal);
    const archDetails = {
      tokenize: {
        title: 'Tokenize + Hash',
        technical: 'Input text is normalized and <a href="https://en.wikipedia.org/wiki/Tokenization_(lexical_analysis)" target="_blank" rel="noopener noreferrer">tokenized</a>, then mapped into a compact <a href="https://en.wikipedia.org/wiki/Hash_function" target="_blank" rel="noopener noreferrer">hashed</a> <a href="https://en.wikipedia.org/wiki/Tf%E2%80%93idf" target="_blank" rel="noopener noreferrer">TF-IDF</a> <a href="https://en.wikipedia.org/wiki/Vector_space_model" target="_blank" rel="noopener noreferrer">vector</a> with signed buckets. This keeps memory low and lookup fast.',
        simple: 'Turns your sentence into lightweight numeric signals [numbers the model can process quickly].'
      },
      transformer: {
        title: 'Transformer Route',
        technical: 'Route candidates are scored with <a href="https://en.wikipedia.org/wiki/Cosine_similarity" target="_blank" rel="noopener noreferrer">cosine similarity</a> and <a href="https://en.wikipedia.org/wiki/Attention_(machine_learning)" target="_blank" rel="noopener noreferrer">attention</a> over page and intent <a href="https://en.wikipedia.org/wiki/Word_embedding" target="_blank" rel="noopener noreferrer">embeddings</a>, then normalized with <a href="https://en.wikipedia.org/wiki/Softmax_function" target="_blank" rel="noopener noreferrer">softmax</a> to produce confidence-weighted routing.',
        simple: 'Chooses where to focus by scoring options and ranking them [attention means weighted focus].'
      },
      diffusion: {
        title: 'Diffusion Refiner',
        technical: 'Intent scores are iteratively denoised over a <a href="https://en.wikipedia.org/wiki/Graph_(discrete_mathematics)" target="_blank" rel="noopener noreferrer">graph</a> <a href="https://en.wikipedia.org/wiki/Prior_probability" target="_blank" rel="noopener noreferrer">prior</a>, blending current belief, observed evidence, and neighborhood consistency for stable intent selection.',
        simple: 'Cleans up noisy guesses in a few passes [denoise = remove uncertainty step by step].'
      },
      classic: {
        title: 'Classic ML Blend',
        technical: '<a href="https://en.wikipedia.org/wiki/Naive_Bayes_classifier" target="_blank" rel="noopener noreferrer">Naive Bayes</a> <a href="https://en.wikipedia.org/wiki/Likelihood_function" target="_blank" rel="noopener noreferrer">likelihood</a>, <a href="https://en.wikipedia.org/wiki/Jaccard_index" target="_blank" rel="noopener noreferrer">lexical overlap</a>, and <a href="https://en.wikipedia.org/wiki/Semantic_similarity" target="_blank" rel="noopener noreferrer">embedding similarity</a> are <a href="https://en.wikipedia.org/wiki/Calibration_(statistics)" target="_blank" rel="noopener noreferrer">calibrated</a> into a single probability score with <a href="https://en.wikipedia.org/wiki/Sigmoid_function" target="_blank" rel="noopener noreferrer">sigmoid</a> compression.',
        simple: 'Combines old-school ML checks with semantic signals [calibration = making confidence realistic].'
      },
      planner: {
        title: 'Planner',
        technical: 'A goal/aim <a href="https://en.wikipedia.org/wiki/Policy" target="_blank" rel="noopener noreferrer">policy</a> layer chooses response mode (balanced/action/deep), determines tool necessity, and applies safety <a href="https://en.wikipedia.org/wiki/Decision_boundary" target="_blank" rel="noopener noreferrer">thresholds</a> before action.',
        simple: 'Decides how to answer and whether to execute a tool [policy = decision rules].'
      },
      compose: {
        title: 'Compositional Constructor',
        technical: 'A compositional layer assembles final sentences from intent-scoped word-group banks, then refines phrasing with context signals and confidence.',
        simple: 'Builds responses piece by piece instead of selecting one full canned sentence.'
      },
      reply: {
        title: 'Reply Generator',
        technical: 'The response surface stage formats the composed text, applies style constraints, and returns a low-latency final utterance.',
        simple: 'Final polish layer that outputs the completed answer.'
      },
      tool: {
        title: 'Tool Dispatcher',
        technical: 'When confidence and intent pass thresholds, the router emits structured tool calls (navigate/search) with typed <a href="https://en.wikipedia.org/wiki/Argument_(computer_science)" target="_blank" rel="noopener noreferrer">arguments</a> and auto-run policy.',
        simple: 'Runs actions like opening pages or searching [typed args = clean structured command fields].'
      },
      memory: {
        title: 'Session Memory',
        technical: 'Short-lived <a href="https://en.wikipedia.org/wiki/State_(computer_science)" target="_blank" rel="noopener noreferrer">state</a> tracks last route, last intent, turn count, and user aim to preserve conversational continuity without heavy storage.',
        simple: 'Remembers recent context so follow-ups feel natural [session = current chat memory].'
      }
    };
    let activeNode = null;
    let previousBodyOverflow = '';

    function renderArchCode() {
      if (!codeBody || codeBody.dataset.rendered === '1') return;
      const rows = [];
      const stageSources = Array.isArray(window.YunoMLFiles) ? window.YunoMLFiles.slice() : [];

      function esc(value) {
        return String(value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
      }

      function colorize(line) {
        let html = esc(line);
        html = html.replace(/(\/\/.*)$/g, '<span class="ui-code-com">$1</span>');
        html = html.replace(/("[^"]*"|'[^']*')/g, '<span class="ui-code-str">$1</span>');
        html = html.replace(/\b(function|const|let|return|if|for|while|new)\b/g, '<span class="ui-code-kw">$1</span>');
        html = html.replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*(?=\()/g, '<span class="ui-code-fn">$1</span>');
        html = html.replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="ui-code-num">$1</span>');
        return html;
      }

      if (stageSources.length) {
        let ln = 1;
        stageSources.forEach((stage) => {
          rows.push('<span class="ui-code-ln">' + String(ln).padStart(2, '0') + '</span> <span class="ui-code-sec"># ' + esc(stage.title || stage.id || 'Stage') + '</span>');
          ln += 1;
          const lines = String(stage.source || '').split('\n');
          lines.forEach((line) => {
            rows.push('<span class="ui-code-ln">' + String(ln).padStart(2, '0') + '</span> ' + colorize(line));
            ln += 1;
          });
          rows.push('<span class="ui-code-ln">' + String(ln).padStart(2, '0') + '</span> ');
          ln += 1;
        });
      } else {
        rows.push(
        '<span class="ui-code-ln">01</span> <span class="ui-code-sec"># ENCODE (real snippet)</span>',
        '<span class="ui-code-ln">02</span> <span class="ui-code-kw">function</span> <span class="ui-code-fn">encode</span>(<span class="ui-code-id">text</span>) {',
        '<span class="ui-code-ln">03</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">tokens</span> = <span class="ui-code-fn">tokenize</span>(<span class="ui-code-id">text</span>);',
        '<span class="ui-code-ln">04</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">tf</span> = <span class="ui-code-kw">new</span> <span class="ui-code-fn">Map</span>();',
        '<span class="ui-code-ln">05</span>   <span class="ui-code-kw">for</span> (<span class="ui-code-kw">let</span> <span class="ui-code-id">i</span> = <span class="ui-code-num">0</span>; <span class="ui-code-id">i</span> &lt; <span class="ui-code-id">tokens</span>.<span class="ui-code-id">length</span>; <span class="ui-code-id">i</span> += <span class="ui-code-num">1</span>) {',
        '<span class="ui-code-ln">06</span>     <span class="ui-code-kw">const</span> <span class="ui-code-id">t</span> = <span class="ui-code-id">tokens</span>[<span class="ui-code-id">i</span>];',
        '<span class="ui-code-ln">07</span>     <span class="ui-code-id">tf</span>.<span class="ui-code-fn">set</span>(<span class="ui-code-id">t</span>, (<span class="ui-code-id">tf</span>.<span class="ui-code-fn">get</span>(<span class="ui-code-id">t</span>) || <span class="ui-code-num">0</span>) + <span class="ui-code-num">1</span>);',
        '<span class="ui-code-ln">08</span>   }',
        '<span class="ui-code-ln">09</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">v</span> = <span class="ui-code-kw">new</span> <span class="ui-code-fn">Float32Array</span>(<span class="ui-code-id">HASH_DIM</span>);',
        '<span class="ui-code-ln">10</span>   <span class="ui-code-id">tf</span>.<span class="ui-code-fn">forEach</span>(<span class="ui-code-kw">function</span> (<span class="ui-code-id">count</span>, <span class="ui-code-id">token</span>) { <span class="ui-code-com">// tf-idf + signed hash</span> });',
        '<span class="ui-code-ln">11</span>   <span class="ui-code-kw">return</span> { <span class="ui-code-id">vector</span>: <span class="ui-code-id">v</span>, <span class="ui-code-id">tokens</span>: <span class="ui-code-id">tokens</span> };',
        '<span class="ui-code-ln">12</span> }',
        '<span class="ui-code-ln">13</span> ',
        '<span class="ui-code-ln">14</span> <span class="ui-code-sec"># ROUTE + CLASSIC ML + DIFFUSION (real snippet)</span>',
        '<span class="ui-code-ln">15</span> <span class="ui-code-kw">function</span> <span class="ui-code-fn">routeAttention</span>(<span class="ui-code-id">queryVector</span>, <span class="ui-code-id">allowedRoutes</span>) { <span class="ui-code-com">// cosine + softmax</span> }',
        '<span class="ui-code-ln">16</span> <span class="ui-code-kw">function</span> <span class="ui-code-fn">naiveBayesScore</span>(<span class="ui-code-id">tokens</span>, <span class="ui-code-id">tag</span>) { <span class="ui-code-com">// log-likelihood</span> }',
        '<span class="ui-code-ln">17</span> <span class="ui-code-kw">function</span> <span class="ui-code-fn">classifyIntent</span>(<span class="ui-code-id">queryVector</span>, <span class="ui-code-id">tokens</span>) {',
        '<span class="ui-code-ln">18</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">rawScores</span> = <span class="ui-code-id">intents</span>.<span class="ui-code-fn">map</span>(<span class="ui-code-kw">function</span> (<span class="ui-code-id">intent</span>) {',
        '<span class="ui-code-ln">19</span>     <span class="ui-code-kw">const</span> <span class="ui-code-id">embScore</span> = <span class="ui-code-fn">cosineSim</span>(<span class="ui-code-id">queryVector</span>, <span class="ui-code-id">intentEmbeddings</span>[<span class="ui-code-id">intent</span>.<span class="ui-code-id">tag</span>]);',
        '<span class="ui-code-ln">20</span>     <span class="ui-code-kw">const</span> <span class="ui-code-id">nbScore</span> = <span class="ui-code-fn">naiveBayesScore</span>(<span class="ui-code-id">tokens</span>, <span class="ui-code-id">intent</span>.<span class="ui-code-id">tag</span>);',
        '<span class="ui-code-ln">21</span>     <span class="ui-code-kw">const</span> <span class="ui-code-id">lexicalScore</span> = <span class="ui-code-id">intent</span>.<span class="ui-code-id">keywords</span>.<span class="ui-code-fn">reduce</span>(<span class="ui-code-kw">function</span> (<span class="ui-code-id">acc</span>, <span class="ui-code-id">k</span>) { <span class="ui-code-kw">return</span> <span class="ui-code-id">acc</span> + (<span class="ui-code-id">tokens</span>.<span class="ui-code-fn">includes</span>(<span class="ui-code-id">k</span>) ? <span class="ui-code-num">1</span> : <span class="ui-code-num">0</span>); }, <span class="ui-code-num">0</span>);',
        '<span class="ui-code-ln">22</span>   });',
        '<span class="ui-code-ln">23</span>   <span class="ui-code-kw">for</span> (<span class="ui-code-kw">let</span> <span class="ui-code-id">step</span> = <span class="ui-code-num">0</span>; <span class="ui-code-id">step</span> &lt; <span class="ui-code-num">4</span>; <span class="ui-code-id">step</span> += <span class="ui-code-num">1</span>) { <span class="ui-code-com">// diffusion-like smoothing over adjacency</span> }',
        '<span class="ui-code-ln">24</span>   <span class="ui-code-kw">return</span> { <span class="ui-code-id">tag</span>: <span class="ui-code-id">intents</span>[<span class="ui-code-id">bestIdx</span>].<span class="ui-code-id">tag</span>, <span class="ui-code-id">confidence</span>: <span class="ui-code-id">bestScore</span> };',
        '<span class="ui-code-ln">25</span> }',
        '<span class="ui-code-ln">26</span> ',
        '<span class="ui-code-ln">27</span> <span class="ui-code-sec"># COMPOSITION LAYER (real snippet)</span>',
        '<span class="ui-code-ln">28</span> <span class="ui-code-kw">function</span> <span class="ui-code-fn">buildCompositionalResponse</span>(<span class="ui-code-id">intentTag</span>, <span class="ui-code-id">state</span>, <span class="ui-code-id">route</span>, <span class="ui-code-id">signals</span>) {',
        '<span class="ui-code-ln">29</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">seed</span> = <span class="ui-code-fn">Math</span>.<span class="ui-code-fn">abs</span>(<span class="ui-code-fn">hashToken</span>(<span class="ui-code-id">signals</span>.<span class="ui-code-id">tokens</span>.<span class="ui-code-fn">join</span>(<span class="ui-code-str">"|"</span>) + <span class="ui-code-str">"|"</span> + <span class="ui-code-id">intentTag</span>));',
        '<span class="ui-code-ln">30</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">bank</span> = <span class="ui-code-id">banks</span>[<span class="ui-code-id">intentTag</span>] || <span class="ui-code-id">banks</span>.<span class="ui-code-id">help</span>;',
        '<span class="ui-code-ln">31</span>   <span class="ui-code-kw">let</span> <span class="ui-code-id">response</span> = <span class="ui-code-fn">pickBySeed</span>(<span class="ui-code-id">bank</span>.<span class="ui-code-id">opener</span>, <span class="ui-code-id">seed</span>, <span class="ui-code-num">3</span>) + <span class="ui-code-str">" "</span> + <span class="ui-code-fn">pickBySeed</span>(<span class="ui-code-id">bank</span>.<span class="ui-code-id">core</span>, <span class="ui-code-id">seed</span>, <span class="ui-code-num">7</span>);',
        '<span class="ui-code-ln">32</span>   <span class="ui-code-kw">if</span> (<span class="ui-code-id">state</span>.<span class="ui-code-id">aim</span> === <span class="ui-code-str">"action"</span>) <span class="ui-code-id">response</span> += <span class="ui-code-str">" "</span> + <span class="ui-code-fn">pickBySeed</span>(<span class="ui-code-id">bank</span>.<span class="ui-code-id">action</span>, <span class="ui-code-id">seed</span>, <span class="ui-code-num">11</span>);',
        '<span class="ui-code-ln">33</span>   <span class="ui-code-kw">return</span> <span class="ui-code-id">response</span>.<span class="ui-code-fn">trim</span>();',
        '<span class="ui-code-ln">34</span> }',
        '<span class="ui-code-ln">35</span> ',
        '<span class="ui-code-ln">36</span> <span class="ui-code-sec"># PLANNER + TOOL + MEMORY IN INFER() (real snippet)</span>',
        '<span class="ui-code-ln">37</span> <span class="ui-code-kw">function</span> <span class="ui-code-fn">infer</span>(<span class="ui-code-id">message</span>, <span class="ui-code-id">state</span>) {',
        '<span class="ui-code-ln">38</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">canonical</span> = <span class="ui-code-fn">canonicalizeText</span>(<span class="ui-code-id">message</span>);',
        '<span class="ui-code-ln">39</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">encoded</span> = <span class="ui-code-fn">encode</span>(<span class="ui-code-id">canonical</span>);',
        '<span class="ui-code-ln">40</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">intentResult</span> = <span class="ui-code-fn">classifyIntent</span>(<span class="ui-code-id">encoded</span>.<span class="ui-code-id">vector</span>, <span class="ui-code-id">encoded</span>.<span class="ui-code-id">tokens</span>);',
        '<span class="ui-code-ln">41</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">attention</span> = <span class="ui-code-fn">routeAttention</span>(<span class="ui-code-id">encoded</span>.<span class="ui-code-id">vector</span>, <span class="ui-code-id">intent</span>.<span class="ui-code-id">routeBias</span>);',
        '<span class="ui-code-ln">42</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">response</span> = <span class="ui-code-fn">buildCompositionalResponse</span>(<span class="ui-code-id">intent</span>.<span class="ui-code-id">tag</span>, <span class="ui-code-id">state</span>, <span class="ui-code-id">route</span>, { <span class="ui-code-id">tokens</span>: <span class="ui-code-id">encoded</span>.<span class="ui-code-id">tokens</span>, <span class="ui-code-id">goal</span>: <span class="ui-code-id">state</span>.<span class="ui-code-id">goal</span> });',
        '<span class="ui-code-ln">43</span>   <span class="ui-code-kw">const</span> <span class="ui-code-id">toolCall</span> = <span class="ui-code-id">wantsTool</span> ? { <span class="ui-code-id">name</span>: <span class="ui-code-str">"navigate"</span>, <span class="ui-code-id">args</span>: { <span class="ui-code-id">url</span>: <span class="ui-code-id">route</span>.<span class="ui-code-id">url</span>, <span class="ui-code-id">page</span>: <span class="ui-code-id">route</span>.<span class="ui-code-id">id</span> }, <span class="ui-code-id">auto</span>: <span class="ui-code-id">conf</span> &gt; <span class="ui-code-num">0.62</span> } : <span class="ui-code-kw">null</span>;',
        '<span class="ui-code-ln">44</span>   <span class="ui-code-id">state</span>.<span class="ui-code-id">lastRoute</span> = <span class="ui-code-id">route</span>.<span class="ui-code-id">id</span>; <span class="ui-code-id">state</span>.<span class="ui-code-id">lastIntent</span> = <span class="ui-code-id">intent</span>.<span class="ui-code-id">tag</span>;',
        '<span class="ui-code-ln">45</span>   <span class="ui-code-kw">return</span> { <span class="ui-code-id">response</span>: <span class="ui-code-id">response</span>, <span class="ui-code-id">confidence</span>: <span class="ui-code-id">conf</span>, <span class="ui-code-id">toolCall</span>: <span class="ui-code-id">toolCall</span> };',
        '<span class="ui-code-ln">46</span> } <span class="ui-code-com">// rendered from actual architecture parts, not raw file dump</span>'
        );
      }
      // pi-lens-ignore: ast-grep:no-inner-html-js
      codeBody.innerHTML = rows.join('\n');
      codeBody.dataset.rendered = '1';
    }

    function renderNodeExplain(key) {
      const item = archDetails[key];
      if (!item || !explain) return;
      // pi-lens-ignore: ast-grep:no-inner-html-js
      explain.innerHTML =
        '<h3 class="ui-arch-explain-title">' + item.title + '</h3>' +
        '<p class="ui-arch-explain-tech"><strong>Technical:</strong> ' + item.technical + '</p>' +
        '<p class="ui-arch-explain-simple"><strong>Simple:</strong> ' + item.simple + '</p>';
    }

    function renderQuickSummary() {
      if (!explain) return;
      // pi-lens-ignore: ast-grep:no-inner-html-js
      explain.innerHTML =
        '<h3 class="ui-arch-explain-title">YunoBot AI In 90 Seconds</h3>' +
        '<p class="ui-arch-explain-tech">YunoBot starts by turning your text into weighted numeric signals (hashed TF-IDF), where rare, informative words are given more influence than common words. Then it runs three parallel scoring views: embedding similarity (meaning closeness in vector space), Bayes likelihood (how probable your observed words are under each intent model), and lexical overlap (direct token intersection strength). A simple analogy: Bayes is like checking which department most likely wrote a memo based on its word habits, while lexical overlap is counting how many key checklist items are explicitly present. These signals are normalized and blended so no single metric dominates unfairly.</p>' +
        '<p class="ui-arch-explain-tech">After blending, scores are refined by graph diffusion over intent neighborhoods: each intent keeps most of its own score, but receives controlled influence from connected intents. Mathematically, this is a weighted iterative smoothing step that reduces noisy spikes while preserving strong evidence. Then attention routing maps your query vector to page vectors and converts relative route scores into a probability-style distribution, so the top route is selected by comparative strength, not by one hard rule.</p>' +
        '<p class="ui-arch-explain-tech">Finally, planner policy uses aim, goal, and confidence thresholds to decide response depth and tool autonomy. Response generation is compositional: it samples opener/core/action phrase groups and composes them into the final output, instead of picking one fixed canned sentence. Think of it as assembling a response from validated components with context-aware weights. Session memory stores last intent and route so follow-ups stay consistent and mathematically anchored to previous state.</p>';
    }

    nodes.forEach((node) => {
      const key = node.getAttribute('data-arch-node');
      node.setAttribute('tabindex', '0');
      node.setAttribute('role', 'button');
      node.setAttribute('aria-label', 'Explain ' + (key || 'architecture node'));

      node.addEventListener('click', () => {
        if (!key) return;
        activeNode = key;
        nodes.forEach((n) => {
          n.classList.toggle('is-active', n === node);
        });
        renderNodeExplain(key);
      });

      node.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        node.click();
      });
    });

    function openModal() {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      previousBodyOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      document.body.dataset.yunobotArchLock = '1';
      const initial = activeNode || (nodes[0] && nodes[0].getAttribute('data-arch-node')) || 'tokenize';
      const targetNode = nodes.find((n) => n.getAttribute('data-arch-node') === initial) || nodes[0];
      if (targetNode) targetNode.click();
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = previousBodyOverflow || '';
      delete document.body.dataset.yunobotArchLock;
      if (codePanel && codeBtn) {
        codePanel.classList.remove('is-open');
        codePanel.setAttribute('aria-hidden', 'true');
        codeBtn.setAttribute('aria-expanded', 'false');
      }
    }

    if (codeBtn && codePanel) {
      codeBtn.addEventListener('click', () => {
        const isOpen = codePanel.classList.toggle('is-open');
        codePanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        codeBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) renderArchCode();
      });
    }

    if (quickBtn) {
      quickBtn.addEventListener('click', () => {
        renderQuickSummary();
      });
    }

    window.addEventListener('pageshow', () => {
      if (!modal.classList.contains('is-open') && document.body.dataset.yunobotArchLock === '1') {
        document.body.style.overflow = previousBodyOverflow || '';
        delete document.body.dataset.yunobotArchLock;
      }
    });

    openBtn.addEventListener('click', openModal);
    closeItems.forEach((el) => {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  }

  function initGrayscaleTapReveal() {
    // On touch devices, toggle .is-revealed on tap for interactive media
    var isTouch = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
    if (!isTouch) return;

    var selectors = [
      '.ui-slideshow-slide',
      '.ui-photo-box',
      '.ui-travel-modal-grid img',
      '.ui-project-card',
      '.ui-card',
      '.ui-profile-frame'
    ];

    selectors.forEach((sel) => {
      qa(sel).forEach((el) => {
        el.addEventListener('click', () => {
          el.classList.toggle('is-revealed');
        });
      });
    });
  }

  function initXPopup() {
    const trigger = q('[data-x-popup-trigger]');
    const popup = q('#uiXPopup');
    if (!trigger || !popup) return;

    const closeItems = qa('[data-x-popup-close]', popup);
    let previousOverflow = '';
    let lastFocused = null;

    function open() {
      lastFocused = document.activeElement;
      previousOverflow = document.body.style.overflow;
      popup.classList.add('is-open');
      popup.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      const closeBtn = q('.ui-x-popup-close', popup);
      if (closeBtn) closeBtn.focus();
    }

    function close() {
      popup.classList.remove('is-open');
      popup.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = previousOverflow || '';
      if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    trigger.addEventListener('click', open);
    closeItems.forEach((el) => {
      el.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && popup.classList.contains('is-open')) {
        close();
      }
    });
  }

  function init() {
    initMobileMenu();
    initAboutCommandCenter();
    initPortfolioFilters();
    initSlideshow();
    initDynamicFavicon();
    initYunobotArchitectureModal();
    initYunobotTerminal();
    initGrayscaleTapReveal();
    initXPopup();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// FORCE REDEPLOY FLAG: js-updated-2026-04-22
26-04-22
