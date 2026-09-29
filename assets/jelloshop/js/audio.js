/* =============================================================================
   The shop's sound. Nothing is downloaded: every note is synthesised here.

   The music is a small band playing an eight-bar loop at 78 bpm with a lazy
   swing: an electric piano comping 9th chords, an upright bass walking under
   it, a vibraphone carrying a written melody, and brushed drums. It changes a
   little every time round (the melody thins out, an arpeggio joins) so a long
   visit does not become a short loop. Under it: rain on the window, the fire,
   and vinyl crackle.

   Progression (D dorian, turning around a I - vi - ii - V):
     Dm9  | G13  | Cmaj9 | Am9  | Fmaj9 | Em7  | Dm9  | G7sus
   ========================================================================== */

const midi = (m) => 440 * Math.pow(2, (m - 69) / 12);

const BPM = 78;
const STEP = 60 / BPM / 4;           // one sixteenth
const BAR_STEPS = 16;
const BARS = 8;

// Rootless-ish voicings, in MIDI note numbers.
const CHORDS = [
  [53, 57, 60, 64],   // Dm9
  [53, 59, 62, 64],   // G13
  [52, 55, 59, 62],   // Cmaj9
  [55, 60, 64, 67],   // Am9
  [57, 60, 64, 67],   // Fmaj9
  [55, 59, 62, 66],   // Em7 (with the 9th on top)
  [53, 57, 60, 64],   // Dm9
  [53, 57, 60, 62],   // G7sus
];
const BASS_ROOT = [38, 43, 36, 33, 41, 40, 38, 43];
const BASS_NEXT = [43, 36, 33, 41, 40, 38, 43, 38];
const BASS_FIFTH = [45, 50, 43, 40, 48, 47, 45, 50];

// The written melody: [step in bar, midi, length in steps].
const MELODY = [
  [[0, 76, 3], [4, 74, 2], [8, 72, 4], [13, 74, 2]],
  [[0, 71, 3], [4, 72, 2], [6, 74, 6], [13, 71, 3]],
  [[0, 76, 6], [8, 79, 3], [12, 76, 4]],
  [[0, 72, 4], [6, 71, 2], [8, 69, 8]],
  [[2, 72, 3], [6, 76, 3], [10, 74, 3], [14, 72, 2]],
  [[0, 71, 4], [6, 67, 2], [8, 71, 3], [12, 74, 4]],
  [[0, 72, 3], [4, 69, 3], [8, 74, 5], [14, 72, 2]],
  [[0, 72, 4], [6, 71, 2], [8, 67, 8]],
];

// Comping rhythm: [step, length in steps, which chord tones, velocity].
const COMP = [
  [0, 6, 'all', 0.95],
  [7, 2, 'top', 0.55],
  [10, 4, 'all', 0.72],
];

export function createAudio() {
  const A = {
    ctx: null,
    on: false,
    started: false,
    timer: null,
    nextTime: 0,
    pos: 0,
    pass: 0,
    beans: 0,
  };

  let master, musicBus, sfxBus, keysBus, reverbIn, noiseBuf;
  let rainGain = null;
  let rainBand = null;

  function makeNoise(ctx) {
    const len = ctx.sampleRate * 2;
    const buf = ctx.createBuffer(1, len, ctx.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    return buf;
  }

  // A small hall: decaying noise, darkened, as an impulse response.
  function makeImpulse(ctx, seconds, decay) {
    const len = Math.floor(ctx.sampleRate * seconds);
    const buf = ctx.createBuffer(2, len, ctx.sampleRate);
    for (let ch = 0; ch < 2; ch++) {
      const d = buf.getChannelData(ch);
      let lp = 0;
      for (let i = 0; i < len; i++) {
        const t = i / len;
        lp += (Math.random() * 2 - 1 - lp) * 0.32;
        d[i] = lp * Math.pow(1 - t, decay) * (i < 400 ? i / 400 : 1);
      }
    }
    return buf;
  }

  function init() {
    if (A.ctx) return true;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return false;
    const ctx = new AC();
    A.ctx = ctx;
    noiseBuf = makeNoise(ctx);

    // master: a touch of glue compression, then a soft lowpass so it sounds like a tape.
    const comp = ctx.createDynamicsCompressor();
    comp.threshold.value = -20;
    comp.knee.value = 24;
    comp.ratio.value = 3;
    comp.attack.value = 0.02;
    comp.release.value = 0.3;
    const tape = ctx.createBiquadFilter();
    tape.type = 'lowpass';
    tape.frequency.value = 7800;
    tape.Q.value = 0.4;
    master = ctx.createGain();
    master.gain.value = 0.9;
    master.connect(comp);
    comp.connect(tape);
    tape.connect(ctx.destination);

    musicBus = ctx.createGain();
    musicBus.gain.value = 0;
    musicBus.connect(master);

    sfxBus = ctx.createGain();
    sfxBus.gain.value = 0.55;
    sfxBus.connect(master);

    const verb = ctx.createConvolver();
    verb.buffer = makeImpulse(ctx, 2.4, 2.6);
    const verbOut = ctx.createGain();
    verbOut.gain.value = 0.42;
    reverbIn = ctx.createGain();
    reverbIn.gain.value = 1;
    reverbIn.connect(verb);
    verb.connect(verbOut);
    verbOut.connect(musicBus);

    // Electric piano bus, with the slow tremolo of a suitcase amp.
    keysBus = ctx.createGain();
    keysBus.gain.value = 0.9;
    const trem = ctx.createGain();
    trem.gain.value = 0.9;
    const lfo = ctx.createOscillator();
    lfo.frequency.value = 4.6;
    const lfoDepth = ctx.createGain();
    lfoDepth.gain.value = 0.11;
    lfo.connect(lfoDepth);
    lfoDepth.connect(trem.gain);
    lfo.start();
    keysBus.connect(trem);
    trem.connect(musicBus);
    const keysSend = ctx.createGain();
    keysSend.gain.value = 0.5;
    trem.connect(keysSend);
    keysSend.connect(reverbIn);

    ambience(ctx);
    return true;
  }

  // Rain on the window, the fire, vinyl crackle: the room, under the band.
  function ambience(ctx) {
    const loop = (rate) => {
      const s = ctx.createBufferSource();
      s.buffer = noiseBuf;
      s.loop = true;
      s.playbackRate.value = rate;
      s.start();
      return s;
    };
    // Rain: band-passed noise, slowly breathing.
    const rain = loop(1);
    rainBand = ctx.createBiquadFilter();
    rainBand.type = 'bandpass';
    rainBand.frequency.value = 2600;
    rainBand.Q.value = 0.35;
    const rainHi = ctx.createBiquadFilter();
    rainHi.type = 'highpass';
    rainHi.frequency.value = 900;
    rainGain = ctx.createGain();
    rainGain.gain.value = 0.05;
    const breathe = ctx.createOscillator();
    breathe.frequency.value = 0.09;
    const breatheAmt = ctx.createGain();
    breatheAmt.gain.value = 0.012;
    breathe.connect(breatheAmt);
    breatheAmt.connect(rainGain.gain);
    breathe.start();
    rain.connect(rainBand);
    rainBand.connect(rainHi);
    rainHi.connect(rainGain);
    rainGain.connect(musicBus);

    // Low room tone.
    const tone = loop(0.4);
    const toneLp = ctx.createBiquadFilter();
    toneLp.type = 'lowpass';
    toneLp.frequency.value = 260;
    const toneGain = ctx.createGain();
    toneGain.gain.value = 0.09;
    tone.connect(toneLp);
    toneLp.connect(toneGain);
    toneGain.connect(musicBus);

    // Vinyl surface noise.
    const vinyl = loop(3.3);
    const vHi = ctx.createBiquadFilter();
    vHi.type = 'highpass';
    vHi.frequency.value = 3400;
    const vGain = ctx.createGain();
    vGain.gain.value = 0.011;
    vinyl.connect(vHi);
    vHi.connect(vGain);
    vGain.connect(musicBus);
  }

  // ------------------------------------------------------------ instruments
  const humanise = (t) => t + (Math.random() - 0.5) * 0.014;
  const vel = (v) => v * (0.9 + Math.random() * 0.2);

  // Rhodes-ish: a sine with a bell-like tine that fades quickly.
  function epiano(freq, at, dur, v) {
    const ctx = A.ctx;
    const out = ctx.createGain();
    const peak = 0.055 * v;
    out.gain.setValueAtTime(0.0001, at);
    out.gain.linearRampToValueAtTime(peak, at + 0.006);
    out.gain.setTargetAtTime(peak * 0.28, at + 0.02, 0.28);
    out.gain.setTargetAtTime(0.0001, at + dur, 0.12);
    const lp = ctx.createBiquadFilter();
    lp.type = 'lowpass';
    lp.frequency.value = 2400 + v * 800;
    out.connect(lp);
    lp.connect(keysBus);

    const car = ctx.createOscillator();
    car.type = 'sine';
    car.frequency.value = freq;
    const mod = ctx.createOscillator();
    mod.type = 'sine';
    mod.frequency.value = freq * 1.0;
    const modGain = ctx.createGain();
    modGain.gain.setValueAtTime(freq * 1.6 * v, at);
    modGain.gain.setTargetAtTime(freq * 0.12, at, 0.22);
    mod.connect(modGain);
    modGain.connect(car.frequency);
    car.connect(out);

    // The tine.
    const tine = ctx.createOscillator();
    tine.type = 'sine';
    tine.frequency.value = freq * 14.0;
    const tineGain = ctx.createGain();
    tineGain.gain.setValueAtTime(0.06 * v, at);
    tineGain.gain.setTargetAtTime(0.0001, at, 0.03);
    tine.connect(tineGain);
    tineGain.connect(out);

    const stop = at + dur + 1.2;
    for (const o of [car, mod, tine]) { o.start(at); o.stop(stop); }
  }

  // Upright bass: a round pluck.
  function bass(freq, at, dur, v) {
    const ctx = A.ctx;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(0.16 * v, at + 0.012);
    g.gain.setTargetAtTime(0.0001, at + dur * 0.55, 0.11);
    const lp = ctx.createBiquadFilter();
    lp.type = 'lowpass';
    lp.frequency.setValueAtTime(700, at);
    lp.frequency.exponentialRampToValueAtTime(240, at + 0.25);
    g.connect(lp);
    lp.connect(musicBus);
    for (const [type, mult, level] of [['triangle', 1, 1], ['sine', 2, 0.3]]) {
      const o = ctx.createOscillator();
      o.type = type;
      o.frequency.value = freq * mult;
      const og = ctx.createGain();
      og.gain.value = level;
      o.connect(og);
      og.connect(g);
      o.start(at);
      o.stop(at + dur + 0.4);
    }
  }

  // Vibraphone / music box: a bright partial that dies quickly on a sine that rings.
  function vibes(freq, at, dur, v) {
    const ctx = A.ctx;
    const g = ctx.createGain();
    const peak = 0.075 * v;
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(peak, at + 0.004);
    g.gain.setTargetAtTime(0.0001, at + 0.02, Math.min(0.5, 0.15 + dur * 0.09));
    g.connect(musicBus);
    const send = ctx.createGain();
    send.gain.value = 0.55;
    g.connect(send);
    send.connect(reverbIn);
    for (const [mult, level, decay] of [[1, 1, 1], [4, 0.28, 0.25], [10.1, 0.07, 0.08]]) {
      const o = ctx.createOscillator();
      o.type = 'sine';
      o.frequency.value = freq * mult;
      const og = ctx.createGain();
      og.gain.setValueAtTime(level, at);
      og.gain.setTargetAtTime(level * 0.02, at, 0.05 + decay * 0.35);
      o.connect(og);
      og.connect(g);
      o.start(at);
      o.stop(at + 2.6);
    }
  }

  function noiseHit(at, len, type, freq, q, gainPeak, dest = musicBus) {
    const ctx = A.ctx;
    const n = ctx.createBufferSource();
    n.buffer = noiseBuf;
    n.playbackRate.value = 0.9 + Math.random() * 0.3;
    const f = ctx.createBiquadFilter();
    f.type = type;
    f.frequency.value = freq;
    f.Q.value = q;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(gainPeak, at + 0.003);
    g.gain.setTargetAtTime(0.0001, at + 0.004, len / 3);
    n.connect(f);
    f.connect(g);
    g.connect(dest);
    n.start(at, Math.random() * 1.5);
    n.stop(at + len * 2.5);
  }

  function kick(at, v) {
    const ctx = A.ctx;
    const o = ctx.createOscillator();
    o.type = 'sine';
    o.frequency.setValueAtTime(128, at);
    o.frequency.exponentialRampToValueAtTime(44, at + 0.13);
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(0.3 * v, at + 0.004);
    g.gain.setTargetAtTime(0.0001, at + 0.02, 0.06);
    o.connect(g);
    g.connect(musicBus);
    o.start(at);
    o.stop(at + 0.4);
  }

  // A brushed snare: hushed noise plus a soft body.
  function snare(at, v) {
    noiseHit(at, 0.18, 'bandpass', 2100, 0.7, 0.085 * v);
    noiseHit(at, 0.06, 'highpass', 5200, 0.5, 0.04 * v);
    const ctx = A.ctx;
    const o = ctx.createOscillator();
    o.type = 'triangle';
    o.frequency.setValueAtTime(210, at);
    o.frequency.exponentialRampToValueAtTime(140, at + 0.09);
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(0.07 * v, at + 0.004);
    g.gain.setTargetAtTime(0.0001, at + 0.01, 0.045);
    o.connect(g);
    g.connect(musicBus);
    o.start(at);
    o.stop(at + 0.3);
  }

  const hat = (at, v) => noiseHit(at, 0.05, 'highpass', 7400, 0.4, 0.045 * v);

  function crackle(at) {
    noiseHit(at, 0.012, 'bandpass', 1400 + Math.random() * 2200, 2, 0.05 + Math.random() * 0.05);
  }

  // -------------------------------------------------------------- sequencer
  function scheduleStep(idx, time) {
    const bar = Math.floor(idx / BAR_STEPS) % BARS;
    const s = idx % BAR_STEPS;
    const pass = A.pass;
    // Lazy swing: the off-beat eighths and sixteenths sit a little late.
    const swing = s % 4 === 2 ? STEP * 0.55 : s % 2 === 1 ? STEP * 0.16 : 0;
    const t = time + swing;
    const chord = CHORDS[bar];

    // Electric piano comping.
    for (const [cs, len, which, v] of COMP) {
      if (s !== cs) continue;
      const notes = which === 'top' ? chord.slice(-2) : chord;
      notes.forEach((m, i) => epiano(midi(m), humanise(t) + i * 0.011, len * STEP, vel(v) * (which === 'all' ? 0.92 : 1)));
    }

    // Bass: root, fifth, root, then walking into the next bar.
    if (s === 0) bass(midi(BASS_ROOT[bar]), humanise(t), STEP * 5, vel(1));
    if (s === 6) bass(midi(BASS_FIFTH[bar]), humanise(t), STEP * 3, vel(0.7));
    if (s === 10) bass(midi(BASS_ROOT[bar] + (pass % 2 ? 12 : 0)), humanise(t), STEP * 3, vel(0.78));
    if (s === 14) {
      const next = BASS_NEXT[bar];
      const lead = next + (next > BASS_ROOT[bar] ? -1 : 1);   // chromatic approach
      bass(midi(lead), humanise(t), STEP * 2, vel(0.66));
    }

    // Drums. The intro of every fourth pass keeps the kick out for the first two bars.
    const drumsIn = !(pass % 4 === 2 && bar < 2);
    if (drumsIn) {
      if (s === 0 || (s === 10 && bar % 2 === 0) || (s === 7 && bar % 4 === 3)) kick(humanise(t), vel(s === 0 ? 1 : 0.65));
      if (s === 4 || s === 12) snare(humanise(t), vel(s === 4 ? 0.7 : 0.9));
      if (s % 2 === 0) hat(humanise(t), vel(s % 4 === 0 ? 0.9 : 0.55));
      else if (Math.random() < 0.22) hat(humanise(t), vel(0.3));
    }

    // Melody.
    const melodyOn = !(pass % 4 === 2 && bar < 4);
    if (melodyOn) {
      for (const [ms, m, len] of MELODY[bar]) {
        if (ms !== s) continue;
        const up = pass % 4 === 1 && (bar % 2 === 1) ? 12 : 0;
        // On the odd passes, long notes get a grace note from below.
        if (pass % 2 === 1 && len >= 4) vibes(midi(m + up - 2), humanise(t) - STEP * 0.7, STEP, vel(0.42));
        vibes(midi(m + up), humanise(t), len * STEP, vel(0.95));
      }
    }

    // Every fourth pass, a quiet arpeggio.
    if (pass % 4 === 3 && s % 2 === 0) {
      const tone = chord[(s / 2) % chord.length] + 12;
      vibes(midi(tone), t, STEP * 2, vel(0.32));
    }

    // Fire and vinyl pops, a little random, in the gaps.
    if (Math.random() < 0.05) crackle(t + Math.random() * STEP);
  }

  function tick() {
    if (!A.ctx || A.ctx.state !== 'running') return;
    const horizon = A.ctx.currentTime + 0.35;
    if (A.nextTime < A.ctx.currentTime) A.nextTime = A.ctx.currentTime + 0.05;
    while (A.nextTime < horizon) {
      scheduleStep(A.pos, A.nextTime);
      A.nextTime += STEP;
      A.pos++;
      if (A.pos % (BAR_STEPS * BARS) === 0) A.pass++;
    }
  }

  function fade(target, secs) {
    const now = A.ctx.currentTime;
    musicBus.gain.cancelScheduledValues(now);
    musicBus.gain.setValueAtTime(musicBus.gain.value, now);
    musicBus.gain.linearRampToValueAtTime(target, now + secs);
  }

  A.start = function start() {
    if (!init()) return false;
    if (A.ctx.state === 'suspended') A.ctx.resume();
    if (!A.started) {
      A.started = true;
      A.nextTime = A.ctx.currentTime + 0.2;
      A.timer = setInterval(tick, 40);
    }
    A.on = true;
    sfxBus.gain.setValueAtTime(0.55, A.ctx.currentTime);
    fade(0.9, 2.2);
    return true;
  };

  A.mute = function mute() {
    if (!A.ctx) return;
    A.on = false;
    fade(0, 0.35);
    sfxBus.gain.setValueAtTime(0, A.ctx.currentTime);
  };

  A.unmute = function unmute() {
    if (!A.ctx) return A.start();
    A.on = true;
    if (A.ctx.state === 'suspended') A.ctx.resume();
    fade(0.9, 1);
    sfxBus.gain.setValueAtTime(0.55, A.ctx.currentTime);
    return true;
  };

  // Browsers keep audio suspended until a real gesture; every tap on the shop tries again.
  A.wake = function wake() {
    if (A.ctx && A.on && A.ctx.state === 'suspended') A.ctx.resume();
  };

  const live = () => A.ctx && A.ctx.state === 'running' && A.on;

  // The jelly's footfall: a wet little plop.
  A.step = function stepSound() {
    if (!live()) return;
    const ctx = A.ctx;
    const at = ctx.currentTime;
    const base = 300 + Math.random() * 80;
    const o = ctx.createOscillator();
    o.type = 'sine';
    o.frequency.setValueAtTime(base * 1.9, at);
    o.frequency.exponentialRampToValueAtTime(base * 0.6, at + 0.11);
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(0.09, at + 0.01);
    g.gain.setTargetAtTime(0.0001, at + 0.02, 0.035);
    o.connect(g);
    g.connect(sfxBus);
    o.start(at);
    o.stop(at + 0.25);
    noiseHit(at, 0.05, 'bandpass', 700, 1.6, 0.04, sfxBus);
  };

  // Picking up a bean: a bright pentatonic chime that climbs as you collect.
  const PENTA = [0, 2, 4, 7, 9, 12, 14, 16];
  A.bean = function beanSound() {
    A.beans++;
    if (!live()) return;
    const ctx = A.ctx;
    const at = ctx.currentTime;
    const base = 79 + PENTA[A.beans % PENTA.length];
    [base, base + 7].forEach((m, i) => {
      const t0 = at + i * 0.075;
      const f = midi(m);
      const g = ctx.createGain();
      g.gain.setValueAtTime(0.0001, t0);
      g.gain.linearRampToValueAtTime(0.1, t0 + 0.006);
      g.gain.setTargetAtTime(0.0001, t0 + 0.02, 0.13);
      g.connect(sfxBus);
      const send = ctx.createGain();
      send.gain.value = 0.5;
      g.connect(send);
      send.connect(reverbIn);
      for (const [mult, level] of [[1, 1], [2.76, 0.25], [5.4, 0.08]]) {
        const o = ctx.createOscillator();
        o.type = 'sine';
        o.frequency.value = f * mult;
        const og = ctx.createGain();
        og.gain.value = level;
        o.connect(og);
        og.connect(g);
        o.start(t0);
        o.stop(t0 + 1.2);
      }
    });
  };

  // A small meow: a sawtooth through a moving formant. `pitch` and `length`
  // vary it so the cat never says it the same way twice; `vol` is 0..1.
  A.meow = function meow(pitch = 1, length = 1, vol = 1) {
    if (!live()) return;
    const ctx = A.ctx;
    const at = ctx.currentTime;
    const L = length;
    const o = ctx.createOscillator();
    o.type = 'sawtooth';
    o.frequency.setValueAtTime(420 * pitch, at);
    o.frequency.linearRampToValueAtTime(610 * pitch, at + 0.14 * L);
    o.frequency.linearRampToValueAtTime(360 * pitch, at + 0.5 * L);
    const f = ctx.createBiquadFilter();
    f.type = 'bandpass';
    f.Q.value = 3.2;
    f.frequency.setValueAtTime(900, at);
    f.frequency.linearRampToValueAtTime(1900, at + 0.16 * L);
    f.frequency.linearRampToValueAtTime(800, at + 0.5 * L);
    const g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(0.13 * vol, at + 0.05);
    g.gain.setTargetAtTime(0.0001, at + 0.3 * L, 0.08);
    o.connect(f);
    f.connect(g);
    g.connect(sfxBus);
    o.start(at);
    o.stop(at + 0.7 * L + 0.1);
  };

  // How hard it is raining, 0..1. The rain fills out and brightens as it rises.
  A.setRain = function setRain(level) {
    if (!A.ctx || !rainGain) return;
    const now = A.ctx.currentTime;
    rainGain.gain.setTargetAtTime(0.05 + level * 0.115, now, 1.4);
    rainBand.frequency.setTargetAtTime(2600 + level * 900, now, 1.4);
  };

  // Thunder. `far` is 0 for right overhead (a sharp crack, then a big roll) to
  // 1 for a long way off (no crack, a low slow rumble).
  A.thunder = function thunder(far = 0.5) {
    if (!live()) return;
    const ctx = A.ctx;
    const at = ctx.currentTime + 0.02;
    const near = 1 - far;
    if (near > 0.3) {
      noiseHit(at, 0.14, 'highpass', 1600, 0.6, 0.2 * near, sfxBus);
      noiseHit(at + 0.03, 0.3, 'bandpass', 500, 0.8, 0.14 * near, sfxBus);
    }
    const dur = 2.6 + far * 2.4 + Math.random() * 0.8;
    const src = ctx.createBufferSource();
    src.buffer = noiseBuf;
    src.loop = true;
    src.playbackRate.value = 0.3 + Math.random() * 0.15;
    const lp = ctx.createBiquadFilter();
    lp.type = 'lowpass';
    lp.frequency.setValueAtTime(340 - far * 150, at);
    lp.frequency.exponentialRampToValueAtTime(60, at + dur);
    const g = ctx.createGain();
    const peak = 0.34 * (0.35 + near * 0.65);
    g.gain.setValueAtTime(0.0001, at);
    g.gain.linearRampToValueAtTime(peak, at + 0.1 + far * 0.4);
    // It rolls: a few swells as it dies away.
    for (let k = 1; k <= 4; k++) {
      const tt = at + (dur * k) / 5;
      g.gain.linearRampToValueAtTime(peak * (0.85 - k * 0.17) * (0.7 + Math.random() * 0.5), tt);
    }
    g.gain.linearRampToValueAtTime(0.0001, at + dur);
    const send = ctx.createGain();
    send.gain.value = 0.35;
    src.connect(lp);
    lp.connect(g);
    g.connect(sfxBus);
    g.connect(send);
    send.connect(reverbIn);
    src.start(at, Math.random() * 1.5);
    src.stop(at + dur + 0.1);
  };

  // The coffee machine letting off steam.
  A.hiss = function hiss(len = 0.7) {
    if (!live()) return;
    const at = A.ctx.currentTime;
    noiseHit(at, len, 'highpass', 4200, 0.6, 0.045, sfxBus);
    noiseHit(at + 0.02, len * 0.8, 'bandpass', 7000, 1.2, 0.03, sfxBus);
  };

  return A;
}
