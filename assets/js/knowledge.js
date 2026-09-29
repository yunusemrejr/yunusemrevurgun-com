/* /post-code and /science-corner: fold-open cards, tag search, random entry,
   copy link, stack/timeline sync with the field filter, count-up, progress.
   Filtering and search themselves live in collections.js. */
(function () {
  'use strict';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.querySelector('[data-collection]');
  if (!root) return;
  var cards = Array.prototype.slice.call(root.querySelectorAll('.kn-card'));
  var input = root.querySelector('[data-collection-search]');

  function open(card, on) {
    card.classList.toggle('is-open', on);
    var t = card.querySelector('[data-kn-toggle]');
    if (t) t.setAttribute('aria-expanded', String(on));
  }

  root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-kn-toggle]');
    if (t) { var c = t.closest('.kn-card'); open(c, !c.classList.contains('is-open')); return; }

    var tag = e.target.closest('[data-kn-tag]');
    if (tag && input) {
      input.value = tag.getAttribute('data-kn-tag');
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.scrollIntoView({ block: 'center', behavior: still ? 'auto' : 'smooth' });
      return;
    }

    var cp = e.target.closest('[data-kn-copy]');
    if (cp) {
      var url = location.origin + location.pathname + '#' + cp.getAttribute('data-kn-copy');
      var old = cp.textContent, done = function () { cp.textContent = 'Copied'; setTimeout(function () { cp.textContent = old; }, 1600); };
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(done, done); else done();
      return;
    }

    var rnd = e.target.closest('[data-kn-random]');
    if (rnd) {
      var pool = cards.filter(function (c) { return !c.hidden; });
      if (!pool.length) return;
      var c2 = pool[Math.floor(Math.random() * pool.length)];
      open(c2, true);
      c2.scrollIntoView({ block: 'center', behavior: still ? 'auto' : 'smooth' });
      c2.classList.remove('is-flash'); void c2.offsetWidth; c2.classList.add('is-flash');
      history.replaceState(null, '', '#' + c2.id);
    }
  });

  // Deep links and index jumps open the target card.
  function openHash() {
    var id; try { id = decodeURIComponent(location.hash.slice(1)); } catch (e) { return; }
    var el = id && document.getElementById(id);
    if (el && el.classList.contains('kn-card')) { open(el, true); el.classList.add('is-flash'); }
  }
  addEventListener('hashchange', openHash);
  openHash();

  // Searching by text opens nothing, but a narrowed list (<=3) reads better open.
  if (input) input.addEventListener('input', function () {
    var vis = cards.filter(function (c) { return !c.hidden; });
    if (vis.length && vis.length <= 3 && input.value.trim()) vis.forEach(function (c) { open(c, true); });
  });

  // Layers / timeline follow the field filter.
  var atlas = root.querySelector('[data-kn-atlas]');
  root.addEventListener('click', function (e) {
    var f = e.target.closest('[data-filter]');
    if (!f) return;
    var cat = f.getAttribute('data-filter');
    if (atlas) atlas.querySelectorAll('li[data-category]').forEach(function (li) {
      li.classList.toggle('is-dim', cat !== 'all' && li.getAttribute('data-category') !== cat);
    });
    // layers and pills are separate buttons for the same filter; keep both lit
    root.querySelectorAll('.kn-layer').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-filter') === cat && cat !== 'all'));
    });
  });

  // "/" focuses search.
  addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (/INPUT|TEXTAREA|SELECT/.test(tag) || !input) return;
    e.preventDefault();
    input.focus();
  });

  var bar = document.querySelector('.kn-progress span');
  if (bar) {
    var tick = function () {
      var h = document.documentElement.scrollHeight - innerHeight;
      bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, scrollY / h) : 0) + ')';
    };
    addEventListener('scroll', tick, { passive: true }); tick();
  }

  if (still) return;
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var end = parseInt(el.getAttribute('data-count'), 10), t0 = null;
    if (!end) return;
    el.textContent = '0';
    requestAnimationFrame(function step(t) {
      if (t0 === null) t0 = t;
      var p = Math.min(1, (t - t0) / 900);
      el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    });
  });
})();
