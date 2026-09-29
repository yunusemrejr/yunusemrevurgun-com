/* =============================================================================
   JELLO SHOP — Jell-omo's café.
   -----------------------------------------------------------------------------
   One hand-drawn room, one 3D jelly, coffee beans on the floor.

   - room.js   the painted café: walls, floor, window, bar, fireplace, lights
   - props.js  the furniture and the cat, depth-sorted against the jelly
   - jello3d.js  Jell-omo himself: a ray-marched 3D model drawn from a shader
   - audio.js  the band, the rain and the sound effects, all synthesised
   - draw.js   the inked, wobbling hand-drawn look

   Nothing is downloaded except the code. If WebGL is missing, Jell-omo falls
   back to the flat mascot picture and everything else still works.
   ========================================================================== */

import { createJelloAvatar } from 'jelloshop/jello3d';
import { createAudio } from 'jelloshop/audio';
import { P, clamp } from 'jelloshop/draw';
import {
  DW, DH, FLOOR_Y, LANES, CAT, MACHINE,
  scaleAt, leftX, rightX,
  paintShell, paintTop, drawWindowRain, drawFire, drawGlow, drawBulbs, drawSteam,
} from 'jelloshop/room';
import { drawArmchair, drawTable, drawStool, drawPlant, drawCat, drawHeart, drawBean } from 'jelloshop/props';

const canvas = document.getElementById('js-canvas');
if (canvas) boot(canvas);

function boot(canvas) {
  const ctx = canvas.getContext('2d');
  const stage = canvas.closest('.js-stage') || canvas.parentElement;
  const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const audio = createAudio();
  // ?flat forces the flat mascot, which is what a browser without WebGL gets.
  const avatar = /[?&]flat\b/.test(location.search) ? null : createJelloAvatar(384);

  // The flat mascot, for browsers with no WebGL.
  let flat = null;
  if (!avatar) {
    flat = new Image();
    flat.src = canvas.dataset.mascot || '';
  }

  // ------------------------------------------------------------------- state
  const game = {
    x: 470, y: 456,
    vx: 0, vy: 0,
    yaw: 0,
    lean: 0,
    capTilt: 0,
    squash: 0, squashV: 0,
    wob: 0,
    phase: 0,
    stepIndex: 0,
    mood: 0,
    idle: 0,
    sleep: 0,
    target: null,
    mouse: null,
    marker: null,
    puff: null,
    hearts: [],
    beans: 0,
    beanList: [],
    motes: [],
    cat: { wake: 0, until: 0 },
    started: false,
    lastT: 0,
    time: 0,
  };

  const WALK = 165;     // design px per second, sideways
  const DEPTH = 105;    // slower in depth, so depth changes read as turning

  // Furniture, by the ground point each stands on. Sorted against Jell-omo.
  const PROPS = [
    { y: 322, kind: 'stool', x: 472 },
    { y: 322, kind: 'stool', x: 542 },
    { y: 322, kind: 'stool', x: 612 },
    { y: 334, kind: 'plant', x: 190 },
    { y: CAT.y, kind: 'cat', x: CAT.x },
    { y: 414, kind: 'armchair', x: 136, c: [P.navy, P.navyLight, '#171d3a'] },
    { y: 404, kind: 'armchair', x: 828, c: [P.jelly, P.jellyLight, P.jellyDark] },
    { y: 492, kind: 'table', x: 322 },
    { y: 560, kind: 'plant', x: 60, small: true },
    { y: 566, kind: 'plant', x: 905 },
  ];

  // ------------------------------------------------------------ floor helpers
  function randomFloorPoint() {
    const y = LANES[Math.floor(Math.random() * LANES.length)];
    const pad = 46;
    const x = Math.random() * (rightX(y) - leftX(y) - pad * 2) + leftX(y) + pad;
    return { x, y };
  }

  // Keep the beans off the cat's cushion and the furniture's feet.
  function beanSpotOk(p) {
    if (Math.hypot(p.x - CAT.x, (p.y - CAT.y) * 1.6) < 70) return false;
    if (p.y < 350 && p.x > 640 && p.x < 840) return false;
    return true;
  }

  function spawnBean(p) {
    let at = p || randomFloorPoint();
    let tries = 0;
    while (!beanSpotOk(at) && tries++ < 20) at = randomFloorPoint();
    return { x: at.x, y: at.y, seed: Math.floor(Math.random() * 1000) };
  }

  function resetBeans() {
    game.beanList = [];
    for (let i = 0; i < 5; i++) {
      let b = spawnBean();
      let tries = 0;
      while (game.beanList.some((o) => Math.hypot(o.x - b.x, (o.y - b.y) * 1.4) < 130) && tries++ < 14) b = spawnBean();
      game.beanList.push(b);
    }
  }

  function initMotes() {
    game.motes = [];
    for (let i = 0; i < 30; i++) {
      game.motes.push({
        x: Math.random() * DW, y: Math.random() * DH,
        r: 0.8 + Math.random() * 1.6,
        vx: (Math.random() - 0.5) * 6, vy: -2 - Math.random() * 5,
        a: 0.12 + Math.random() * 0.24,
      });
    }
  }

  function resolveTarget(px, py) {
    let lane = LANES[0];
    let best = Infinity;
    for (const l of LANES) {
      const d = Math.abs(l - py);
      if (d < best) { best = d; lane = l; }
    }
    const pad = 38;
    return { x: clamp(px, leftX(lane) + pad, rightX(lane) - pad), y: lane };
  }

  function toDesign(clientX, clientY) {
    const r = canvas.getBoundingClientRect();
    return { x: ((clientX - r.left) / r.width) * DW, y: ((clientY - r.top) / r.height) * DH };
  }

  // ---------------------------------------------------------------- baking
  let shell = null;
  let top = null;
  let bakeScale = 1;

  function bake() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const r = canvas.getBoundingClientRect();
    const cssW = Math.max(1, r.width);
    const scale = cssW / DW;
    const w = Math.round(cssW * dpr);
    const h = Math.round(DH * scale * dpr);
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    bakeScale = scale * dpr;
    ctx.setTransform(bakeScale, 0, 0, bakeScale, 0, 0);

    const make = (paint) => {
      const c = document.createElement('canvas');
      c.width = w;
      c.height = h;
      const cx = c.getContext('2d');
      cx.setTransform(bakeScale, 0, 0, bakeScale, 0, 0);
      paint(cx);
      return c;
    };
    shell = make(paintShell);
    top = make(paintTop);
  }

  // --------------------------------------------------------------- the loop
  function update(dt) {
    const g = game;
    g.time += dt;
    const prevSpeed = Math.hypot(g.vx, g.vy);

    if (g.target) {
      const dx = g.target.x - g.x;
      const dy = g.target.y - g.y;
      const dist = Math.hypot(dx, dy);
      if (dist < 3) {
        g.target = null;
        g.vx = 0;
        g.vy = 0;
        g.wob = Math.min(1, g.wob + 0.7);
      } else {
        const nx = g.x + (dx / dist) * Math.min(WALK * dt, dist);
        const ny = g.y + Math.sign(dy) * Math.min(DEPTH * dt, Math.abs(dy));
        g.vx = (nx - g.x) / Math.max(dt, 1e-4);
        g.vy = (ny - g.y) / Math.max(dt, 1e-4);
        g.x = nx;
        g.y = ny;
        g.idle = 0;
      }
    } else {
      g.vx *= 0.8;
      g.vy *= 0.8;
      g.idle += dt;
    }

    // Which way he faces. Walking, it follows the walk; standing, he looks at you.
    let yawT = 0;
    const speed = Math.hypot(g.vx, g.vy);
    if (speed > 10) {
      const dx = g.vx / WALK;
      let dz = g.vy / DEPTH;
      // Sideways stays three-quarter so the face is still visible.
      const zf = dz >= -0.15 ? dz + 0.62 : dz;
      yawT = Math.atan2(dx, zf);
    } else if (g.mouse) {
      yawT = clamp(Math.atan2(g.mouse.x - g.x, 220), -0.9, 0.9);
    }
    // Turn by the short way round.
    let dyaw = yawT - g.yaw;
    while (dyaw > Math.PI) dyaw -= Math.PI * 2;
    while (dyaw < -Math.PI) dyaw += Math.PI * 2;
    g.yaw += dyaw * Math.min(1, dt * 9);

    // Leaning into the walk, and the cap lagging behind.
    g.lean += (clamp(g.vx / 900, -0.16, 0.16) - g.lean) * Math.min(1, dt * 10);
    const accel = (speed - prevSpeed) / Math.max(dt, 1e-3);
    g.capTilt += (clamp(-accel * 0.0006, -0.22, 0.22) - g.capTilt) * Math.min(1, dt * 6);

    // Walk cycle: a hop, a squash where it lands.
    const moving = speed > 10;
    if (moving) {
      g.phase += dt * (5.6 + speed * 0.012);
      const idx = Math.floor(g.phase / Math.PI);
      if (idx !== g.stepIndex) {
        g.stepIndex = idx;
        g.squashV -= 2.6;
        g.wob = Math.min(1, g.wob + 0.16);
        audio.step();
      }
    } else {
      g.phase += dt * 1.6;
    }
    // Squash: a damped spring, kicked at every footfall.
    g.squashV += (-g.squash * 210 - g.squashV * 13) * dt;
    g.squash += g.squashV * dt;
    g.wob = Math.max(0, g.wob - dt * 1.7);

    // Sleepy when left alone.
    const sleepT = g.idle > 16 ? 1 : 0;
    g.sleep += (sleepT - g.sleep) * Math.min(1, dt * 1.5);
    g.mood = Math.max(0, g.mood - dt * 1.4);

    // Beans.
    for (let i = g.beanList.length - 1; i >= 0; i--) {
      const b = g.beanList[i];
      if (Math.hypot(b.x - g.x, (b.y - g.y) * 0.45) < 48) {
        g.beanList.splice(i, 1);
        g.beans++;
        audio.bean();
        g.puff = { x: b.x, y: b.y, t: 0 };
        g.mood = 1;
        g.squashV += 3.2;
        g.wob = 1;
        syncCounter();
        setTimeout(() => g.beanList.push(spawnBean()), 280);
      }
    }
    if (g.puff) {
      g.puff.t += dt;
      if (g.puff.t > 0.7) g.puff = null;
    }

    // The cat: wakes when clicked or when Jell-omo walks close, drifts back to sleep.
    const nearCat = Math.hypot(g.x - CAT.x, (g.y - CAT.y) * 1.3) < 105;
    if (nearCat && g.cat.until < g.time + 1) g.cat.until = g.time + 1.6;
    const want = g.time < g.cat.until ? 1 : 0;
    g.cat.wake += (want - g.cat.wake) * Math.min(1, dt * (want ? 6 : 1.4));

    g.hearts = g.hearts.filter((h) => g.time - h.t0 < 1.1);

    for (const m of g.motes) {
      m.x += m.vx * dt;
      m.y += m.vy * dt;
      if (m.y < -10) { m.y = DH + 10; m.x = Math.random() * DW; }
      if (m.x < -10) m.x = DW + 10;
      if (m.x > DW + 10) m.x = -10;
    }
  }

  // Jell-omo, drawn as a sprite at his feet.
  const SPRITE = 226;
  function drawJello(c) {
    const g = game;
    const sc = scaleAt(g.y);
    const bounce = Math.abs(Math.sin(g.phase));
    const moving = Math.hypot(g.vx, g.vy) > 10;
    const hop = moving ? bounce * 9 * sc : Math.sin(g.time * 1.7) * 0.9;
    const sy = 1 + g.squash * 0.09 + (moving ? -0.03 * Math.cos(g.phase * 2) : Math.sin(g.time * 1.7 + 0.5) * 0.008);
    const sx = 1 - (sy - 1) * 0.6;

    // Shadow and the red light he throws on the floor.
    const shW = (66 - bounce * (moving ? 8 : 0)) * sc;
    const gl = c.createRadialGradient(g.x, g.y + 2, 4, g.x, g.y + 2, shW * 1.25);
    gl.addColorStop(0, 'rgba(255,60,50,0.28)');
    gl.addColorStop(1, 'rgba(255,60,50,0)');
    c.save();
    c.translate(g.x, g.y + 2);
    c.scale(1, 0.24);
    c.translate(-g.x, -(g.y + 2));
    c.fillStyle = gl;
    c.beginPath();
    c.arc(g.x, g.y + 2, shW * 1.25, 0, Math.PI * 2);
    c.fill();
    c.restore();
    const sh = c.createRadialGradient(g.x, g.y + 2, 2, g.x, g.y + 2, shW);
    sh.addColorStop(0, 'rgba(50,20,10,0.55)');
    sh.addColorStop(0.7, 'rgba(50,20,10,0.22)');
    sh.addColorStop(1, 'rgba(50,20,10,0)');
    c.save();
    c.translate(g.x, g.y + 2);
    c.scale(1, 0.26);
    c.translate(-g.x, -(g.y + 2));
    c.fillStyle = sh;
    c.beginPath();
    c.arc(g.x, g.y + 2, shW, 0, Math.PI * 2);
    c.fill();
    c.restore();

    const S = SPRITE * sc;
    if (avatar) {
      avatar.render({
        time: g.time, yaw: g.yaw, lean: g.lean, sx, sy,
        wob: g.wob, mood: g.mood, sleep: g.sleep, capTilt: g.capTilt,
      });
      // The figure's feet sit 81.5% of the way down the frame.
      c.drawImage(avatar.canvas, g.x - S / 2, g.y - hop - S * 0.815 + 4 * sc, S, S);
    } else if (flat && flat.complete && flat.naturalWidth) {
      const w = 132 * sc * sx;
      const h = 132 * sc * sy;
      c.save();
      c.translate(g.x, g.y - hop + 2);
      c.rotate(g.lean * 0.6);
      if (Math.sin(g.yaw) < -0.15) c.scale(-1, 1);
      c.drawImage(flat, -w / 2, -h, w, h);
      c.restore();
    }
  }

  function drawProp(c, p) {
    const s = scaleAt(p.y);
    switch (p.kind) {
      case 'stool': drawStool(c, p.x, p.y, s); break;
      case 'plant': drawPlant(c, p.x, p.y, s * (p.small ? 0.9 : 1.05), game.time, !p.small); break;
      case 'cat': drawCat(c, p.x, p.y, s * 1.05, game.time, game.cat.wake); break;
      case 'armchair': drawArmchair(c, p.x, p.y, s, ...p.c); break;
      case 'table': drawTable(c, p.x, p.y, s, game.time); break;
      default: break;
    }
  }

  function render() {
    const g = game;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (shell) ctx.drawImage(shell, 0, 0);
    ctx.setTransform(bakeScale, 0, 0, bakeScale, 0, 0);

    drawWindowRain(ctx, g.time);
    drawFire(ctx, g.time);
    drawGlow(ctx, g.time);
    drawBulbs(ctx, g.time);
    for (const [sx, sy] of MACHINE.spouts) drawSteam(ctx, sx, sy - 8, g.time, 0.55, sx * 0.01);

    // Everything on the floor, back to front.
    const list = PROPS.map((p) => ({ y: p.y, p }));
    for (const b of g.beanList) list.push({ y: b.y, b });
    list.push({ y: g.y, jello: true });
    list.sort((a, b) => a.y - b.y);
    for (const it of list) {
      if (it.jello) drawJello(ctx);
      else if (it.b) drawBean(ctx, it.b.x, it.b.y, scaleAt(it.b.y) * 1.05, g.time, it.b.seed);
      else drawProp(ctx, it.p);
    }

    // Dust in the lamp light.
    for (const m of g.motes) {
      ctx.fillStyle = `rgba(255,238,200,${m.a})`;
      ctx.beginPath();
      ctx.arc(m.x, m.y, m.r, 0, Math.PI * 2);
      ctx.fill();
    }

    if (g.marker && g.time - g.marker.t < 0.6) {
      const k = (g.time - g.marker.t) / 0.6;
      ctx.strokeStyle = `rgba(255,248,230,${0.85 * (1 - k)})`;
      ctx.lineWidth = 3;
      ctx.beginPath();
      ctx.ellipse(g.marker.x, g.marker.y, 10 + k * 26, (10 + k * 26) * 0.38, 0, 0, Math.PI * 2);
      ctx.stroke();
    }

    if (g.puff) {
      const k = g.puff.t / 0.7;
      for (let i = 0; i < 9; i++) {
        const a = (i / 9) * Math.PI * 2;
        const d = 12 + k * 42;
        ctx.fillStyle = i % 2 ? `rgba(255,224,150,${0.9 * (1 - k)})` : `rgba(255,255,255,${0.8 * (1 - k)})`;
        ctx.beginPath();
        ctx.arc(g.puff.x + Math.cos(a) * d, g.puff.y - 12 + Math.sin(a) * d * 0.5 - k * 24, 3.6 * (1 - k * 0.5), 0, Math.PI * 2);
        ctx.fill();
      }
    }
    for (const h of g.hearts) drawHeart(ctx, h.x, h.y, (g.time - h.t0) / 1.1);

    if (top) {
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.drawImage(top, 0, 0);
      ctx.setTransform(bakeScale, 0, 0, bakeScale, 0, 0);
    }
  }

  // ---------------------------------------------------------------- input
  function onDown(ev) {
    const p = toDesign(ev.clientX, ev.clientY);
    if (!game.started) begin();
    audio.wake();
    // Poke the cat.
    if (Math.abs(p.x - CAT.x) < 56 && p.y > CAT.y - 78 && p.y < CAT.y + 8) {
      game.cat.until = game.time + 3;
      game.hearts.push({ x: CAT.x - 20, y: CAT.y - 60, t0: game.time });
      audio.meow();
    }
    if (p.y < FLOOR_Y - 30) p.y = FLOOR_Y - 30;
    const t = resolveTarget(p.x, p.y);
    game.target = t;
    game.marker = { x: t.x, y: t.y, t: game.time };
    game.idle = 0;
  }

  function onMove(ev) {
    game.mouse = toDesign(ev.clientX, ev.clientY);
    game.idle = Math.min(game.idle, 4);
  }

  function begin() {
    if (game.started) return;
    game.started = true;
    stage.classList.add('is-playing');
    audio.start();
    setSoundLabel(true);
    setTimeout(() => {
      game.target = { x: 610, y: 456 };
      game.marker = { x: 610, y: 456, t: game.time };
    }, 320);
  }

  const startBtn = document.getElementById('js-start');
  if (startBtn) startBtn.addEventListener('click', begin);

  const counterEl = document.getElementById('js-beans');
  const liveEl = document.getElementById('js-beans-live');
  function syncCounter() {
    if (counterEl) counterEl.textContent = String(game.beans);
    if (liveEl) liveEl.textContent = `${game.beans} coffee bean${game.beans === 1 ? '' : 's'}`;
  }

  canvas.addEventListener('pointerdown', onDown);
  canvas.addEventListener('pointermove', onMove);
  canvas.addEventListener('pointerleave', () => { game.mouse = null; });
  canvas.addEventListener('contextmenu', (e) => e.preventDefault());

  const KEYS = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1], a: [-1, 0], d: [1, 0], w: [0, -1], s: [0, 1] };
  window.addEventListener('keydown', (e) => {
    const dir = KEYS[e.key];
    if (!dir || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
    e.preventDefault();
    if (!game.started) begin();
    let tx = game.target ? game.target.x : game.x;
    let ty = game.target ? game.target.y : game.y;
    tx += dir[0] * 90;
    ty += dir[1] * 34;
    const lane = LANES.reduce((a, l) => (Math.abs(l - ty) < Math.abs(a - ty) ? l : a), LANES[0]);
    game.target = resolveTarget(tx, lane);
    game.marker = { x: game.target.x, y: game.target.y, t: game.time };
    game.idle = 0;
  });

  const soundBtn = document.getElementById('js-sound');
  function setSoundLabel(on) {
    if (!soundBtn) return;
    soundBtn.setAttribute('aria-pressed', String(on));
    const label = soundBtn.querySelector('.js-sound-label');
    if (label) label.textContent = on ? 'Sound on' : 'Sound off';
  }
  if (soundBtn) {
    soundBtn.addEventListener('click', () => {
      if (audio.on) audio.mute();
      else audio.unmute();
      setSoundLabel(audio.on);
    });
  }

  // ---------------------------------------------------------------- resize
  let resizeTimer = null;
  const onResize = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(bake, 120);
  };
  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);

  let raf = null;
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      if (raf) cancelAnimationFrame(raf);
      raf = null;
    } else if (!raf) {
      game.lastT = performance.now();
      raf = requestAnimationFrame(frame);
    }
  });

  function frame(now) {
    raf = requestAnimationFrame(frame);
    const dt = Math.min(0.05, (now - game.lastT) / 1000 || 0.016);
    game.lastT = now;
    update(reduced ? dt * 0.6 : dt);
    render();
  }

  // Read-only view of the state for tests and console curiosity.
  window.jelloShop = {
    state: () => ({
      x: Math.round(game.x),
      y: Math.round(game.y),
      yaw: +game.yaw.toFixed(2),
      beans: game.beans,
      onFloor: game.beanList.length,
      walking: !!game.target,
      avatar: avatar ? '3d' : 'flat',
      audio: audio.ctx ? audio.ctx.state : 'off',
      sound: audio.on,
      catAwake: game.cat.wake > 0.5,
    }),
  };

  // ----------------------------------------------------------------- start
  resetBeans();
  initMotes();
  syncCounter();

  const go = () => {
    bake();
    game.lastT = performance.now();
    raf = requestAnimationFrame(frame);
  };
  // The chalkboard and the signs use the site's display face, so wait for it
  // before baking or the text would be baked in a fallback.
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(go).catch(go);
  else go();
}
