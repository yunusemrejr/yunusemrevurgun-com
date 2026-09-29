/* /rmrp extras: "Surprise me", copy link, reading progress, count-up and a
   gentle reveal. Everything here is optional — pages read fully without it. */
(function () {
  'use strict';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Random memory: pick from the id list on the button, never the current one.
  document.querySelectorAll('[data-random-memory]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var ids = (a.getAttribute('data-ids') || '').split(',').filter(Boolean);
      var skip = a.getAttribute('data-exclude');
      if (skip) ids = ids.filter(function (i) { return i !== skip; });
      if (!ids.length) return;
      e.preventDefault();
      var base = a.getAttribute('href').replace(/\d+$/, '');
      window.location.href = base + ids[Math.floor(Math.random() * ids.length)];
    });
  });

  var copy = document.querySelector('[data-copy-link]');
  if (copy) {
    copy.addEventListener('click', function () {
      var done = function () {
        var old = copy.textContent;
        copy.textContent = 'Copied';
        setTimeout(function () { copy.textContent = old; }, 1600);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, done);
      else done();
    });
  }

  var bar = document.querySelector('.ui-rmrp-progress span');
  if (bar) {
    var tick = function () {
      var h = document.documentElement.scrollHeight - innerHeight;
      bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, scrollY / h) : 0) + ')';
    };
    addEventListener('scroll', tick, { passive: true });
    tick();
  }

  if (still) return;

  document.querySelectorAll('[data-count]').forEach(function (el) {
    var end = parseInt(el.getAttribute('data-count'), 10);
    if (!end) return;
    var t0 = null;
    var step = function (t) {
      if (t0 === null) t0 = t;
      var p = Math.min(1, (t - t0) / 900);
      el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step);
    };
    el.textContent = '0';
    requestAnimationFrame(step);
  });

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('[data-reveal]').forEach(function (el) {
      el.classList.add('will-reveal');
      io.observe(el);
    });
  }
})();
