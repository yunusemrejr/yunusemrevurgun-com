/*
 * Cube-bot — miniature cube robot (two antennas) patrolling the safe area
 * inside the viewport. Remake of the duck-bot per user request.
 *
 * WHY separate file: landing-page-only easter egg; vanilla JS, no build step.
 * Palette (dev/new_design_plan_and_assets/palette.md): body #e3e2de,
 * slate #8490a4, sage #8fa6a6, ink #575757. No glow, no off-token colors.
 *
 * Movement rules ("extra smart"):
 * - SAFE_MARGIN is a hard keep-out zone: the robot never touches borders.
 * - Patrol path runs between the inner corners of the safe area; it pauses,
 *   looks around, and mostly keeps circling.
 * - Pointer (or touch-drag) within FLEE_RADIUS -> dashes to the farthest
 *   safe corner with panicked double-tempo animation, cooldown, resumes.
 * - If the user resizes the window so the robot's spot is no longer safe
 *   (e.g. shrinking), it immediately runs back the MINIMUM distance needed
 *   to be inside the new safe area — no more than necessary — then cools
 *   down and resumes patrol. Works on grow too (never needed, so it stays).
 * - Speed eases in/out instead of snapping (accel/decel).
 * - prefers-reduced-motion -> static perch inside the safe area.
 * Decorative: aria-hidden, pointer-events:none, transform-only animation.
 */
(() => {
  const REDUCED =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const TOUCH =
    window.matchMedia &&
    window.matchMedia("(hover: none) and (pointer: coarse)").matches;

  const el = document.querySelector(".cube-bot");
  if (!el) return;

  const SAFE_MARGIN = 46; // hard keep-out from every border (sprite-safe)
  const WALK_SPEED = 42; // px/s cruise
  const DASH_SPEED = 400; // px/s panicked dash
  const FLEE_RADIUS = 115; // pointer proximity that triggers a dash
  const COOLDOWN_MS = 1400; // pause after reaching safety
  const ACCEL = 9; // speed easing factor (higher = snappier)

  const inner = el.querySelector(".cube-bot-inner");
  let x = 0;
  let y = 0;
  let facing = 1; // 1 = right, -1 = left
  let mode = "walk"; // walk | idle | flee | recover | cooldown
  let modeUntil = 0;
  let target = null; // {x, y} while moving with intent
  let nextCornerIdx = 1;
  let speed = 0; // eased actual speed (px/s)
  let px = -9999; // pointer position
  let py = -9999;
  let last = null;
  let resizeDash = false; // dash caused by a resize (run back just enough)

  function safeCorners() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    const m = SAFE_MARGIN;
    return [
      { x: m, y: h - m }, // bottom-left (start)
      { x: w - m, y: h - m }, // bottom-right
      { x: w - m, y: m }, // top-right
      { x: m, y: m }, // top-left
    ];
  }

  function isSafe(px2, py2) {
    const w = window.innerWidth;
    const h = window.innerHeight;
    return (
      px2 >= SAFE_MARGIN &&
      px2 <= w - SAFE_MARGIN &&
      py2 >= SAFE_MARGIN &&
      py2 <= h - SAFE_MARGIN
    );
  }

  // nearest point inside the current safe area (the minimum move required)
  function nearestSafePoint() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    return {
      x: Math.max(SAFE_MARGIN, Math.min(w - SAFE_MARGIN, x)),
      y: Math.max(SAFE_MARGIN, Math.min(h - SAFE_MARGIN, y)),
    };
  }

  function setFacing(dx) {
    if (dx === 0) return;
    facing = dx > 0 ? 1 : -1;
    inner.style.transform = `scaleX(${facing})`;
  }

  function startFlee() {
    mode = "flee";
    resizeDash = false;
    const cs = safeCorners();
    let best = cs[0];
    let bestD = -1;
    for (let i = 0; i < cs.length; i++) {
      const d =
        (cs[i].x - px) * (cs[i].x - px) + (cs[i].y - py) * (cs[i].y - py);
      if (d > bestD) {
        bestD = d;
        best = cs[i];
      }
    }
    target = best;
    el.classList.add("is-fleeing");
  }

  function startRecover() {
    // resize pushed us (partly) out of the safe area: run back the minimum
    mode = "recover";
    resizeDash = true;
    target = nearestSafePoint();
    el.classList.add("is-fleeing");
  }

  window.addEventListener(
    "pointermove",
    (e) => {
      px = e.clientX;
      py = e.clientY;
    },
    { passive: true },
  );

  window.addEventListener("resize", () => {
    if (mode === "flee") return; // already dashing with intent
    if (!isSafe(x, y)) startRecover();
  });

  function step(dt, tx, ty, maxSpeed) {
    // ease actual speed toward the cap (accel/decel, no snapping)
    speed += (maxSpeed - speed) * Math.min(1, ACCEL * dt);
    const dx = tx - x;
    const dy = ty - y;
    const d = Math.sqrt(dx * dx + dy * dy);
    if (d <= Math.max(4, speed * dt)) {
      x = tx;
      y = ty;
      return true; // arrived
    }
    x += (dx / d) * speed * dt;
    y += (dy / d) * speed * dt;
    setFacing(dx);
    return false;
  }

  function tick(now) {
    if (!last) last = now;
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;

    // panic check — pointer close enough while on the ground (never mid-dash)
    if (mode === "walk" || mode === "idle") {
      const dxp = px - x;
      const dyp = py - y;
      if (px > -9000 && dxp * dxp + dyp * dyp < FLEE_RADIUS * FLEE_RADIUS) {
        startFlee();
      }
    }

    if (mode === "flee" || mode === "recover") {
      const fdx = target.x - x;
      const fdy = target.y - y;
      const fd = Math.sqrt(fdx * fdx + fdy * fdy);
      if (fd < 4) {
        // arrived exactly on target — clamp so the border rule holds exactly
        x = target.x;
        y = target.y;
        speed = 0;
        mode = "cooldown";
        modeUntil = now + (resizeDash ? 700 : COOLDOWN_MS);
        el.classList.remove("is-fleeing");
        // if the window kept shrinking mid-run, go again immediately
        if (!isSafe(x, y)) startRecover();
      } else {
        step(dt, target.x, target.y, DASH_SPEED);
      }
    } else if (mode === "cooldown") {
      if (now >= modeUntil) {
        mode = "walk";
        target = null;
      }
    } else if (mode === "idle") {
      if (now >= modeUntil) mode = "walk";
    } else {
      // walk
      if (!target) target = safeCorners()[nextCornerIdx];
      const dx = target.x - x;
      const dy = target.y - y;
      const d = Math.sqrt(dx * dx + dy * dy);
      if (d < 4) {
        // reached an inner corner — look around, pick a neighbor
        mode = "idle";
        modeUntil = now + 700 + Math.random() * 1100;
        const turn = Math.random() < 0.25 ? -1 : 1; // mostly keep circling
        nextCornerIdx = (nextCornerIdx + turn + 4) % 4;
        target = null;
      } else {
        step(dt, target.x, target.y, WALK_SPEED);
      }
    }

    el.style.transform = `translate3d(${x - 28}px, ${y - 26}px, 0)`;
    requestAnimationFrame(tick);
  }

  // init: start at a safe corner; perch statically if motion is unwanted
  const start = safeCorners()[0];
  x = start.x;
  y = start.y;
  if (REDUCED) {
    el.classList.add("is-perched");
    el.style.transform = `translate3d(${x - 28}px, ${y - 26}px, 0)`;
    return;
  }
  el.classList.add(TOUCH ? "is-touch" : "is-walking");
  requestAnimationFrame(tick);
})();
