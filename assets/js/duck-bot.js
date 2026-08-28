/*
 * Duck-bot — miniature duck-like robot patrolling the viewport corners.
 *
 * WHY separate file: landing-page-only easter egg; vanilla JS, no build step.
 * Palette (dev/new_design_plan_and_assets/palette.md): body #e3e2de,
 * slate #8490a4, sage #8fa6a6, ink #575757. No glow, no off-token colors.
 *
 * Behavior: waddles along the viewport perimeter, pauses at corners, mostly
 * keeps circling. Pointer (or touch-drag) within FLEE_RADIUS -> dashes to the
 * farthest corner, cooldown, resumes patrol. prefers-reduced-motion -> perches
 * statically in a corner. Decorative: aria-hidden, pointer-events:none,
 * transform-only animation.
 */
(() => {
  const REDUCED = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const TOUCH = window.matchMedia &&
    window.matchMedia('(hover: none) and (pointer: coarse)').matches;

  const el = document.querySelector('.duck-bot');
  if (!el) return;

  const PAD = 22;            // distance from viewport edge while patrolling
  const WALK_SPEED = 38;     // px/s
  const FLEE_SPEED = 380;    // px/s
  const FLEE_RADIUS = 110;   // pointer proximity that triggers a dash
  const COOLDOWN_MS = 1400;  // pause after reaching safety

  const inner = el.querySelector('.duck-bot-inner');
  let x = 0;
  let y = 0;
  let facing = 1;            // 1 = right, -1 = left
  let mode = 'walk';         // walk | idle | flee | cooldown
  let modeUntil = 0;
  let target = null;         // {x, y} while moving with intent
  let nextCornerIdx = 1;
  let px = -9999;            // pointer position
  let py = -9999;
  let last = null;

  function corners() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    return [
      { x: PAD, y: h - PAD },      // bottom-left (start)
      { x: w - PAD, y: h - PAD },  // bottom-right
      { x: w - PAD, y: PAD },      // top-right
      { x: PAD, y: PAD }           // top-left
    ];
  }

  function setFacing(dx) {
    if (dx === 0) return;
    facing = dx > 0 ? 1 : -1;
    inner.style.transform = `scaleX(${facing})`;
  }

  function startFlee() {
    mode = 'flee';
    const cs = corners();
    let best = cs[0];
    let bestD = -1;
    for (let i = 0; i < cs.length; i++) {
      const d = (cs[i].x - px) * (cs[i].x - px) + (cs[i].y - py) * (cs[i].y - py);
      if (d > bestD) {
        bestD = d;
        best = cs[i];
      }
    }
    target = best;
    el.classList.add('is-fleeing');
  }

  function clampToViewport() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    x = Math.max(PAD, Math.min(w - PAD, x));
    y = Math.max(PAD, Math.min(h - PAD, y));
  }

  window.addEventListener('pointermove', (e) => {
    px = e.clientX;
    py = e.clientY;
  }, { passive: true });

  window.addEventListener('resize', clampToViewport);

  function tick(now) {
    if (!last) last = now;
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;

    // panic check — pointer (or touch-drag) close enough (while on the ground,
    // i.e. walking/idle/cooldown; never mid-dash)
    if (mode === 'walk' || mode === 'idle') {
      const dxp = px - x;
      const dyp = py - y;
      if (px > -9000 && dxp * dxp + dyp * dyp < FLEE_RADIUS * FLEE_RADIUS) {
        startFlee();
      }
    }

    if (mode === 'flee') {
      const fdx = target.x - x;
      const fdy = target.y - y;
      const fd = Math.sqrt(fdx * fdx + fdy * fdy);
      if (fd < 6) {
        mode = 'cooldown';
        modeUntil = now + COOLDOWN_MS;
        el.classList.remove('is-fleeing');
      } else {
        x += (fdx / fd) * FLEE_SPEED * dt;
        y += (fdy / fd) * FLEE_SPEED * dt;
        setFacing(fdx);
      }
    } else if (mode === 'cooldown') {
      if (now >= modeUntil) {
        mode = 'walk';
        target = null;
      }
    } else if (mode === 'idle') {
      if (now >= modeUntil) mode = 'walk';
    } else { // walk
      if (!target) target = corners()[nextCornerIdx];
      const dx = target.x - x;
      const dy = target.y - y;
      const d = Math.sqrt(dx * dx + dy * dy);
      if (d < 4) {
        // reached a corner — look around, then pick a neighbor corner
        mode = 'idle';
        modeUntil = now + 700 + Math.random() * 1100;
        const turn = Math.random() < 0.25 ? -1 : 1; // mostly keep circling
        nextCornerIdx = (nextCornerIdx + turn + 4) % 4;
        target = null;
      } else {
        const sp = WALK_SPEED * dt;
        x += (dx / d) * sp;
        y += (dy / d) * sp;
        setFacing(dx);
      }
    }

    el.style.transform = `translate3d(${x - 28}px, ${y - 24}px, 0)`;
    requestAnimationFrame(tick);
  }

  // init: start at a corner; perch statically if the user prefers no motion
  const start = corners()[0];
  x = start.x;
  y = start.y;
  if (REDUCED) {
    el.classList.add('is-perched');
    el.style.transform = `translate3d(${x - 28}px, ${y - 24}px, 0)`;
    return;
  }
  el.classList.add(TOUCH ? 'is-touch' : 'is-walking');
  requestAnimationFrame(tick);
})();