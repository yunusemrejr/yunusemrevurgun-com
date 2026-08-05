/**
 * Updates page view options — grid/list toggle + per-page selector.
 *
 * Progressive enhancement: the toggle buttons and per-page select are plain
 * links/forms that work without JS (server renders both layouts from URL
 * params). This script upgrades them to instant switching and remembers the
 * last chosen view in localStorage. URL ?view= always wins over the saved
 * preference so shared links behave as expected.
 */
(function () {
    'use strict';

    var grid = document.querySelector('.ui-updates-grid');
    if (!grid) return;

    // Mark JS as active so the no-JS "Apply" fallback button can be hidden.
    document.documentElement.classList.add('has-js');

    var KEY = 'yev:updates:view';
    var VIEWS = ['grid', 'list'];

    var urlView = null;
    try {
        urlView = new URL(window.location.href).searchParams.get('view');
    } catch (e) { /* ignore */ }
    if (VIEWS.indexOf(urlView) === -1) urlView = null;

    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) { /* ignore */ }
    if (VIEWS.indexOf(saved) === -1) saved = null;

    var view = urlView || saved || (grid.getAttribute('data-view') || 'grid');
    if (VIEWS.indexOf(view) === -1) view = 'grid';

    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-updates-view]'));

    function setView(v, persist, pushUrl) {
        grid.classList.toggle('is-grid', v === 'grid');
        grid.classList.toggle('is-list', v === 'list');
        buttons.forEach(function (b) {
            var active = b.getAttribute('data-updates-view') === v;
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-pressed', String(active));
        });
        if (persist) {
            try { localStorage.setItem(KEY, v); } catch (e) { /* ignore */ }
        }
        if (pushUrl) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('view', v);
                url.searchParams.set('page', '1');
                history.replaceState(null, '', url);
            } catch (e) { /* ignore */ }
        }
    }

    setView(view, false, false);

    buttons.forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.preventDefault();
            var v = b.getAttribute('data-updates-view');
            if (v !== view) {
                view = v;
                setView(v, true, true);
            }
        });
    });

    // Per-page selector: navigate to keep the URL shareable/server-rendered.
    var per = document.querySelector('[data-updates-per]');
    if (per) {
        per.addEventListener('change', function () {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('per', per.value);
                url.searchParams.set('page', '1');
                window.location.href = url;
            } catch (e) { /* ignore */ }
        });
    }
})();
