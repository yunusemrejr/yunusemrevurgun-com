/* Homepage hero: poking Jell-omo.
 *
 * One damped spring is the whole model. Poking adds an impulse to it; every frame the same state
 * squashes the render, swings the dial's needle, moves the probe resting on the cap's button and
 * writes the readout, so the numbers on the dial are the motion you see, not a decoration.
 *
 *   squash  x   : fraction of height lost (x'' = -K x - C x'); sx = 1 + x/2, sy = 1 - x
 *   lean    deg : a second, softer spring, kicked by where on the jelly the poke landed
 *   dial        : height change in percent = -x * 100, 6 degrees of needle per percent
 *
 * The loop runs only while there is energy to spend (a poke lasts about a second), only while the
 * figure is on screen, and idle "settling" pokes are off for reduced motion, data saver and
 * low-memory or low-core devices. No idle frame loop exists.
 * Geometry constants are the mascot cutout's 800 x 800 pixel space (see hero-stage.php).
 */
(function () {
  'use strict';

  var stage = document.querySelector('[data-stage]');
  if (!stage) return;

  var frame = stage.querySelector('.jx-frame');
  var img = stage.querySelector('.ui-mascot');
  var needle = stage.querySelector('.jx-needle');
  var probe = stage.querySelector('.jx-probe');
  var tip = stage.querySelector('.jx-probe-tip');
  var poke = stage.querySelector('.jx-poke');
  var readout = stage.querySelector('[data-readout]');
  var replay = stage.querySelector('[data-replay]');
  if (!frame || !img || !poke) return;

  var root = document.documentElement;
  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  var conn = navigator.connection || {};
  var lite = !!conn.saveData ||
    (navigator.deviceMemory && navigator.deviceMemory <= 2) ||
    (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 2);
  if (lite) root.setAttribute('data-motion', 'lite');

  // spring constants: ~19 rad/s with a damping ratio of 0.29 for the squash (a few visible bounces,
  // gone in about a second); the lean is slower and more damped
  var K = 360, C = 11, KL = 240, CL = 8;
  var OX = 400, OY = 768;     // transform origin of the render, in the 800 px space (50% / 96%)
  var BX = 372, BY = 22;      // the cap's button, where the probe rests
  var SOCKET = 'M108 70L';

  var sq = 0, sv = 0, lean = 0, lv = 0;
  var pokes = 0, raf = 0, last = 0, shown = '', visible = true, idleTimer = 0;
  var born = performance.now();

  function render() {
    var sx = 1 + sq * 0.5;
    var sy = 1 - sq;
    img.style.transform = 'rotate(' + lean.toFixed(3) + 'deg) scale(' + sx.toFixed(4) + ',' + sy.toFixed(4) + ')';

    var pct = -sq * 100;
    if (needle) needle.setAttribute('transform', 'rotate(' + Math.max(-125, Math.min(125, pct * 6)).toFixed(2) + ')');

    if (tip && probe) {
      var a = lean * Math.PI / 180, c = Math.cos(a), s = Math.sin(a);
      var vx = (BX - OX) * sx, vy = (BY - OY) * sy;
      var tx = (OX + vx * c - vy * s).toFixed(1), ty = (OY + vx * s + vy * c).toFixed(1);
      tip.setAttribute('cx', tx);
      tip.setAttribute('cy', ty);
      probe.setAttribute('d', SOCKET + tx + ' ' + ty);
    }
    say(pct);
  }

  function say(pct) {
    if (!readout) return;
    var v = Math.abs(pct) < 0.05 ? '0.0' : (pct > 0 ? '+' : '−') + Math.abs(pct).toFixed(1);
    var text = 'dial: height ' + v + ' % · ' + pokes + (pokes === 1 ? ' poke' : ' pokes');
    if (text !== shown) {
      shown = text;
      readout.textContent = text;
    }
  }

  function step(now) {
    var dt = last ? Math.min((now - last) / 1000, 1 / 30) : 1 / 60;
    last = now;
    var n = 4, h = dt / n;
    for (var i = 0; i < n; i++) {
      sv += (-K * sq - C * sv) * h;
      sq += sv * h;
      lv += (-KL * lean - CL * lv) * h;
      lean += lv * h;
    }
    if (sq > 0.24) { sq = 0.24; if (sv > 0) sv = 0; }
    if (sq < -0.14) { sq = -0.14; if (sv < 0) sv = 0; }
    render();
    if (Math.abs(sq) < 4e-4 && Math.abs(sv) < 4e-3 && Math.abs(lean) < 0.01 && Math.abs(lv) < 0.05) {
      sq = sv = lean = lv = 0;
      render();
      raf = 0;
      return;
    }
    raf = requestAnimationFrame(step);
  }

  function run() {
    if (!raf) {
      last = 0;
      raf = requestAnimationFrame(step);
    }
  }

  // The dial's self-test, as an instrument does at power-up: the needle sweeps the whole scale and
  // returns. It is driven here (a handful of attribute writes) rather than by a CSS keyframe so the needle
  // rotates about the dial's centre the same way in every engine.
  var selfRaf = 0, selfTimer = 0;
  function ease(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }
  function selfTest() {
    if (still.matches || lite || raf || !needle) return;
    var t0 = performance.now(), D = 1900;
    function frame(now) {
      var p = (now - t0) / D;
      if (p >= 1 || raf) { selfRaf = 0; render(); return; }
      var a = p < 0.28 ? -120 * ease(p / 0.28)
        : p < 0.72 ? -120 + 240 * ease((p - 0.28) / 0.44)
        : 120 * (1 - ease((p - 0.72) / 0.28));
      needle.setAttribute('transform', 'rotate(' + a.toFixed(1) + ')');
      selfRaf = requestAnimationFrame(frame);
    }
    selfRaf = requestAnimationFrame(frame);
  }
  function cancelSelfTest() {
    clearTimeout(selfTimer);
    if (selfRaf) { cancelAnimationFrame(selfRaf); selfRaf = 0; render(); }
  }

  // The first touch of the figure ends the intro (the render is already there, just faded back).
  function endIntro() {
    if (stage.getAttribute('data-intro') !== 'off') stage.setAttribute('data-intro', 'off');
    cancelSelfTest();
  }

  function ripple(ox, oy) {
    if (still.matches) return;
    var r = document.createElement('span');
    r.className = 'jx-ripple';
    r.style.left = ((ox + 1) * 50).toFixed(1) + '%';
    r.style.top = (oy * 100).toFixed(1) + '%';
    r.addEventListener('animationend', function () { r.remove(); });
    frame.appendChild(r);
  }

  function push(strength, ox) {
    sv += 4.0 * strength;
    lv += ox * 46 * strength;
    run();
  }

  function doPoke(cx, cy, quiet) {
    var box = frame.getBoundingClientRect();
    var ox = Math.max(-1, Math.min(1, ((cx - box.left) / box.width) * 2 - 1));
    var oy = Math.max(0, Math.min(1, (cy - box.top) / box.height));
    var strength = 0.78 + 0.34 * (1 - oy);   // the top of the cap gives more than the base
    if (!quiet) {
      endIntro();
      pokes++;
      ripple(ox, oy);
    }
    if (still.matches) {
      // no motion: show the reading for a moment, move nothing
      sq = 0.08 * strength;
      render();
      clearTimeout(doPoke.t);
      doPoke.t = setTimeout(function () { sq = 0; render(); }, 600);
      return;
    }
    push(quiet ? 0.32 : strength, ox);
  }

  poke.addEventListener('pointerdown', function (e) {
    if (e.button) return;
    doPoke(e.clientX, e.clientY, false);
  });

  // Keyboard and assistive-tech activation arrive as a click with detail 0 (a mouse click has been
  // handled on pointerdown already).
  poke.addEventListener('click', function (e) {
    if (e.detail !== 0) return;
    var box = frame.getBoundingClientRect();
    doPoke(box.left + box.width * 0.5, box.top + box.height * 0.3, false);
  });

  if (replay) {
    replay.addEventListener('click', function () {
      cancelSelfTest();
      stage.setAttribute('data-intro', 'off');
      void stage.offsetWidth;   // restart every CSS animation of the intro
      stage.setAttribute('data-intro', 'on');
      selfTimer = setTimeout(selfTest, 2900);
      settleIntro();
    });
  }

  // Idle: the jelly settles by itself now and then, a tenth of a poke, so the dial shows it.
  function scheduleIdle() {
    clearTimeout(idleTimer);
    if (still.matches || lite || !visible) return;
    var wait = 9000 + Math.random() * 7000;
    wait = Math.max(wait, 3800 - (performance.now() - born));
    idleTimer = setTimeout(function () {
      if (visible && document.visibilityState === 'visible' && !raf) {
        var box = frame.getBoundingClientRect();
        doPoke(box.left + box.width * (0.35 + Math.random() * 0.3), box.top + box.height * 0.35, true);
      }
      scheduleIdle();
    }, wait);
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      visible = entries[entries.length - 1].isIntersecting;
      stage.classList.toggle('is-paused', !visible);
      root.classList.toggle('jx-paused', !visible);
      if (visible) scheduleIdle(); else clearTimeout(idleTimer);
    }, { rootMargin: '60px 0px' }).observe(stage);
  } else {
    scheduleIdle();
  }

  // When the intro is over, switch it off for good: its layers (and their will-change) are released and
  // nothing animates but the ring. The finished figure does not depend on the intro rules.
  var cleanTimer = 0;
  function settleIntro() {
    clearTimeout(cleanTimer);
    cleanTimer = setTimeout(function () {
      if (stage.getAttribute('data-intro') === 'on') stage.setAttribute('data-intro', 'off');
    }, 4300);
  }

  render();
  selfTimer = setTimeout(selfTest, 2900);   // the dial powers up as the render arrives
  settleIntro();
})();
