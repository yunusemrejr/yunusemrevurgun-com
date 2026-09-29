/* =============================================================================
   JELLO SHOP — a small coffee shop for the mascot.
   -----------------------------------------------------------------------------
   Everything in this file is procedural: the room, the jell-o and the coffee
   beans are drawn with canvas paths, and the music and the walk sounds are
   synthesised with the Web Audio API. There are no images and no audio files to
   download, which is what keeps the whole shop light enough to open on a phone.

   The room borrows Club Penguin's 2006-2010 grammar rather than its artwork:
   a one-point-perspective box, a floor that widens towards the viewer, a
   character that walks in depth lanes and flips to face the cursor when it is
   standing still. The drawing is hand-sketched instead, with wobbling ink
   lines, and everything is drawn in the site's own palette.

   Draw order matters: the room shell is baked once into an offscreen canvas,
   then the floor furniture and the jell-o are drawn every frame, sorted by
   depth, so the jell-o can walk behind the counter and in front of the couches.
   ========================================================================== */

(() => {
  'use strict';

  // ---------------------------------------------------------------- design space
  // The room is authored in a fixed 960x600 space and letterboxed to fit
  // whatever screen it lands on, so a small phone simply gets a zoomed-out shop
  // rather than a cropped one.
  const DW = 960;
  const DH = 600;

  // One-point-perspective box. The back wall is a rectangle; the floor, walls
  // and ceiling fan out towards the viewer from it.
  const BACK = { x0: 190, y0: 50, x1: 770, y1: 300 };
  const FRONT = { x0: 20, y0: -40, x1: 940, y1: 600 };
  const FLOOR_Y = BACK.y1;

  // Walkable lanes, back to front. In Club Penguin the character is always on
  // one of a few rows; clicking picks the row you clicked in.
  const LANES = [352, 404, 462, 528];

  const scaleAt = (y) => 0.62 + ((y - FLOOR_Y) / (DH - FLOOR_Y)) * 0.45;
  const leftX = (y) => BACK.x0 + ((y - FLOOR_Y) / (DH - FLOOR_Y)) * (FRONT.x0 - BACK.x0);
  const rightX = (y) => BACK.x1 + ((y - FLOOR_Y) / (DH - FLOOR_Y)) * (FRONT.x1 - BACK.x1);

  const clamp = (v, lo, hi) => (v < lo ? lo : v > hi ? hi : v);
  const lerp = (a, b, t) => a + (b - a) * t;

  // ------------------------------------------------------------------- palette
  const C = {
    ceiling: '#E9DABE',
    wall: '#F0E2C8',
    wallShade: '#E0CCAB',
    wainscot: '#C89B68',
    wainscotDark: '#A87B4E',
    floor: '#C28F5C',
    floorDark: '#9E7045',
    seam: '#8A5F3B',
    navy: '#262E4D',
    navyLight: '#3D4770',
    cream: '#EEE2D7',
    creamShade: '#D6C6B6',
    sky: '#69B0F9',
    jelly: '#DB1816',
    brass: '#C9973F',
    brassLight: '#F0CE85',
    steel: '#C6CCD6',
    steelDark: '#868EA0',
    ink: '#2A2118',
    lamp: '#FFD79A',
  };

  // ============================================================== drawing utils

  // A seeded generator. The hand-drawn wobble is baked into the room, so it
  // must be identical every time the shop is drawn — otherwise the walls would
  // shimmer on every resize.
  function rng(seed) {
    let s = (seed >>> 0) || 1;
    return () => {
      s ^= s << 13; s >>>= 0;
      s ^= s >>> 17;
      s ^= s << 5; s >>>= 0;
      return s / 4294967296;
    };
  }

  function ink(ctx, rand, color, width, alpha = 1) {
    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.globalAlpha = alpha;
    ctx.stroke();
    ctx.globalAlpha = 1;
  }

  // A hand-drawn line: a straight run is drawn twice with a small random
  // offset, which is enough to read as pencil without looking broken.
  function sketchLine(ctx, x1, y1, x2, y2, o = {}) {
    const { color = C.ink, width = 3, alpha = 1, rand = Math.random, amount = 1.4 } = o;
    ctx.beginPath();
    ctx.moveTo(x1 + (rand() * 2 - 1) * amount, y1 + (rand() * 2 - 1) * amount);
    ctx.lineTo(x2 + (rand() * 2 - 1) * amount, y2 + (rand() * 2 - 1) * amount);
    ink(ctx, rand, color, width, alpha);
  }

  function sketchPoly(ctx, pts, o = {}) {
    const { color = C.ink, width = 3, alpha = 1, close = false, rand = Math.random, amount = 1.4 } = o;
    const j = (x, y) => [x + (rand() * 2 - 1) * amount, y + (rand() * 2 - 1) * amount];
    ctx.beginPath();
    let [x, y] = j(pts[0][0], pts[0][1]);
    ctx.moveTo(x, y);
    for (let i = 1; i < pts.length; i++) {
      const [px, py] = j(pts[i][0], pts[i][1]);
      ctx.lineTo(px, py);
    }
    if (close) ctx.closePath();
    ink(ctx, rand, color, width, alpha);
  }

  function sketchRect(ctx, x, y, w, h, o = {}) {
    sketchPoly(ctx, [[x, y], [x + w, y], [x + w, y + h], [x, y + h]], { ...o, close: true });
  }

  // An ellipse sampled into a wobbling polygon, so even round shapes look drawn.
  function sketchEllipse(ctx, cx, cy, rx, ry, o = {}) {
    const { steps = 26, amount = 1.2, rand = Math.random } = o;
    const pts = [];
    for (let i = 0; i < steps; i++) {
      const a = (i / steps) * Math.PI * 2;
      const wob = 1 + (rand() * 2 - 1) * 0.035;
      pts.push([cx + Math.cos(a) * rx * wob, cy + Math.sin(a) * ry * wob]);
    }
    sketchPoly(ctx, pts, { ...o, close: true, amount });
  }

  // A soft wobbly fill, used for large flat areas like the walls and the floor.
  function fillWobbly(ctx, pts, fill, rand, amount = 1.6) {
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length; i++) {
      ctx.lineTo(pts[i][0] + (rand() * 2 - 1) * amount, pts[i][1] + (rand() * 2 - 1) * amount);
    }
    ctx.closePath();
    ctx.fillStyle = fill;
    ctx.fill();
  }

  function quad(ctx, pts, fill) {
    ctx.beginPath();
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
    ctx.closePath();
    ctx.fillStyle = fill;
    ctx.fill();
  }

  function roundRectPath(ctx, x, y, w, h, r) {
    const rr = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + rr, y);
    ctx.arcTo(x + w, y, x + w, y + h, rr);
    ctx.arcTo(x + w, y + h, x, y + h, rr);
    ctx.arcTo(x, y + h, x, y, rr);
    ctx.arcTo(x, y, x + w, y, rr);
    ctx.closePath();
  }

  // Text that looks written rather than typeset: a small rotation and a slight
  // per-letter drift, drawn with the site's rounded display face.
  function handText(ctx, text, x, y, o = {}) {
    const { size = 22, color = C.ink, align = 'center', rotate = 0, weight = 600, family = "'Fredoka', system-ui, sans-serif", rand = Math.random, alpha = 1 } = o;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rotate);
    ctx.font = `${weight} ${size}px ${family}`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.globalAlpha = alpha;
    ctx.fillStyle = color;
    const chars = [...text];
    let total = 0;
    for (const ch of chars) total += ctx.measureText(ch).width;
    let cx = align === 'center' ? -total / 2 : align === 'right' ? -total : 0;
    for (const ch of chars) {
      const w = ctx.measureText(ch).width;
      ctx.save();
      ctx.translate(cx + w / 2, (rand() * 2 - 1) * (size * 0.035));
      ctx.rotate((rand() * 2 - 1) * 0.05);
      ctx.fillText(ch, -w / 2, 0);
      ctx.restore();
      cx += w;
    }
    ctx.restore();
    ctx.globalAlpha = 1;
  }

  // ================================================================ room shell

  function floorPoint(t) {
    // t in [0,1] back to front
    return { y: lerp(FLOOR_Y, DH, t), l: leftX(lerp(FLOOR_Y, DH, t)), r: rightX(lerp(FLOOR_Y, DH, t)) };
  }

  function paintRoom(ctx) {
    const rand = rng(0x5eed1a);

    // Ceiling, back wall, side walls — the box itself.
    quad(ctx, [[BACK.x0, BACK.y0], [BACK.x1, BACK.y0], [FRONT.x1, FRONT.y0], [FRONT.x0, FRONT.y0]], C.ceiling);

    // Left and right walls get a gradient so the box reads as turning away.
    const gl = ctx.createLinearGradient(BACK.x0, 0, FRONT.x0, 0);
    gl.addColorStop(0, C.wallShade);
    gl.addColorStop(1, C.wall);
    quad(ctx, [[BACK.x0, BACK.y0], [BACK.x0, BACK.y1], [FRONT.x0, FRONT.y1], [FRONT.x0, FRONT.y0]], gl);
    const gr = ctx.createLinearGradient(BACK.x1, 0, FRONT.x1, 0);
    gr.addColorStop(0, C.wall);
    gr.addColorStop(1, C.wallShade);
    quad(ctx, [[BACK.x1, BACK.y0], [BACK.x1, BACK.y1], [FRONT.x1, FRONT.y1], [FRONT.x1, FRONT.y0]], gr);

    // Back wall.
    const gb = ctx.createLinearGradient(0, BACK.y0, 0, BACK.y1);
    gb.addColorStop(0, '#F6EBD6');
    gb.addColorStop(1, C.wall);
    quad(ctx, [[BACK.x0, BACK.y0], [BACK.x1, BACK.y0], [BACK.x1, BACK.y1], [BACK.x0, BACK.y1]], gb);

    // Floor.
    const gf = ctx.createLinearGradient(0, FLOOR_Y, 0, DH);
    gf.addColorStop(0, '#A87A4B');
    gf.addColorStop(0.45, C.floor);
    gf.addColorStop(1, '#CE9C68');
    quad(ctx, [[BACK.x0, FLOOR_Y], [BACK.x1, FLOOR_Y], [FRONT.x1, DH], [FRONT.x0, DH]], gf);

    // Floorboards: seams fan out from the back wall towards the viewer.
    const BOARDS = 16;
    for (let i = 0; i <= BOARDS; i++) {
      const t = i / BOARDS;
      const bx = lerp(BACK.x0, BACK.x1, t);
      const fx = lerp(FRONT.x0, FRONT.x1, t);
      const c = C.seam;
      sketchLine(ctx, bx, FLOOR_Y, fx, DH, { color: c, width: 1.8, alpha: 0.5, rand, amount: 0.8 });
    }
    // Cross joints, a little denser towards the viewer.
    for (let i = 1; i < 7; i++) {
      const p = floorPoint(i / 7);
      sketchLine(ctx, p.l, p.y, p.r, p.y, { color: C.seam, width: 1.4, alpha: 0.3, rand, amount: 0.7 });
    }
    // Vanishing hint: a soft shadow where the wall meets the floor.
    const gsh = ctx.createLinearGradient(0, FLOOR_Y, 0, FLOOR_Y + 46);
    gsh.addColorStop(0, 'rgba(74,45,22,0.45)');
    gsh.addColorStop(1, 'rgba(74,45,22,0)');
    ctx.fillStyle = gsh;
    ctx.fillRect(BACK.x0 - 40, FLOOR_Y, BACK.x1 - BACK.x0 + 80, 46);

    // Wainscot: a wooden dado running around the room, the thing that makes
    // the walls read as a real surface rather than a backdrop.
    const dadoTop = 214;
    const wallFaces = [
      { pts: [[BACK.x0, dadoTop], [BACK.x1, dadoTop], [BACK.x1, BACK.y1], [BACK.x0, BACK.y1]] },
      { pts: [[BACK.x0, BACK.y0 + 40], [BACK.x0, dadoTop], [FRONT.x0, FRONT.y1 - 210], [FRONT.x0, FRONT.y0 + 20]] },
      { pts: [[BACK.x1, BACK.y0 + 40], [BACK.x1, dadoTop], [FRONT.x1, FRONT.y1 - 210], [FRONT.x1, FRONT.y0 + 20]] },
    ];
    for (const f of wallFaces) {
      quad(ctx, f.pts, C.wainscot);
      sketchPoly(ctx, f.pts, { color: C.wainscotDark, width: 2.4, alpha: 0.85, close: true, rand, amount: 1.2 });
    }
    // Chair rail.
    sketchLine(ctx, BACK.x0, dadoTop, BACK.x1, dadoTop, { color: C.wainscotDark, width: 4, alpha: 0.9, rand, amount: 1 });
    sketchLine(ctx, BACK.x0, BACK.y1 - 4, BACK.x1, BACK.y1 - 4, { color: C.wainscotDark, width: 3, alpha: 0.7, rand, amount: 1 });

    // Wainscot panels on the back wall.
    for (let i = 0; i < 6; i++) {
      const x = BACK.x0 + 14 + i * ((BACK.x1 - BACK.x0 - 28) / 6);
      const w = (BACK.x1 - BACK.x0 - 28) / 6 - 10;
      sketchRect(ctx, x, dadoTop + 12, w, 62, { color: C.wainscotDark, width: 2, alpha: 0.55, rand, amount: 1.1 });
    }

    // Corner ink: the box edges, drawn by hand so the room has a drawn edge.
    const edges = [
      [[BACK.x0, BACK.y0], [FRONT.x0, FRONT.y0]],
      [[BACK.x1, BACK.y0], [FRONT.x1, FRONT.y0]],
      [[BACK.x0, BACK.y0], [BACK.x0, BACK.y1]],
      [[BACK.x1, BACK.y0], [BACK.x1, BACK.y1]],
      [[BACK.x0, BACK.y1], [BACK.x1, BACK.y1]],
      [[FRONT.x0, FRONT.y0], [FRONT.x0, FRONT.y1]],
      [[FRONT.x1, FRONT.y0], [FRONT.x1, FRONT.y1]],
    ];
    for (const [a, b] of edges) {
      sketchLine(ctx, a[0], a[1], b[0], b[1], { color: C.ink, width: 3.4, alpha: 0.8, rand, amount: 1.1 });
    }
    // Where the walls meet the floor.
    sketchLine(ctx, BACK.x0, BACK.y1, FRONT.x0, DH, { color: C.ink, width: 3, alpha: 0.7, rand, amount: 1 });
    sketchLine(ctx, BACK.x1, BACK.y1, FRONT.x1, DH, { color: C.ink, width: 3, alpha: 0.7, rand, amount: 1 });

    // --------------------------------------------------------- back wall items

    // Window: a rainy evening outside, warm light inside.
    const win = { x: 224, y: 104, w: 150, h: 128 };
    ctx.fillStyle = C.cream;
    roundRectPath(ctx, win.x - 8, win.y - 8, win.w + 16, win.h + 16, 6);
    ctx.fill();
    const sky = ctx.createLinearGradient(0, win.y, 0, win.y + win.h);
    sky.addColorStop(0, '#2A3358');
    sky.addColorStop(1, '#4A4E7A');
    ctx.fillStyle = sky;
    ctx.fillRect(win.x, win.y, win.w, win.h);
    // Buildings and a few lit windows.
    ctx.fillStyle = '#1B2140';
    ctx.fillRect(win.x + 6, win.y + 62, 34, 70);
    ctx.fillRect(win.x + 48, win.y + 44, 40, 88);
    ctx.fillRect(win.x + 96, win.y + 70, 30, 62);
    const lit = [[12, 74, 6, 8], [24, 92, 6, 8], [56, 58, 7, 9], [70, 84, 7, 9], [56, 100, 7, 9], [104, 86, 6, 8]];
    for (const [dx, dy, w, h] of lit) {
      ctx.fillStyle = 'rgba(255,214,150,0.85)';
      ctx.fillRect(win.x + dx, win.y + dy, w, h);
    }
    // Rain.
    ctx.strokeStyle = 'rgba(210,225,255,0.35)';
    ctx.lineWidth = 1.2;
    for (let i = 0; i < 26; i++) {
      const rx = win.x + rand() * win.w;
      const ry = win.y + rand() * win.h;
      ctx.beginPath();
      ctx.moveTo(rx, ry);
      ctx.lineTo(rx - 4, ry + 13);
      ctx.stroke();
    }
    // Glazing bars and frame.
    sketchRect(ctx, win.x, win.y, win.w, win.h, { color: C.creamShade, width: 5, rand, amount: 1 });
    sketchLine(ctx, win.x + win.w / 2, win.y, win.x + win.w / 2, win.y + win.h, { color: C.creamShade, width: 4, rand, amount: 0.8 });
    sketchLine(ctx, win.x, win.y + win.h * 0.42, win.x + win.w, win.y + win.h * 0.42, { color: C.creamShade, width: 4, rand, amount: 0.8 });
    // Sill.
    ctx.fillStyle = C.cream;
    ctx.fillRect(win.x - 14, win.y + win.h + 6, win.w + 28, 12);
    sketchLine(ctx, win.x - 14, win.y + win.h + 6, win.x + win.w + 14, win.y + win.h + 6, { color: C.ink, width: 2.4, alpha: 0.7, rand });
    // A little pot on the sill.
    ctx.fillStyle = C.jelly;
    ctx.beginPath();
    ctx.moveTo(win.x + 24, win.y + win.h + 6);
    ctx.lineTo(win.x + 42, win.y + win.h + 6);
    ctx.lineTo(win.x + 38, win.y + win.h - 10);
    ctx.lineTo(win.x + 28, win.y + win.h - 10);
    ctx.closePath();
    ctx.fill();
    for (let i = 0; i < 5; i++) {
      const a = -Math.PI / 2 + (i - 2) * 0.34;
      ctx.strokeStyle = '#4E8C61';
      ctx.lineWidth = 3.5;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(win.x + 33, win.y + win.h - 10);
      ctx.quadraticCurveTo(win.x + 33 + Math.cos(a) * 10, win.y + win.h - 20 + Math.sin(a) * 6, win.x + 33 + Math.cos(a) * 18, win.y + win.h - 26 + Math.sin(a) * 16);
      ctx.stroke();
    }

    // Menu board: a chalkboard with the day's list, written out by hand.
    const menu = { x: 400, y: 96, w: 146, h: 138 };
    ctx.fillStyle = C.wainscotDark;
    roundRectPath(ctx, menu.x - 7, menu.y - 7, menu.w + 14, menu.h + 14, 7);
    ctx.fill();
    const chalk = ctx.createLinearGradient(0, menu.y, 0, menu.y + menu.h);
    chalk.addColorStop(0, '#2F3A44');
    chalk.addColorStop(1, '#232B33');
    ctx.fillStyle = chalk;
    ctx.fillRect(menu.x, menu.y, menu.w, menu.h);
    // Chalk dust around the edges.
    ctx.fillStyle = 'rgba(240,240,235,0.10)';
    ctx.fillRect(menu.x + 3, menu.y + 3, menu.w - 6, 8);
    handText(ctx, 'TODAY', menu.x + menu.w / 2, menu.y + 24, { size: 19, color: '#F2EDE2', rand });
    sketchLine(ctx, menu.x + 22, menu.y + 36, menu.x + menu.w - 22, menu.y + 36, { color: 'rgba(242,237,226,0.6)', width: 1.6, rand, amount: 1 });
    const items = [['espresso', '2'], ['latte', '3'], ['mocha', '4'], ['jello cc', '5']];
    items.forEach(([name, price], i) => {
      const y = menu.y + 58 + i * 21;
      handText(ctx, name, menu.x + 18, y, { size: 14, color: '#E8E2D4', align: 'left', rand });
      handText(ctx, price, menu.x + menu.w - 18, y, { size: 14, color: '#E8E2D4', align: 'right', rand });
      ctx.fillStyle = 'rgba(232,226,212,0.35)';
      for (let d = 0; d < 3; d++) ctx.fillRect(menu.x + 18 + name.length * 7 + d * 6 + 4, y + 3, 2, 2);
    });
    sketchRect(ctx, menu.x, menu.y, menu.w, menu.h, { color: 'rgba(0,0,0,0.35)', width: 2, rand, amount: 1 });

    // The door. Closed: this shop is one room and it says so.
    const door = { x: 592, y: 62, w: 152, h: 238 };
    ctx.fillStyle = C.wainscotDark;
    ctx.fillRect(door.x - 10, door.y - 6, door.w + 20, 8);
    const dg = ctx.createLinearGradient(door.x, 0, door.x + door.w, 0);
    dg.addColorStop(0, '#8A5F3B');
    dg.addColorStop(0.5, '#A87B4E');
    dg.addColorStop(1, '#7E5636');
    ctx.fillStyle = dg;
    ctx.fillRect(door.x, door.y, door.w, door.h);
    for (let i = 0; i < 2; i++) {
      for (let j = 0; j < 3; j++) {
        sketchRect(ctx, door.x + 16 + j * 42, door.y + 20 + i * 96, 32, 74, { color: '#6E4A2E', width: 2.4, alpha: 0.8, rand, amount: 1.1 });
      }
    }
    // Handle.
    ctx.fillStyle = C.brass;
    ctx.beginPath();
    ctx.arc(door.x + door.w - 22, door.y + 132, 7, 0, Math.PI * 2);
    ctx.fill();
    sketchLine(ctx, door.x + door.w - 22, door.y + 139, door.x + door.w - 22, door.y + 158, { color: C.brass, width: 4, rand, amount: 0.8 });
    sketchRect(ctx, door.x, door.y, door.w, door.h, { color: C.ink, width: 3.2, alpha: 0.8, rand, amount: 1.1 });

    // A hanging sign on the door.
    ctx.save();
    ctx.translate(door.x + door.w / 2, door.y + 56);
    ctx.rotate(-0.045);
    ctx.fillStyle = C.cream;
    roundRectPath(ctx, -46, -20, 92, 40, 5);
    ctx.fill();
    sketchRect(ctx, -46, -20, 92, 40, { color: C.ink, width: 2.4, rand, amount: 1 });
    handText(ctx, 'CLOSED', 0, -4, { size: 19, color: C.ink, rand });
    handText(ctx, 'back soon', 0, 12, { size: 11, color: '#7a6a5c', rand });
    ctx.restore();
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(door.x + door.w / 2 - 40, door.y + 36);
    ctx.lineTo(door.x + door.w / 2, door.y + 30);
    ctx.moveTo(door.x + door.w / 2 + 40, door.y + 36);
    ctx.lineTo(door.x + door.w / 2, door.y + 30);
    ctx.stroke();

    // A taped-up note, because the shop is being tidied.
    ctx.save();
    ctx.translate(door.x + 40, door.y + 176);
    ctx.rotate(0.07);
    ctx.fillStyle = '#FFF6DC';
    ctx.fillRect(-28, -22, 56, 44);
    sketchLine(ctx, -28, -22, 28, -22, { color: C.ink, width: 1.8, alpha: 0.6, rand, amount: 1 });
    handText(ctx, 'sweeping', 0, -8, { size: 10, color: '#6a5c50', rand });
    handText(ctx, 'the floor', 0, 4, { size: 10, color: '#6a5c50', rand });
    ctx.restore();
    ctx.fillStyle = 'rgba(255,255,255,0.5)';
    ctx.fillRect(door.x + 18, door.y + 148, 18, 8);

    // Framed print on the left wall.
    ctx.save();
    ctx.translate(120, 150);
    ctx.transform(1, 0, -0.28, 1, 0, 0);
    ctx.fillStyle = C.cream;
    roundRectPath(ctx, -26, -34, 52, 68, 4);
    ctx.fill();
    sketchRect(ctx, -26, -34, 52, 68, { color: C.ink, width: 2.6, rand, amount: 1 });
    ctx.fillStyle = C.sky;
    ctx.fillRect(-18, -26, 36, 30);
    ctx.fillStyle = '#4E8C61';
    ctx.beginPath();
    ctx.moveTo(-18, 4); ctx.lineTo(-2, -12); ctx.lineTo(18, 4); ctx.closePath(); ctx.fill();
    ctx.fillStyle = '#F0CE85';
    ctx.beginPath(); ctx.arc(6, -18, 5, 0, Math.PI * 2); ctx.fill();
    ctx.restore();

    // Shelf with cups on the left wall.
    ctx.save();
    ctx.transform(1, 0, -0.28, 1, 0, 0);
    ctx.fillStyle = C.wainscotDark;
    ctx.fillRect(-6, 226, 76, 8);
    sketchLine(ctx, -6, 226, 70, 226, { color: C.ink, width: 2.4, alpha: 0.7, rand, amount: 1 });
    for (let i = 0; i < 3; i++) {
      ctx.fillStyle = [C.cream, C.jelly, C.navy][i];
      ctx.beginPath();
      ctx.arc(12 + i * 24, 218, 8, Math.PI, 0);
      ctx.rect(4 + i * 24, 218, 16, 8);
      ctx.fill();
      sketchLine(ctx, 4 + i * 24, 226, 20 + i * 24, 226, { color: C.ink, width: 1.8, alpha: 0.6, rand, amount: 0.8 });
    }
    ctx.restore();

    // -------------------------------------------------------------- floor rug
    const rugY = 430;
    const rl = leftX(rugY) + 120;
    const rr = rightX(rugY) - 150;
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(rl + 60, rugY);
    ctx.quadraticCurveTo((rl + rr) / 2, rugY - 22, rr - 60, rugY);
    ctx.quadraticCurveTo(rr + 26, rugY + 52, rr - 70, rugY + 66);
    ctx.quadraticCurveTo((rl + rr) / 2, rugY + 80, rl + 66, rugY + 62);
    ctx.quadraticCurveTo(rl - 22, rugY + 46, rl + 60, rugY);
    ctx.closePath();
    const rugG = ctx.createLinearGradient(0, rugY - 20, 0, rugY + 80);
    rugG.addColorStop(0, '#4E6E8E');
    rugG.addColorStop(1, '#3A5570');
    ctx.fillStyle = rugG;
    ctx.fill();
    ctx.save();
    ctx.clip();
    ctx.strokeStyle = 'rgba(238,226,215,0.5)';
    ctx.lineWidth = 2.4;
    for (let i = 0; i < 7; i++) {
      ctx.beginPath();
      ctx.ellipse((rl + rr) / 2, rugY + 24, 40 + i * 26, 12 + i * 9, 0, 0, Math.PI * 2);
      ctx.stroke();
    }
    ctx.restore();
    ctx.restore();
    sketchLine(ctx, rl + 60, rugY, rr - 60, rugY, { color: C.ink, width: 2, alpha: 0.35, rand, amount: 1.2 });
    // Fringe.
    for (let i = 0; i < 22; i++) {
      const t = i / 21;
      const x = lerp(rl + 8, rr - 12, t);
      const y = rugY + 64 + Math.sin(t * Math.PI) * 12;
      sketchLine(ctx, x, y, x + (rand() * 6 - 3), y + 9, { color: '#C8B49A', width: 2, alpha: 0.8, rand, amount: 0.6 });
    }

    // ------------------------------------------------------------ pendant lamps
    // Lamps are part of the room shell, but their glow pulses, so only the
    // fixtures are baked.
    for (const lamp of LAMPS) {
      const p = floorPoint(lamp.t);
      const s = scaleAt(p.y);
      const x = lerp(p.l, p.r, lamp.u);
      const topY = lerp(BACK.y0, FRONT.y0, lamp.t);
      ctx.strokeStyle = C.ink;
      ctx.lineWidth = 2.4;
      ctx.beginPath();
      ctx.moveTo(x, topY);
      ctx.lineTo(x, topY + 46 * s);
      ctx.stroke();
      // Shade.
      ctx.beginPath();
      ctx.moveTo(x - 30 * s, topY + 82 * s);
      ctx.quadraticCurveTo(x, topY + 40 * s, x + 30 * s, topY + 82 * s);
      ctx.closePath();
      const sg = ctx.createLinearGradient(x - 30 * s, topY + 40 * s, x + 30 * s, topY + 86 * s);
      sg.addColorStop(0, C.brassLight);
      sg.addColorStop(1, '#A87A34');
      ctx.fillStyle = sg;
      ctx.fill();
      sketchLine(ctx, x - 30 * s, topY + 82 * s, x + 30 * s, topY + 82 * s, { color: C.ink, width: 2.6, alpha: 0.8, rand, amount: 1 });
      // Bulb.
      ctx.fillStyle = C.lamp;
      ctx.beginPath();
      ctx.arc(x, topY + 84 * s, 7 * s, 0, Math.PI * 2);
      ctx.fill();
    }

    // Warm pools of light on the floor beneath the lamps.
    for (const lamp of LAMPS) {
      const p = floorPoint(lamp.t + 0.16);
      const x = lerp(p.l, p.r, lamp.u);
      const g = ctx.createRadialGradient(x, p.y, 4, x, p.y, 190);
      g.addColorStop(0, 'rgba(255,214,150,0.34)');
      g.addColorStop(0.5, 'rgba(255,214,150,0.12)');
      g.addColorStop(1, 'rgba(255,214,150,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.ellipse(x, p.y, 190, 66, 0, 0, Math.PI * 2);
      ctx.fill();
    }

    // Ambient vignette, drawn last so it sits over the whole room.
    const vg = ctx.createRadialGradient(DW / 2, DH * 0.52, DH * 0.28, DW / 2, DH * 0.52, DH * 0.95);
    vg.addColorStop(0, 'rgba(60,32,10,0)');
    vg.addColorStop(1, 'rgba(60,32,10,0.42)');
    ctx.fillStyle = vg;
    ctx.fillRect(0, 0, DW, DH);
  }

  const LAMPS = [
    { u: 0.24, t: 0.1 },
    { u: 0.58, t: 0.44 },
    { u: 0.3, t: 0.76 },
  ];

  // ============================================================= floor furniture
  // Each item is anchored at (x, y) on the floor and drawn in its own local
  // units, scaled by its depth. They are re-drawn every frame (there are only
  // a handful) so the jell-o can pass in front of and behind them.

  function shadowUnder(ctx, x, y, w, alpha = 0.22) {
    const g = ctx.createRadialGradient(x, y, 1, x, y, w);
    g.addColorStop(0, `rgba(50,28,12,${alpha})`);
    g.addColorStop(1, 'rgba(50,28,12,0)');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.ellipse(x, y, w, w * 0.26, 0, 0, Math.PI * 2);
    ctx.fill();
  }

  function drawCounter(ctx, x, y, s) {
    shadowUnder(ctx, x, y, 150 * s, 0.26);
    const w = 150;
    const h = 96;
    // Body.
    const g = ctx.createLinearGradient(0, y - h * s, 0, y);
    g.addColorStop(0, '#7A5230');
    g.addColorStop(1, '#5E3D23');
    ctx.fillStyle = g;
    roundRectPath(ctx, x - w * s, y - h * s, w * 2 * s, h * s, 6 * s);
    ctx.fill();
    // Front panels.
    for (let i = 0; i < 4; i++) {
      const px = x - w * s + (12 + i * 68) * s;
      ctx.strokeStyle = 'rgba(40,24,12,0.5)';
      ctx.lineWidth = 2 * s;
      roundRectPath(ctx, px, y - (h - 16) * s, 54 * s, (h - 30) * s, 3 * s);
      ctx.stroke();
    }
    // Top slab, overhanging.
    ctx.fillStyle = '#EFE3CE';
    roundRectPath(ctx, x - (w + 12) * s, y - (h + 14) * s, (w * 2 + 24) * s, 18 * s, 5 * s);
    ctx.fill();
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2.6 * s;
    ctx.globalAlpha = 0.75;
    roundRectPath(ctx, x - (w + 12) * s, y - (h + 14) * s, (w * 2 + 24) * s, 18 * s, 5 * s);
    ctx.stroke();
    ctx.globalAlpha = 1;
    // Kick board.
    ctx.fillStyle = '#4A2F1B';
    ctx.fillRect(x - w * s, y - 10 * s, w * 2 * s, 10 * s);
  }

  // The espresso machine: the heart of the shop, chrome and brass with a
  // warm red badge so it belongs to this brand rather than another one.
  function drawMachine(ctx, x, y, s) {
    const w = 66;
    const h = 74;
    const top = y - h * s;
    // Body with vertical chrome banding.
    const g = ctx.createLinearGradient(x - w * s, 0, x + w * s, 0);
    g.addColorStop(0, '#7E8698');
    g.addColorStop(0.18, '#DDE2EA');
    g.addColorStop(0.34, '#9BA3B4');
    g.addColorStop(0.52, '#EDF0F5');
    g.addColorStop(0.72, '#8F97A8');
    g.addColorStop(1, '#6E7688');
    ctx.fillStyle = g;
    roundRectPath(ctx, x - w * s, top, w * 2 * s, h * s, 7 * s);
    ctx.fill();
    // Domed top.
    ctx.beginPath();
    ctx.moveTo(x - w * s, top + 12 * s);
    ctx.quadraticCurveTo(x, top - 16 * s, x + w * s, top + 12 * s);
    ctx.closePath();
    ctx.fillStyle = '#E4E8EF';
    ctx.fill();
    // Red badge.
    ctx.fillStyle = C.jelly;
    roundRectPath(ctx, x - 20 * s, top + 20 * s, 40 * s, 13 * s, 4 * s);
    ctx.fill();
    // Dials.
    for (const dx of [-38, 38]) {
      ctx.fillStyle = C.brass;
      ctx.beginPath();
      ctx.arc(x + dx * s, top + 26 * s, 8 * s, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = '#5A421A';
      ctx.lineWidth = 1.6 * s;
      ctx.beginPath();
      ctx.moveTo(x + dx * s, top + 26 * s);
      ctx.lineTo(x + dx * s + 4 * s, top + 21 * s);
      ctx.stroke();
    }
    // Group heads and portafilters.
    for (const dx of [-24, 24]) {
      ctx.fillStyle = C.brassLight;
      roundRectPath(ctx, x + dx * s - 13 * s, top + 40 * s, 26 * s, 12 * s, 3 * s);
      ctx.fill();
      ctx.fillStyle = '#6E7688';
      ctx.fillRect(x + dx * s - 9 * s, top + 52 * s, 18 * s, 5 * s);
      ctx.strokeStyle = C.ink;
      ctx.lineWidth = 2.6 * s;
      ctx.beginPath();
      ctx.moveTo(x + dx * s - 12 * s, top + 57 * s);
      ctx.lineTo(x + dx * s + 12 * s, top + 57 * s);
      ctx.stroke();
    }
    // Steam wand.
    ctx.strokeStyle = C.steelDark;
    ctx.lineWidth = 3.4 * s;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(x + w * s - 4 * s, top + 44 * s);
    ctx.quadraticCurveTo(x + w * s + 12 * s, top + 56 * s, x + w * s + 6 * s, top + 70 * s);
    ctx.stroke();
    // Little cup catching a pour.
    ctx.fillStyle = C.cream;
    ctx.beginPath();
    ctx.moveTo(x - 24 * s, y - 6 * s);
    ctx.lineTo(x - 6 * s, y - 6 * s);
    ctx.lineTo(x - 8 * s, y + 3 * s);
    ctx.lineTo(x - 22 * s, y + 3 * s);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#5A3A22';
    ctx.fillRect(x - 22 * s, y - 8 * s, 14 * s, 3 * s);
    // Outline.
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2.4 * s;
    ctx.globalAlpha = 0.7;
    roundRectPath(ctx, x - w * s, top, w * 2 * s, h * s, 7 * s);
    ctx.stroke();
    ctx.globalAlpha = 1;
  }

  function drawCouch(ctx, x, y, s, tint) {
    shadowUnder(ctx, x, y, 132 * s, 0.24);
    const w = 118;
    const seatY = y - 26 * s;
    // Back.
    ctx.fillStyle = tint;
    roundRectPath(ctx, x - w * s, seatY - 62 * s, w * 2 * s, 66 * s, 14 * s);
    ctx.fill();
    // Back cushions.
    for (const dx of [-58, 58]) {
      ctx.fillStyle = shade(tint, 0.12);
      roundRectPath(ctx, x + dx * s - 50 * s, seatY - 54 * s, 100 * s, 50 * s, 12 * s);
      ctx.fill();
      ctx.strokeStyle = 'rgba(20,16,32,0.25)';
      ctx.lineWidth = 2 * s;
      roundRectPath(ctx, x + dx * s - 50 * s, seatY - 54 * s, 100 * s, 50 * s, 12 * s);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(x + dx * s, seatY - 54 * s);
      ctx.lineTo(x + dx * s, seatY - 4 * s);
      ctx.stroke();
    }
    // Seat.
    ctx.fillStyle = shade(tint, -0.08);
    roundRectPath(ctx, x - w * s, seatY, w * 2 * s, 30 * s, 10 * s);
    ctx.fill();
    // Arms.
    for (const dx of [-w + 12, w - 12]) {
      ctx.fillStyle = shade(tint, 0.05);
      roundRectPath(ctx, x + dx * s - 15 * s, seatY - 30 * s, 30 * s, 56 * s, 12 * s);
      ctx.fill();
    }
    // Legs.
    ctx.strokeStyle = '#4A2F1B';
    ctx.lineWidth = 5 * s;
    ctx.lineCap = 'round';
    for (const dx of [-w + 18, w - 18]) {
      ctx.beginPath();
      ctx.moveTo(x + dx * s, y - 2 * s);
      ctx.lineTo(x + dx * s, y + 4 * s);
      ctx.stroke();
    }
    // Ink outline.
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2.6 * s;
    ctx.globalAlpha = 0.6;
    roundRectPath(ctx, x - w * s, seatY - 62 * s, w * 2 * s, 92 * s, 14 * s);
    ctx.stroke();
    ctx.globalAlpha = 1;
    // A cushion, because it is a cosy shop.
    ctx.fillStyle = C.jelly;
    ctx.save();
    ctx.translate(x - 30 * s, seatY - 8 * s);
    ctx.rotate(-0.12);
    roundRectPath(ctx, -22 * s, -20 * s, 44 * s, 38 * s, 8 * s);
    ctx.fill();
    ctx.restore();
  }

  function drawTable(ctx, x, y, s) {
    shadowUnder(ctx, x, y, 62 * s, 0.2);
    // Pedestal.
    ctx.fillStyle = '#6E4A2E';
    ctx.beginPath();
    ctx.moveTo(x - 16 * s, y - 4 * s);
    ctx.lineTo(x - 5 * s, y - 44 * s);
    ctx.lineTo(x + 5 * s, y - 44 * s);
    ctx.lineTo(x + 16 * s, y - 4 * s);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#4A2F1B';
    ctx.beginPath();
    ctx.ellipse(x, y, 20 * s, 6 * s, 0, 0, Math.PI * 2);
    ctx.fill();
    // Top.
    ctx.fillStyle = C.wainscot;
    ctx.beginPath();
    ctx.ellipse(x, y - 46 * s, 46 * s, 15 * s, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#B0824F';
    ctx.beginPath();
    ctx.ellipse(x, y - 43 * s, 46 * s, 14 * s, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2.4 * s;
    ctx.globalAlpha = 0.65;
    ctx.beginPath();
    ctx.ellipse(x, y - 45 * s, 46 * s, 15 * s, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.globalAlpha = 1;
    // A cup and a small vase.
    ctx.fillStyle = C.cream;
    roundRectPath(ctx, x + 12 * s, y - 60 * s, 17 * s, 14 * s, 3 * s);
    ctx.fill();
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 1.8 * s;
    ctx.globalAlpha = 0.6;
    roundRectPath(ctx, x + 12 * s, y - 60 * s, 17 * s, 14 * s, 3 * s);
    ctx.stroke();
    ctx.globalAlpha = 1;
    ctx.fillStyle = C.jelly;
    ctx.beginPath();
    ctx.ellipse(x - 16 * s, y - 52 * s, 9 * s, 7 * s, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#4E8C61';
    ctx.lineWidth = 2.4 * s;
    for (let i = -1; i <= 1; i++) {
      ctx.beginPath();
      ctx.moveTo(x - 16 * s, y - 58 * s);
      ctx.quadraticCurveTo(x - 16 * s + i * 8 * s, y - 68 * s, x - 16 * s + i * 13 * s, y - 74 * s);
      ctx.stroke();
    }
  }

  function drawStool(ctx, x, y, s) {
    shadowUnder(ctx, x, y, 26 * s, 0.18);
    ctx.strokeStyle = '#4A2F1B';
    ctx.lineWidth = 4 * s;
    ctx.lineCap = 'round';
    for (const dx of [-8, 8]) {
      ctx.beginPath();
      ctx.moveTo(x + dx * s, y - 26 * s);
      ctx.lineTo(x + dx * s * 1.3, y);
      ctx.stroke();
    }
    ctx.fillStyle = C.jelly;
    ctx.beginPath();
    ctx.ellipse(x, y - 30 * s, 17 * s, 9 * s, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2 * s;
    ctx.globalAlpha = 0.6;
    ctx.beginPath();
    ctx.ellipse(x, y - 30 * s, 17 * s, 9 * s, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.globalAlpha = 1;
  }

  function drawPlant(ctx, x, y, s) {
    shadowUnder(ctx, x, y, 40 * s, 0.22);
    // Pot.
    ctx.fillStyle = '#B06A45';
    ctx.beginPath();
    ctx.moveTo(x - 26 * s, y - 40 * s);
    ctx.lineTo(x + 26 * s, y - 40 * s);
    ctx.lineTo(x + 18 * s, y);
    ctx.lineTo(x - 18 * s, y);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#96522F';
    roundRectPath(ctx, x - 29 * s, y - 48 * s, 58 * s, 12 * s, 4 * s);
    ctx.fill();
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2.4 * s;
    ctx.globalAlpha = 0.6;
    ctx.beginPath();
    ctx.moveTo(x - 26 * s, y - 40 * s);
    ctx.lineTo(x + 26 * s, y - 40 * s);
    ctx.lineTo(x + 18 * s, y);
    ctx.lineTo(x - 18 * s, y);
    ctx.closePath();
    ctx.stroke();
    ctx.globalAlpha = 1;
    // Fronds.
    ctx.strokeStyle = '#4E8C61';
    ctx.lineWidth = 4.5 * s;
    ctx.lineCap = 'round';
    for (let i = -3; i <= 3; i++) {
      const a = -Math.PI / 2 + i * 0.3;
      ctx.beginPath();
      ctx.moveTo(x, y - 46 * s);
      ctx.quadraticCurveTo(
        x + Math.cos(a) * 26 * s, y - 46 * s + Math.sin(a) * 26 * s,
        x + Math.cos(a) * 46 * s, y - 44 * s + Math.sin(a) * 44 * s
      );
      ctx.stroke();
    }
  }

  // A velvet rope across the shop door. Small, hand-drawn, and it explains
  // why the jell-o cannot leave.
  function drawRope(ctx, x, y, s) {
    for (const dx of [-40, 40]) {
      ctx.fillStyle = C.brass;
      ctx.beginPath();
      ctx.ellipse(x + dx * s, y - 22 * s, 9 * s, 4 * s, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = C.brass;
      roundRectPath(ctx, x + dx * s - 3 * s, y - 46 * s, 6 * s, 26 * s, 2 * s);
      ctx.fill();
      ctx.fillStyle = '#C9973F';
      ctx.beginPath();
      ctx.arc(x + dx * s, y - 48 * s, 6 * s, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.strokeStyle = '#8E2A3E';
    ctx.lineWidth = 6 * s;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(x - 40 * s, y - 44 * s);
    ctx.quadraticCurveTo(x, y - 22 * s, x + 40 * s, y - 44 * s);
    ctx.stroke();
  }

  // An A-frame sign, also closed-shop hand-drawn signage.
  function drawSignBoard(ctx, x, y, s) {
    shadowUnder(ctx, x, y, 46 * s, 0.2);
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(-0.04);
    ctx.fillStyle = '#8A5F3B';
    roundRectPath(ctx, -34 * s, -86 * s, 68 * s, 86 * s, 4 * s);
    ctx.fill();
    ctx.fillStyle = '#33404A';
    ctx.fillRect(-27 * s, -79 * s, 54 * s, 62 * s);
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2.4 * s;
    ctx.globalAlpha = 0.7;
    roundRectPath(ctx, -34 * s, -86 * s, 68 * s, 86 * s, 4 * s);
    ctx.stroke();
    ctx.globalAlpha = 1;
    // handText defaults its own jitter, so no rand is needed here.
    handText(ctx, 'BACK', 0, -66 * s, { size: 15 * s, color: '#F2EDE2' });
    handText(ctx, 'SOON', 0, -48 * s, { size: 15 * s, color: '#F2EDE2' });
    handText(ctx, 'sorry!', 0, -26 * s, { size: 10 * s, color: '#9FB4C4' });
    ctx.restore();
  }

  function shade(hex, amt) {
    const n = parseInt(hex.slice(1), 16);
    let r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    if (amt > 0) { r += (255 - r) * amt; g += (255 - g) * amt; b += (255 - b) * amt; }
    else { r *= 1 + amt; g *= 1 + amt; b *= 1 + amt; }
    return `rgb(${r | 0},${g | 0},${b | 0})`;
  }

  // ================================================================ the jell-o
  // The mascot: a red jelly in a navy and cream cap, drawn from the front, the
  // back and both sides, with the lobes and the gloss kept in.

  const JELLY = { hw: 46, hh: 60 }; // half-width and height in local units

  function jellyPath(ctx, hw, hh) {
    const lobes = 6;
    ctx.beginPath();
    ctx.moveTo(-hw, -hh * 0.04);
    // Scalloped bottom: the moulded edge of a jelly.
    for (let i = 0; i < lobes; i++) {
      const x0 = -hw + 2 * hw * (i / lobes);
      const x1 = -hw + 2 * hw * ((i + 1) / lobes);
      const xm = (x0 + x1) / 2;
      ctx.quadraticCurveTo(xm, -hh * 0.04 + hh * 0.14, x1, -hh * 0.04);
    }
    ctx.bezierCurveTo(hw * 1.03, -hh * 0.32, hw * 0.94, -hh * 0.68, hw * 0.5, -hh * 0.88);
    ctx.bezierCurveTo(hw * 0.32, -hh * 0.98, -hw * 0.32, -hh * 0.98, -hw * 0.5, -hh * 0.88);
    ctx.bezierCurveTo(-hw * 0.94, -hh * 0.68, -hw * 1.03, -hh * 0.32, -hw, -hh * 0.04);
    ctx.closePath();
  }

  // One lobe's shading: a light edge on the left, a dark crease on the right.
  function jellyLobes(ctx, hw, hh, alpha = 1) {
    const lobes = 6;
    ctx.save();
    jellyPath(ctx, hw, hh);
    ctx.clip();
    for (let i = 1; i < lobes; i++) {
      const x = -hw + 2 * hw * (i / lobes);
      // Crease.
      const g = ctx.createLinearGradient(x - 5, 0, x + 9, 0);
      g.addColorStop(0, 'rgba(150,10,10,0)');
      g.addColorStop(0.5, `rgba(150,10,10,${0.28 * alpha})`);
      g.addColorStop(1, 'rgba(150,10,10,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.moveTo(x, -hh * 0.02);
      ctx.quadraticCurveTo(x * 1.02, -hh * 0.5, x * 0.34, -hh * 0.9);
      ctx.lineTo(x + 12, -hh * 0.9);
      ctx.lineTo(x + 12, -hh * 0.02);
      ctx.closePath();
      ctx.fill();
      // Highlight beside it.
      const h = ctx.createLinearGradient(x + 4, 0, x + 16, 0);
      h.addColorStop(0, 'rgba(255,220,215,0)');
      h.addColorStop(0.5, `rgba(255,225,220,${0.30 * alpha})`);
      h.addColorStop(1, 'rgba(255,225,220,0)');
      ctx.fillStyle = h;
      ctx.beginPath();
      ctx.moveTo(x + 12, -hh * 0.02);
      ctx.quadraticCurveTo(x * 1.02, -hh * 0.5, x * 0.34, -hh * 0.9);
      ctx.lineTo(x + 22, -hh * 0.9);
      ctx.lineTo(x + 22, -hh * 0.02);
      ctx.closePath();
      ctx.fill();
    }
    // Inner shadow at the very bottom, where the jelly meets the floor.
    const bot = ctx.createLinearGradient(0, -hh * 0.3, 0, -hh * 0.04);
    bot.addColorStop(0, 'rgba(120,0,0,0)');
    bot.addColorStop(1, `rgba(110,0,0,${0.35 * alpha})`);
    ctx.fillStyle = bot;
    ctx.fillRect(-hw, -hh * 0.3, hw * 2, hh * 0.3);
    ctx.restore();
  }

  // Glossy specular streaks: the thing that makes it read as jelly and not as
  // a red blob.
  function jellyGloss(ctx, hw, hh, alpha = 1) {
    ctx.save();
    jellyPath(ctx, hw, hh);
    ctx.clip();
    ctx.globalAlpha = alpha;
    // Broad top-left sheen.
    const g = ctx.createRadialGradient(-hw * 0.4, -hh * 0.78, 2, -hw * 0.4, -hh * 0.78, hw * 0.9);
    g.addColorStop(0, 'rgba(255,235,230,0.75)');
    g.addColorStop(0.45, 'rgba(255,190,185,0.22)');
    g.addColorStop(1, 'rgba(255,150,145,0)');
    ctx.fillStyle = g;
    ctx.fillRect(-hw, -hh, hw * 2, hh);
    // Two hard highlights.
    ctx.fillStyle = 'rgba(255,255,255,0.72)';
    ctx.beginPath();
    ctx.ellipse(-hw * 0.42, -hh * 0.6, 5, 13, -0.35, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.5)';
    ctx.beginPath();
    ctx.ellipse(hw * 0.5, -hh * 0.52, 4, 10, 0.4, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.34)';
    ctx.beginPath();
    ctx.ellipse(hw * 0.16, -hh * 0.8, 3, 7, -0.2, 0, Math.PI * 2);
    ctx.fill();
    // A soft rim light down the right edge.
    const rim = ctx.createLinearGradient(hw * 0.55, 0, hw, 0);
    rim.addColorStop(0, 'rgba(255,180,175,0)');
    rim.addColorStop(1, 'rgba(255,205,200,0.42)');
    ctx.fillStyle = rim;
    ctx.fillRect(hw * 0.55, -hh, hw * 0.5, hh);
    ctx.restore();
    ctx.globalAlpha = 1;
  }

  // The cap. Perched on top of the dome the way it sits on the mascot: the
  // crown clears the jelly and the brim sweeps across just above the eyes, so
  // the face stays clear underneath it.
  function drawCap(ctx, hw, hh, facing, t) {
    const cw = hw * 0.84;
    const baseY = -hh * 1.02;
    const topY = -hh * 1.62;
    const dir = facing === 'left' ? -1 : facing === 'right' ? 1 : 0;
    const tilt = facing === 'back' ? 0 : dir * 0.05 + Math.sin(t * 0.9) * 0.012;
    ctx.save();
    ctx.translate(hw * 0.05, 0);
    ctx.rotate(tilt);

    // Crown: navy sides, cream front panels.
    ctx.beginPath();
    ctx.moveTo(-cw, baseY);
    ctx.bezierCurveTo(-cw * 1.02, topY + 12, -cw * 0.5, topY - 4, 0, topY - 4);
    ctx.bezierCurveTo(cw * 0.5, topY - 4, cw * 1.02, topY + 12, cw, baseY);
    ctx.closePath();
    const cg = ctx.createLinearGradient(0, topY, 0, baseY);
    cg.addColorStop(0, C.cream);
    cg.addColorStop(1, '#D8CBBC');
    ctx.fillStyle = cg;
    ctx.fill();
    // Navy side panels.
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(-cw, baseY);
    ctx.bezierCurveTo(-cw * 1.02, topY + 12, -cw * 0.5, topY - 4, 0, topY - 4);
    ctx.bezierCurveTo(cw * 0.5, topY - 4, cw * 1.02, topY + 12, cw, baseY);
    ctx.closePath();
    ctx.clip();
    ctx.fillStyle = C.navy;
    ctx.beginPath();
    ctx.moveTo(-cw - 4, baseY + 4);
    ctx.quadraticCurveTo(-cw * 0.86, topY - 6, -cw * 0.34, topY - 6);
    ctx.lineTo(-cw * 0.16, topY - 6);
    ctx.quadraticCurveTo(-cw * 0.56, topY + 8, -cw * 0.5, baseY + 4);
    ctx.closePath();
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(cw + 4, baseY + 4);
    ctx.quadraticCurveTo(cw * 0.86, topY - 6, cw * 0.34, topY - 6);
    ctx.lineTo(cw * 0.16, topY - 6);
    ctx.quadraticCurveTo(cw * 0.56, topY + 8, cw * 0.5, baseY + 4);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
    // Panel seams.
    ctx.strokeStyle = 'rgba(38,46,77,0.5)';
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.moveTo(-cw * 0.34, topY - 4);
    ctx.quadraticCurveTo(-cw * 0.3, topY * 0.5, -cw * 0.24, baseY);
    ctx.moveTo(cw * 0.34, topY - 4);
    ctx.quadraticCurveTo(cw * 0.3, topY * 0.5, cw * 0.24, baseY);
    ctx.stroke();

    // Button on top.
    ctx.fillStyle = C.navy;
    ctx.beginPath();
    ctx.ellipse(0, topY - 5, 6, 4.5, 0, 0, Math.PI * 2);
    ctx.fill();

    if (facing !== 'back') {
      // Brim, sweeping to the facing side.
      // The brim sweeps towards the side the jell-o is looking, further in
      // profile than head-on.
      const bx = dir * 0.26;
      ctx.beginPath();
      ctx.moveTo(-cw * 0.96 + cw * bx, baseY - 2);
      ctx.quadraticCurveTo(cw * 0.5 + cw * bx, baseY + 10, cw * 1.16 + cw * bx, baseY + 2);
      ctx.quadraticCurveTo(cw * 0.6 + cw * bx, baseY + 19, -cw * 0.7 + cw * bx, baseY + 7);
      ctx.closePath();
      const bg = ctx.createLinearGradient(0, baseY, 0, baseY + 18);
      bg.addColorStop(0, C.navyLight);
      bg.addColorStop(1, C.navy);
      ctx.fillStyle = bg;
      ctx.fill();
      ctx.strokeStyle = 'rgba(0,0,0,0.35)';
      ctx.lineWidth = 1.4;
      ctx.stroke();
    }
    // Cap outline.
    ctx.strokeStyle = C.ink;
    ctx.lineWidth = 2;
    ctx.globalAlpha = 0.5;
    ctx.beginPath();
    ctx.moveTo(-cw, baseY);
    ctx.bezierCurveTo(-cw * 1.02, topY + 12, -cw * 0.5, topY - 4, 0, topY - 4);
    ctx.bezierCurveTo(cw * 0.5, topY - 4, cw * 1.02, topY + 12, cw, baseY);
    ctx.stroke();
    ctx.globalAlpha = 1;
    ctx.restore();
  }

  function drawFace(ctx, hw, hh, facing) {
    if (facing === 'back') return;
    // The face sits in the middle of the dome, below the cap brim.
    const eyeY = -hh * 0.50;
    const dir = facing === 'left' ? -1 : facing === 'right' ? 1 : 0;
    ctx.strokeStyle = '#241A2E';
    ctx.lineWidth = 4.6;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    if (dir === 0) {
      for (const dx of [-19, 19]) {
        ctx.beginPath();
        ctx.moveTo(dx - 8, eyeY + 6);
        ctx.lineTo(dx, eyeY - 6);
        ctx.lineTo(dx + 8, eyeY + 6);
        ctx.stroke();
      }
      // Mouth.
      ctx.beginPath();
      ctx.moveTo(-9, -hh * 0.32);
      ctx.lineTo(9, -hh * 0.32);
      ctx.stroke();
    } else {
      // Side view: one eye and a short mouth, both set towards the facing side
      // and kept clear of each other.
      const ex = dir * 13;
      ctx.beginPath();
      ctx.moveTo(ex - 7, eyeY + 6);
      ctx.lineTo(ex, eyeY - 6);
      ctx.lineTo(ex + 7, eyeY + 6);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(dir * 1, -hh * 0.20);
      ctx.lineTo(dir * 14, -hh * 0.20);
      ctx.stroke();
    }
  }

  // The whole mascot, at (x, y) on the floor. `pose` carries the walk cycle.
  function drawJello(ctx, x, y, s, facing, pose, t) {
    const { bob = 0, squash = 0, lean = 0, capLag = 0 } = pose;
    shadowUnder(ctx, x, y + 2 * s, JELLY.hw * 1.5 * s, 0.26);
    ctx.save();
    ctx.translate(x, y + bob * s);
    ctx.rotate(lean * 0.05);
    ctx.scale(s * (1 + squash * 0.16), s * (1 - squash * 0.16));
    // Narrower in profile.
    if (facing === 'left' || facing === 'right') ctx.scale(0.88, 1);

    const hw = JELLY.hw;
    const hh = JELLY.hh;

    // The body.
    const bodyG = ctx.createLinearGradient(-hw, -hh, hw * 0.7, 0);
    bodyG.addColorStop(0, '#FF4A42');
    bodyG.addColorStop(0.42, C.jelly);
    bodyG.addColorStop(1, '#A80F0D');
    jellyPath(ctx, hw, hh);
    ctx.fillStyle = bodyG;
    ctx.fill();
    jellyLobes(ctx, hw, hh);
    jellyGloss(ctx, hw, hh);
    // Hand-drawn edge over the top of the gradient.
    ctx.strokeStyle = 'rgba(120,8,8,0.5)';
    ctx.lineWidth = 1.6;
    jellyPath(ctx, hw, hh);
    ctx.stroke();

    drawFace(ctx, hw, hh, facing);

    // The cap lags behind the body, which is the whole reason a jelly looks
    // like a jelly.
    ctx.save();
    ctx.translate(capLag * 5, 0);
    drawCap(ctx, hw, hh, facing, t);
    ctx.restore();
    ctx.restore();
  }

  // ================================================================= coffee bean
  function drawBean(ctx, x, y, s, t) {
    const wob = Math.sin(t * 2.2 + x) * 0.09;
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(wob);
    ctx.scale(s, s);
    // Body.
    const g = ctx.createRadialGradient(-3, -4, 1, 0, 0, 13);
    g.addColorStop(0, '#8A5A33');
    g.addColorStop(0.55, '#5E3A1E');
    g.addColorStop(1, '#3E2412');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.ellipse(0, 0, 10, 13, 0, 0, Math.PI * 2);
    ctx.fill();
    // The crease.
    ctx.strokeStyle = '#2E1A0C';
    ctx.lineWidth = 2.6;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(0, -11);
    ctx.bezierCurveTo(4, -6, -4, -1, 0, 4);
    ctx.bezierCurveTo(4, 7, -3, 9, -1, 11);
    ctx.stroke();
    // Gloss.
    ctx.fillStyle = 'rgba(255,225,200,0.35)';
    ctx.beginPath();
    ctx.ellipse(-4, -6, 2.6, 4.4, -0.4, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  // ====================================================================== audio
  // Everything is synthesised. No files, so the shop is silent-weight.

  const audio = {
    ctx: null,
    master: null,
    musicGain: null,
    sfxGain: null,
    on: false,
    timer: null,
    nextNote: 0,
    step: 0,
    _noise: null,
  };

  function makeNoiseBuffer(ctx) {
    const len = ctx.sampleRate * 2;
    const buf = ctx.createBuffer(1, len, ctx.sampleRate);
    const d = buf.getChannelData(0);
    let last = 0;
    for (let i = 0; i < len; i++) {
      const white = Math.random() * 2 - 1;
      last = (last + 0.02 * white) / 1.02;
      d[i] = last * 3.2;
    }
    return buf;
  }

  // A small, cheap reverb: a couple of feedback delays. It is what turns a few
  // oscillators into a room.
  function makeRoom(ctx, dest) {
    const input = ctx.createGain();
    const output = ctx.createGain();
    output.gain.value = 0.5;
    const lp = ctx.createBiquadFilter();
    lp.type = 'lowpass';
    lp.frequency.value = 2400;
    for (const [time, fb] of [[0.037, 0.72], [0.053, 0.68], [0.079, 0.6]]) {
      const d = ctx.createDelay(0.5);
      d.delayTime.value = time;
      const g = ctx.createGain();
      g.gain.value = fb;
      input.connect(d);
      d.connect(lp);
      lp.connect(g);
      g.connect(d);
      d.connect(output);
    }
    input.connect(dest);
    return input;
  }

  function initAudio() {
    if (audio.ctx) return;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    const ctx = new AC();
    audio.ctx = ctx;
    audio.master = ctx.createGain();
    audio.master.gain.value = 0.9;
    audio.master.connect(ctx.destination);

    audio.musicGain = ctx.createGain();
    audio.musicGain.gain.value = 0;
    const musicFilter = ctx.createBiquadFilter();
    musicFilter.type = 'lowpass';
    musicFilter.frequency.value = 2600;
    const verb = makeRoom(ctx, audio.master);
    audio.musicGain.connect(musicFilter);
    musicFilter.connect(audio.master);
    musicFilter.connect(verb);

    audio.sfxGain = ctx.createGain();
    audio.sfxGain.gain.value = 0.5;
    audio.sfxGain.connect(audio.master);

    audio._noise = makeNoiseBuffer(ctx);

    // Room tone: filtered noise, very low. The hum of a shop with the door shut.
    const tone = ctx.createBufferSource();
    tone.buffer = audio._noise;
    tone.loop = true;
    const toneFilter = ctx.createBiquadFilter();
    toneFilter.type = 'lowpass';
    toneFilter.frequency.value = 420;
    const toneGain = ctx.createGain();
    toneGain.gain.value = 0.05;
    tone.connect(toneFilter);
    toneFilter.connect(toneGain);
    toneGain.connect(audio.master);
    tone.start();

    // Vinyl crackle on top of the room tone.
    const crackle = ctx.createBufferSource();
    crackle.buffer = audio._noise;
    crackle.loop = true;
    crackle.playbackRate.value = 3.7;
    const crackleFilter = ctx.createBiquadFilter();
    crackleFilter.type = 'highpass';
    crackleFilter.frequency.value = 1800;
    const crackleGain = ctx.createGain();
    crackleGain.gain.value = 0.012;
    crackle.connect(crackleFilter);
    crackleFilter.connect(crackleGain);
    crackleGain.connect(audio.musicGain);
    crackle.start();

    audio.on = true;
    scheduleMusic();
  }

  // A slow, warm progression. Fmaj7 - Dm7 - Bbmaj7 - C7sus, the kind of thing a
  // coffee shop plays at four in the afternoon.
  const PROG = [
    { root: 87.31, chord: [0, 4, 7, 11, 14] },   // Fmaj7
    { root: 73.42, chord: [0, 3, 7, 10, 14] },   // Dm7
    { root: 116.54, chord: [0, 4, 7, 11] },      // Bbmaj7
    { root: 130.81, chord: [0, 5, 7, 10] },      // C7sus
  ];
  const BAR = 4.6;

  function midiToFreq(m) { return 440 * Math.pow(2, (m - 69) / 12); }

  function pad(freq, at, dur, gain) {
    const ctx = audio.ctx;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.exponentialRampToValueAtTime(gain, at + dur * 0.35);
    g.gain.setValueAtTime(gain, at + dur * 0.7);
    g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
    const filt = ctx.createBiquadFilter();
    filt.type = 'lowpass';
    filt.frequency.setValueAtTime(700, at);
    filt.frequency.linearRampToValueAtTime(1500, at + dur * 0.5);
    filt.Q.value = 0.6;
    g.connect(filt);
    filt.connect(audio.musicGain);
    for (const detune of [-6, 0, 7]) {
      const o = ctx.createOscillator();
      o.type = 'triangle';
      o.frequency.value = freq;
      o.detune.value = detune;
      o.connect(g);
      o.start(at);
      o.stop(at + dur + 0.1);
    }
  }

  function bell(freq, at, gain = 0.09, dur = 1.5) {
    const ctx = audio.ctx;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.exponentialRampToValueAtTime(gain, at + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
    g.connect(audio.musicGain);
    for (const [mult, level, type] of [[1, 1, 'sine'], [2.76, 0.28, 'sine'], [5.4, 0.12, 'sine']]) {
      const o = ctx.createOscillator();
      o.type = type;
      o.frequency.value = freq * mult;
      const og = ctx.createGain();
      og.gain.value = level;
      o.connect(og);
      og.connect(g);
      o.start(at);
      o.stop(at + dur + 0.1);
    }
  }

  function subBass(freq, at, dur) {
    const ctx = audio.ctx;
    const o = ctx.createOscillator();
    o.type = 'sine';
    o.frequency.value = freq;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.exponentialRampToValueAtTime(0.075, at + 0.08);
    g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
    o.connect(g);
    g.connect(audio.musicGain);
    o.start(at);
    o.stop(at + dur + 0.1);
  }

  // The clink of a cup, now and then.
  function clink(at) {
    const ctx = audio.ctx;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.exponentialRampToValueAtTime(0.03, at + 0.004);
    g.gain.exponentialRampToValueAtTime(0.0001, at + 0.28);
    g.connect(audio.musicGain);
    for (const f of [2380, 3510, 4700]) {
      const o = ctx.createOscillator();
      o.type = 'sine';
      o.frequency.value = f * (0.98 + Math.random() * 0.04);
      const og = ctx.createGain();
      og.gain.value = 1 / f * 2400;
      o.connect(og);
      og.connect(g);
      o.start(at);
      o.stop(at + 0.4);
    }
  }

  function scheduleMusic() {
    if (!audio.ctx) return;
    audio.timer = setInterval(() => {
      if (!audio.ctx || audio.ctx.state !== 'running') return;
      const horizon = audio.ctx.currentTime + 0.6;
      while (audio.nextNote < horizon) {
        const at = audio.nextNote;
        const bar = audio.step % 4;
        const p = PROG[bar];
        // Pad the whole bar.
        for (const semi of p.chord) {
          pad(midiToFreq(69 + semi) / 2, at, BAR * 1.02, 0.030);
        }
        // Bass on the first and third beat.
        subBass(p.root, at, BAR * 0.5);
        subBass(p.root, at + BAR * 0.5, BAR * 0.42);
        // A sparse melody over the top.
        const notes = 2 + Math.floor(Math.random() * 3);
        for (let i = 0; i < notes; i++) {
          const semi = p.chord[Math.floor(Math.random() * p.chord.length)];
          const at2 = at + (i / notes) * BAR + Math.random() * 0.3;
          bell(midiToFreq(69 + semi + (Math.random() < 0.3 ? 12 : 0)), at2, 0.055, 1.8);
        }
        if (Math.random() < 0.4) clink(at + Math.random() * BAR);
        audio.nextNote += BAR;
        audio.step++;
      }
    }, 120);
  }

  function startAudio() {
    initAudio();
    if (!audio.ctx) return;
    if (audio.ctx.state === 'suspended') audio.ctx.resume();
    // Fade the music in rather than slamming it on.
    const now = audio.ctx.currentTime;
    audio.musicGain.gain.cancelScheduledValues(now);
    audio.musicGain.gain.setValueAtTime(audio.musicGain.gain.value, now);
    audio.musicGain.gain.linearRampToValueAtTime(0.5, now + 2.5);
  }

  // A small, soft step: the jelly's footfall is a wobble, not a thud.
  function stepSound() {
    if (!audio.ctx || audio.ctx.state !== 'running') return;
    const ctx = audio.ctx;
    const at = ctx.currentTime;
    const base = 330 + Math.random() * 70;
    const o = ctx.createOscillator();
    o.type = 'sine';
    o.frequency.setValueAtTime(base * 1.7, at);
    o.frequency.exponentialRampToValueAtTime(base * 0.72, at + 0.1);
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.exponentialRampToValueAtTime(0.075, at + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, at + 0.15);
    o.connect(g);
    g.connect(audio.sfxGain);
    o.start(at);
    o.stop(at + 0.2);

    // A touch of squish.
    const n = ctx.createBufferSource();
    n.buffer = audio._noise;
    n.playbackRate.value = 1.6;
    const nf = ctx.createBiquadFilter();
    nf.type = 'bandpass';
    nf.frequency.value = 900;
    nf.Q.value = 1.4;
    const ng = ctx.createGain();
    ng.gain.setValueAtTime(0.05, at);
    ng.gain.exponentialRampToValueAtTime(0.0001, at + 0.09);
    n.connect(nf); nf.connect(ng); ng.connect(audio.sfxGain);
    n.start(at);
    n.stop(at + 0.12);
  }

  // Picking up a bean: a bright two-note lift.
  function beanSound() {
    if (!audio.ctx || audio.ctx.state !== 'running') return;
    const ctx = audio.ctx;
    const at = ctx.currentTime;
    [midiToFreq(81), midiToFreq(88)].forEach((f, i) => {
      const o = ctx.createOscillator();
      o.type = 'sine';
      o.frequency.value = f;
      const g = ctx.createGain();
      const t0 = at + i * 0.07;
      g.gain.setValueAtTime(0.0001, t0);
      g.gain.exponentialRampToValueAtTime(0.09, t0 + 0.01);
      g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.5);
      o.connect(g);
      g.connect(audio.sfxGain);
      o.start(t0);
      o.stop(t0 + 0.6);
    });
  }

  // ======================================================================= game

  const canvas = document.getElementById('js-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const stage = canvas.closest('.js-stage') || canvas.parentElement;

  const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const game = {
    x: 480,
    y: 462,
    facing: 'front',
    target: null,
    walkPhase: 0,
    stepIndex: 0,
    vx: 0,
    vy: 0,
    lean: 0,
    capLag: 0,
    beans: 0,
    beanList: [],
    motes: [],
    puff: null,
    started: false,
    lastT: 0,
    time: 0,
  };

  const WALK_SPEED = 150;   // design px per second
  const DEPTH_SPEED = 96;   // slower in depth, so depth changes read as turning

  function randomFloorPoint() {
    const y = LANES[Math.floor(Math.random() * LANES.length)];
    const pad = 40;
    const x = Math.random() * (rightX(y) - leftX(y) - pad * 2) + leftX(y) + pad;
    return { x, y };
  }

  function spawnBean(p) {
    const at = p || randomFloorPoint();
    return { x: at.x, y: at.y, bob: Math.random() * 6.28, born: game.time };
  }

  function resetBeans() {
    game.beanList = [];
    for (let i = 0; i < 5; i++) {
      let p = randomFloorPoint();
      // Keep them apart so a single walk does not vacuum up the whole shop.
      let tries = 0;
      while (game.beanList.some((b) => Math.hypot(b.x - p.x, (b.y - p.y) * 1.4) < 130) && tries++ < 12) {
        p = randomFloorPoint();
      }
      game.beanList.push(spawnBean(p));
    }
  }

  function initMotes() {
    game.motes = [];
    for (let i = 0; i < 26; i++) {
      game.motes.push({
        x: Math.random() * DW,
        y: Math.random() * DH,
        r: 0.8 + Math.random() * 1.6,
        vx: (Math.random() - 0.5) * 7,
        vy: -2 - Math.random() * 6,
        a: 0.12 + Math.random() * 0.22,
      });
    }
  }

  // Where the jell-o stops: the clicked point, pulled onto the nearest lane and
  // kept inside the walls of the room.
  function resolveTarget(px, py) {
    let lane = LANES[0];
    let best = Infinity;
    for (const l of LANES) {
      const d = Math.abs(l - py);
      if (d < best) { best = d; lane = l; }
    }
    const pad = 34;
    const x = clamp(px, leftX(lane) + pad, rightX(lane) - pad);
    return { x, y: lane };
  }

  function toDesign(clientX, clientY) {
    const r = canvas.getBoundingClientRect();
    return {
      x: ((clientX - r.left) / r.width) * DW,
      y: ((clientY - r.top) / r.height) * DH,
    };
  }

  // --------------------------------------------------------------- room baking
  let roomCanvas = null;

  function bakeRoom() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const r = canvas.getBoundingClientRect();
    const cssW = Math.max(1, r.width);
    const scale = Math.min(cssW / DW, r.height / DH);
    const cssH = DW * scale === 0 ? 0 : DH * scale;
    const w = Math.round(cssW * dpr);
    const h = Math.round(cssH * dpr);
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    ctx.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);

    roomCanvas = document.createElement('canvas');
    roomCanvas.width = Math.round(DW * scale * dpr);
    roomCanvas.height = Math.round(DH * scale * dpr);
    const rctx = roomCanvas.getContext('2d');
    rctx.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);
    paintRoom(rctx);
  }

  // ------------------------------------------------------------------ the loop
  function update(dt) {
    const g = game;
    g.time += dt;

    // Movement towards the target.
    if (g.target) {
      const dx = g.target.x - g.x;
      const dy = g.target.y - g.y;
      const dist = Math.hypot(dx, dy);
      if (dist < 3) {
        g.target = null;
        g.vx = 0;
        g.vy = 0;
      } else {
        const stepLen = WALK_SPEED * dt;
        const nx = g.x + (dx / dist) * Math.min(stepLen, dist);
        const ny = g.y + (dy / dist) * Math.min(stepLen * (DEPTH_SPEED / WALK_SPEED), Math.abs(dy) + 0.0001);
        g.vx = (nx - g.x) / Math.max(dt, 0.0001);
        g.vy = (ny - g.y) / Math.max(dt, 0.0001);
        g.x = nx;
        g.y = ny;
        // Facing follows the walk, exactly as a Club Penguin penguin does.
        if (Math.abs(dx) > Math.abs(dy) * 0.7) g.facing = dx < 0 ? 'left' : 'right';
        else g.facing = dy < 0 ? 'back' : 'front';
      }
    } else {
      // Standing still, the jell-o turns to look at the cursor.
      if (g.mouse) {
        if (g.mouse.x < g.x - 6) g.facing = 'left';
        else if (g.mouse.x > g.x + 6) g.facing = 'right';
      }
      g.vx *= 0.86;
      g.vy *= 0.86;
    }

    // Walk cycle. A jelly has no legs, so the walk is a bounce with a squash
    // on the way down, and the cap lags a frame behind the body.
    const speed = Math.hypot(g.vx, g.vy);
    const moving = speed > 8;
    if (moving) {
      const prev = g.stepIndex;
      g.walkPhase += (speed / 90) * dt * 3.2;
      g.stepIndex = Math.floor(g.walkPhase / Math.PI);
      if (g.stepIndex !== prev) stepSound();
    } else {
      g.walkPhase += dt * 0.9;
    }

    const p = g.walkPhase * Math.PI * 2;
    const bob = moving ? -Math.abs(Math.sin(p)) * 7 : Math.sin(g.time * 1.6) * 1.6;
    const squash = moving ? Math.cos(p * 2) * 0.5 : Math.sin(g.time * 1.6 + 0.6) * 0.05;
    g.pose = { bob, squash, lean: clamp(g.vx / 260, -1, 1) };
    g.capLag += ((moving ? clamp(g.vx / 200, -1, 1) : 0) - g.capLag) * Math.min(1, dt * 7);

    // Beans.
    // Beans. The lane spacing is wider than the pickup circle, so depth is
    // weighted down: walking past a bean in the next lane back still counts,
    // otherwise a bean on a neighbouring lane could never be collected.
    for (let i = g.beanList.length - 1; i >= 0; i--) {
      const b = g.beanList[i];
      if (Math.hypot(b.x - g.x, (b.y - g.y) * 0.45) < 46) {
        g.beanList.splice(i, 1);
        g.beans++;
        beanSound();
        g.puff = { x: b.x, y: b.y, t: 0 };
        syncCounter();
        setTimeout(() => g.beanList.push(spawnBean()), 260);
      }
    }
    if (g.puff) {
      g.puff.t += dt;
      if (g.puff.t > 0.7) g.puff = null;
    }

    // Dust in the light.
    for (const m of g.motes) {
      m.x += m.vx * dt;
      m.y += m.vy * dt;
      if (m.y < -10) { m.y = DH + 10; m.x = Math.random() * DW; }
      if (m.x < -10) m.x = DW + 10;
      if (m.x > DW + 10) m.x = -10;
    }
  }

  // Everything standing on the floor, sorted back to front.
  function sceneList() {
    const items = [
      { y: 300, kind: 'counter', x: 396 },
      { y: 300, kind: 'machine', x: 396 },
      { y: 330, kind: 'stool', x: 250 },
      { y: 336, kind: 'stool', x: 336 },
      { y: 344, kind: 'stool', x: 452 },
      { y: 300, kind: 'rope', x: 668 },
      { y: 336, kind: 'sign', x: 742 },
      { y: 396, kind: 'plant', x: 172 },
      { y: 430, kind: 'couch', x: 214, tint: C.navy },
      { y: 470, kind: 'table', x: 700 },
      { y: 500, kind: 'couch', x: 792, tint: '#7A4E7A' },
      { y: 528, kind: 'table', x: 372 },
      { y: 556, kind: 'plant', x: 620 },
    ];
    for (const b of game.beanList) items.push({ y: b.y, kind: 'bean', ref: b });
    items.push({ y: game.y, kind: 'jello' });
    items.sort((a, b) => a.y - b.y);
    return items;
  }

  function drawItem(ctx, it) {
    const s = scaleAt(it.y);
    switch (it.kind) {
      case 'counter': drawCounter(ctx, it.x, it.y, s); break;
      case 'machine': drawMachine(ctx, it.x + 34 * s, it.y - 110 * s, s); break;
      case 'stool': drawStool(ctx, it.x, it.y, s); break;
      case 'couch': drawCouch(ctx, it.x, it.y, s, it.tint); break;
      case 'table': drawTable(ctx, it.x, it.y, s); break;
      case 'plant': drawPlant(ctx, it.x, it.y, s); break;
      case 'rope': drawRope(ctx, it.x, it.y, s); break;
      case 'sign': drawSignBoard(ctx, it.x, it.y, s); break;
      case 'bean': drawBean(ctx, it.ref.x, it.ref.y - 6, s * 0.95, game.time + it.ref.bob); break;
      case 'jello': drawJello(ctx, game.x, game.y, s * 1.02, game.facing, game.pose || {}, game.time); break;
    }
  }

  function render() {
    const g = game;
    ctx.clearRect(0, 0, DW, DH);
    if (roomCanvas) ctx.drawImage(roomCanvas, 0, 0, DW, DH);

    // The glow from the pendant lamps, breathing slowly.
    for (let i = 0; i < LAMPS.length; i++) {
      const lamp = LAMPS[i];
      const p = floorPoint(lamp.t + 0.16);
      const x = lerp(p.l, p.r, lamp.u);
      const flick = 0.9 + Math.sin(g.time * 0.7 + i * 2) * 0.05 + Math.sin(g.time * 3.1 + i) * 0.02;
      const gr = ctx.createRadialGradient(x, p.y - 40, 4, x, p.y - 40, 150);
      gr.addColorStop(0, `rgba(255,214,150,${0.16 * flick})`);
      gr.addColorStop(1, 'rgba(255,214,150,0)');
      ctx.fillStyle = gr;
      ctx.beginPath();
      ctx.ellipse(x, p.y - 20, 150, 90, 0, 0, Math.PI * 2);
      ctx.fill();
    }

    // Steam from the machine.
    drawSteam(ctx, 430, 246, g.time);

    for (const it of sceneList()) drawItem(ctx, it);

    // Dust motes.
    for (const m of g.motes) {
      ctx.fillStyle = `rgba(255,240,214,${m.a})`;
      ctx.beginPath();
      ctx.arc(m.x, m.y, m.r, 0, Math.PI * 2);
      ctx.fill();
    }

    // The click marker.
    if (g.marker && g.time - g.marker.t < 0.6) {
      const k = (g.time - g.marker.t) / 0.6;
      ctx.strokeStyle = `rgba(255,255,255,${0.7 * (1 - k)})`;
      ctx.lineWidth = 3;
      ctx.beginPath();
      ctx.ellipse(g.marker.x, g.marker.y, 12 + k * 26, (12 + k * 26) * 0.4, 0, 0, Math.PI * 2);
      ctx.stroke();
    }

    // The bean pickup puff.
    if (g.puff) {
      const k = g.puff.t / 0.7;
      for (let i = 0; i < 8; i++) {
        const a = (i / 8) * Math.PI * 2;
        const d = 12 + k * 40;
        ctx.fillStyle = `rgba(255,224,170,${0.8 * (1 - k)})`;
        ctx.beginPath();
        ctx.arc(g.puff.x + Math.cos(a) * d, g.puff.y - 10 + Math.sin(a) * d * 0.5 - k * 22, 3.4 * (1 - k * 0.5), 0, Math.PI * 2);
        ctx.fill();
      }
    }
  }

  function drawSteam(ctx, x, y, t) {
    ctx.save();
    for (let i = 0; i < 3; i++) {
      const p = (t * 0.35 + i * 0.33) % 1;
      const yy = y - p * 66;
      const a = Math.sin(p * Math.PI) * 0.3;
      const xx = x + Math.sin(t * 1.3 + i * 2) * 9 * p;
      ctx.fillStyle = `rgba(255,255,255,${a})`;
      ctx.beginPath();
      ctx.ellipse(xx, yy, 8 + p * 14, 10 + p * 16, 0, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.restore();
  }

  // Walk-here marker plus the click-to-move behaviour.
  function onDown(ev) {
    const p = toDesign(ev.clientX, ev.clientY);
    if (p.y < FLOOR_Y - 40) p.y = FLOOR_Y - 40;
    const t = resolveTarget(p.x, p.y);
    game.target = t;
    game.marker = { x: t.x, y: t.y, t: game.time };
    if (!game.started) begin();
  }

  function onMove(ev) {
    const p = toDesign(ev.clientX, ev.clientY);
    game.mouse = p;
  }

  function begin() {
    if (game.started) return;
    game.started = true;
    stage.classList.add('is-playing');
    startAudio();
    setSoundLabel(true);
    setTimeout(() => { game.target = { x: 620, y: 462 }; game.marker = { x: 620, y: 462, t: game.time }; }, 320);
  }

  const startBtn = document.getElementById('js-start');
  if (startBtn) startBtn.addEventListener('click', begin);

  const counterEl = document.getElementById('js-beans');
  function syncCounter() {
    if (counterEl) counterEl.textContent = String(game.beans);
  }
  const liveEl = document.getElementById('js-beans-live');
  function syncLive() {
    if (liveEl) liveEl.textContent = `${game.beans} coffee bean${game.beans === 1 ? '' : 's'}`;
  }
  // Keep the polite live region in step without announcing every pickup twice.
  let lastBeans = -1;  function trackBeans() {
    if (game.beans !== lastBeans) {
      lastBeans = game.beans;
      syncLive();
    }
  }

  // ------------------------------------------------------------------- wiring
  canvas.addEventListener('pointerdown', onDown);
  canvas.addEventListener('pointermove', onMove);
  canvas.addEventListener('pointerleave', () => { game.mouse = null; });
  canvas.addEventListener('contextmenu', (e) => e.preventDefault());

  // Keyboard: the shop should be walkable without a mouse.
  window.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    if (e.target && /^(INPUT|TEXTAREA)$/.test(e.target.tagName)) return;
    e.preventDefault();
    if (!game.started) begin();
    const stepX = 90;
    const stepY = 34;
    let tx = game.target ? game.target.x : game.x;
    let ty = game.target ? game.target.y : game.y;
    if (e.key === 'ArrowLeft') tx -= stepX;
    if (e.key === 'ArrowRight') tx += stepX;
    if (e.key === 'ArrowUp') ty -= stepY;
    if (e.key === 'ArrowDown') ty += stepY;
    const lane = LANES.reduce((a, l) => (Math.abs(l - ty) < Math.abs(a - ty) ? l : a), LANES[0]);
    game.target = resolveTarget(tx, lane);
    game.marker = { x: game.target.x, y: game.target.y, t: game.time };
  });

  const soundBtn = document.getElementById('js-sound');
  if (soundBtn) {
    soundBtn.addEventListener('click', () => {
      if (!audio.ctx) { startAudio(); setSoundLabel(true); return; }
      if (audio.on) {
        audio.on = false;
        const now = audio.ctx.currentTime;
        audio.musicGain.gain.cancelScheduledValues(now);
        audio.musicGain.gain.setValueAtTime(audio.musicGain.gain.value, now);
        audio.musicGain.gain.linearRampToValueAtTime(0, now + 0.4);
        audio.sfxGain.gain.setValueAtTime(0, now);
      } else {
        audio.on = true;
        const now = audio.ctx.currentTime;
        audio.musicGain.gain.cancelScheduledValues(now);
        audio.musicGain.gain.setValueAtTime(audio.musicGain.gain.value, now);
        audio.musicGain.gain.linearRampToValueAtTime(0.5, now + 1.2);
        audio.sfxGain.gain.setValueAtTime(0.5, now);
        if (audio.ctx.state === 'suspended') audio.ctx.resume();
      }
      setSoundLabel(audio.on);
    });
  }
  function setSoundLabel(on) {
    if (!soundBtn) return;
    soundBtn.setAttribute('aria-pressed', String(on));
    soundBtn.querySelector('.js-sound-label').textContent = on ? 'Sound on' : 'Sound off';
  }

  // ------------------------------------------------------------------- resize
  let resizeTimer = null;
  function onResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(bakeRoom, 120);
  }
  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);

  // Pause when the tab is hidden: no reason to burn battery on a shop nobody
  // is looking at.
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
    update(dt);
    trackBeans();
    render();
  }

  // A small read-only view of the game state, for tests and for anyone who
  // wants to poke at the shop from the console. Read-only on purpose: the
  // object it returns is a copy, so it cannot be used to drive the jell-o.
  window.jelloShop = {
    state: () => ({
      x: Math.round(game.x),
      y: Math.round(game.y),
      facing: game.facing,
      beans: game.beans,
      onFloor: game.beanList.length,
      walking: !!game.target,
      audio: audio.ctx ? audio.ctx.state : 'off',
      sound: audio.on,
    }),
  };

  // --------------------------------------------------------------------- start
  resetBeans();
  initMotes();
  game.pose = { bob: 0, squash: 0, lean: 0, capLag: 0 };
  syncCounter();

  const boot = () => {
    bakeRoom();
    game.lastT = performance.now();
    raf = requestAnimationFrame(frame);
  };

  if (document.fonts && document.fonts.ready) {
    // The hand-written menu and signs use the site's display face, so wait for
    // it before baking the room, or the text would be baked in a fallback.
    document.fonts.ready.then(boot).catch(boot);
  } else {
    boot();
  }
})();
