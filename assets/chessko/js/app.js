// chessko UI: renders the jelly board, talks to the engine (in a worker when possible)
// and surfaces what the learned components are actually doing.

import * as ChessModule from "./chess.js";
import { MlRig, BANDIT_ARMS } from "./ml.js";
import { API, ASSETS } from "./config.js";
import { WasmBrain } from "./wasm.js";
import { Search, makeRng } from "./search.js";
import { extractFeatures } from "./eval.js";
import { pieceElement, pieceFlavour, PROMOTION_TYPES } from "./pieces.js";

const Chess = ChessModule.Chess || ChessModule.default;
const START_FEN =
  ChessModule.START_FEN ||
  "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
const FILES = ["a", "b", "c", "d", "e", "f", "g", "h"];

const $ = (id) => document.getElementById(id);

const state = {
  chess: new Chess(),
  level: 3,
  playerColor: "w",
  selected: null,
  legalTargets: [],
  thinking: false,
  finished: false,
  sound: true,
  autoNudge: false,
  engineScoreAfter: 0, // engine POV, right after the engine's own move
  lastDecision: null,
  hintMove: null,
  pendingPromotion: null,
  history: [],
  appliedOffset: 0, // what the bandit has moved the level by (0 unless auto-goo is on)
  lastQuality: null,
  // the position the current game started from: the worker replays moves from here, so a
  // position loaded in the lab must be remembered (sending START_FEN broke every loaded game)
  baseFen: START_FEN,
  // bumped by anything that invalidates in-flight engine work (undo, new game, FEN load),
  // so a slow reply can never land on a position it did not come from
  generation: 0,
};

function bumpGeneration() {
  state.generation += 1;
  wasmBrain?.stop(); // a Stockfish search for the old position is worthless now
  return state.generation;
}

/** Level the engine actually plays at after the bandit's nudge. */
function effectiveLevel() {
  // the Stockfish levels are fixed points: the jelly bandit only nudges the home-grown levels
  if (isWasmLevel(state.level)) return state.level;
  return Math.max(1, Math.min(5, state.level + state.appliedOffset));
}

/** True for levels whose moves come from the WebAssembly Stockfish rather than the jelly search. */
function isWasmLevel(id) {
  return Boolean(rig && rig.level(id).wasm);
}

// Stockfish is downloaded lazily (1.8 MB) the first time it is needed.
let wasmBrain = null;
function brain() {
  if (!wasmBrain)
    wasmBrain = new WasmBrain({
      url: `${ASSETS}/vendor/stockfish/stockfish-19-lite-single.js`,
    });
  return wasmBrain;
}

/**
 * A random id kept in this browser's localStorage. The PHP backend uses it to keep "your record"
 * per visitor without accounts or IP addresses; the local python server ignores it.
 */
function visitorHeaders() {
  try {
    let id = localStorage.getItem("chessko.visitor");
    if (!id || !/^[a-f0-9]{32}$/.test(id)) {
      id = Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) =>
        b.toString(16).padStart(2, "0"),
      ).join("");
      localStorage.setItem("chessko.visitor", id);
    }
    return { "X-Chessko-Visitor": id };
  } catch {
    return {}; // storage blocked: the record simply is not kept between visits
  }
}

let rig = null;
let engine = null;
let search = null; // only used when running in-page (no worker)
let boardSquares = new Map();

// ---------------------------------------------------------------- engine host
/** Talks to web/js/engine.worker.js; falls back to an in-page search if workers fail. */
class EngineHost {
  constructor() {
    this.mode = "page";
    this.worker = null;
    this.pending = new Map();
    this.nextId = 1;
    this.search = null;
  }

  async boot(payload = {}, { seed = 20260929, reload = false } = {}) {
    if (!this.worker && typeof Worker === "function") {
      try {
        this.worker = new Worker(`${ASSETS}/js/engine.worker.js`, { type: "module" });
        this.worker.addEventListener("message", (event) =>
          this.onMessage(event),
        );
        this.worker.addEventListener("error", (event) => {
          this.fail(event.message || "worker error");
        });
        this.mode = "worker";
      } catch (err) {
        console.warn(
          "[chessko] worker unavailable, running in-page:",
          err.message,
        );
        this.worker = null;
        this.mode = "page";
      }
    }
    if (this.mode === "worker" && this.worker) {
      // the init message carries the trained weights + book into the worker
      return this.send({ type: "init", payload: { ...payload, seed } });
    }
    const { Evaluator } = await import("./eval.js");
    if (payload.model) rig.evaluator = new Evaluator(payload.model);
    if (payload.book) {
      rig.book = payload.book;
      rig.bookIndex = new Map(payload.book.keys.map((key, i) => [key, i]));
    }
    if (!this.search || reload) {
      this.search = new Search({
        evaluator: rig.evaluator,
        ttEntries: ensureEngineConfig(payload.config).ttEntries,
        rng: makeRng(seed),
      });
    } else {
      this.search.reset();
    }
    return { ok: true, mode: "page" };
  }

  onMessage(event) {
    const data = event.data || {};
    if (!data.id) return; // 'ready' style broadcasts carry no request id
    const entry = this.pending.get(data.id);
    if (!entry) return;
    this.pending.delete(data.id);
    if (data.type === "error") entry.reject(new Error(data.message));
    else entry.resolve(data.payload);
  }

  fail(message) {
    for (const [, entry] of this.pending) entry.reject(new Error(message));
    this.pending.clear();
  }

  send(message) {
    return new Promise((resolve, reject) => {
      const id = this.nextId++;
      this.pending.set(id, { resolve, reject });
      this.worker.postMessage({ ...message, id });
    });
  }

  /** Ask for a move (or a quick opinion). See web/js/engine.worker.js for the payload contract. */
  async think({ payload, fen, moves, level, seed, engineColor, quick }) {
    if (this.mode === "worker" && this.worker) {
      return this.send({
        type: quick ? "quick" : "think",
        fen,
        moves,
        level,
        seed,
        engineColor,
        payload,
      });
    }
    return runInPage({
      fen,
      moves,
      level,
      seed,
      engineColor,
      quick,
      payload,
      search: this.search,
      rig,
    });
  }

  async quickScore({ fen, moves, engineColor, depth = 2 }) {
    return this.think({
      quick: true,
      fen,
      moves,
      engineColor,
      payload: { depth },
    });
  }
}

function ensureEngineConfig(config) {
  return (
    (config && config.engine) || {
      ttEntries: 65536,
      mateScore: 100000,
      quiescenceMaxDepth: 6,
    }
  );
}

/** Same contract as the worker, executed on the main thread (fallback path). */
async function runInPage({
  fen,
  moves,
  level,
  seed,
  engineColor,
  quick,
  payload,
  search: searcher,
  rig: mlRig,
}) {
  if (!mlRig) return { error: "engine not ready" };
  if (payload && payload.model)
    mlRig.evaluator = new (await import("./eval.js")).Evaluator(payload.model);
  if (payload && payload.book) {
    mlRig.book = payload.book;
    mlRig.bookIndex = new Map(payload.book.keys.map((key, i) => [key, i]));
  }
  const game = new Chess(fen || START_FEN);
  for (const move of moves || []) game.move(move);
  const config = mlRig.level(level || state.level);
  const engineColour = engineColor || (game.turn() === "w" ? "b" : "w");

  if (quick) {
    const score = searcher.quickScore(game, {
      depth: (payload && payload.depth) || 2,
      learnedWeight: config.learnedWeight,
    });
    return { score, enginePov: game.turn() === engineColour ? score : -score };
  }

  const bookMove = mlRig.bookMove(game, config.bookPlies);
  let decision = bookMove
    ? {
        source: bookMove.source,
        detail: bookMove.detail,
        san: bookMove.san,
        count: bookMove.count,
        distance: bookMove.distance,
      }
    : { source: "search", detail: `alpha-beta depth ${config.depth}` };

  let chosen = bookMove ? bookMove.move : null;
  let think = null;
  if (!chosen) {
    think = searcher.think(game, {
      depth: config.depth,
      timeMs: config.timeMs,
      temperature: config.temperature,
      blunder: config.blunder,
      learnedWeight: config.learnedWeight,
      quiescence: config.quiescence,
      seed: seed === undefined ? null : seed,
    });
    if (!think.move) return { gameOver: true };
    chosen = think.move;
  } else {
    think = searcher.think(game, {
      depth: 1,
      timeMs: 60,
      temperature: 0,
      blunder: 0,
      learnedWeight: config.learnedWeight,
      quiescence: 0,
      seed: seed === undefined ? null : seed,
    });
  }

  game.move({ from: chosen.from, to: chosen.to, promotion: chosen.promotion });
  const afterScore = searcher.quickScore(game, {
    depth: 2,
    learnedWeight: config.learnedWeight,
  });
  const engineScoreAfter =
    game.turn() === engineColour ? afterScore : -afterScore;
  const describe = mlRig.describePosition(game, config.learnedWeight);
  return {
    move: {
      from: chosen.from,
      to: chosen.to,
      promotion: chosen.promotion || null,
      san: chosen.san,
    },
    think: {
      depth: think.depth,
      nodes: think.nodes,
      ms: think.ms,
      nps: think.nps,
      score: think.score,
      considered: think.considered,
      sampled: think.sampled,
    },
    engineScoreAfter,
    decision,
    describe,
    fenAfter: game.fen(),
    learnedWeight: config.learnedWeight,
    bookPlies: config.bookPlies,
    degraded: mlRig.degraded,
  };
}

// ------------------------------------------------------------------- rendering
function orientation() {
  return state.playerColor === "w";
}

function squareAt(fileIdx, rankIdx) {
  return `${FILES[fileIdx]}${rankIdx + 1}`;
}

function buildBoard() {
  const board = $("board");
  board.replaceChildren();
  boardSquares = new Map();
  const white = orientation();
  for (let row = 0; row < 8; row++) {
    for (let col = 0; col < 8; col++) {
      const fileIdx = white ? col : 7 - col;
      const rankIdx = white ? 7 - row : row;
      const name = squareAt(fileIdx, rankIdx);
      const button = document.createElement("button");
      button.type = "button";
      button.className = `sq ${(fileIdx + rankIdx) % 2 === 0 ? "dark" : "light"}`;
      button.dataset.square = name;
      button.setAttribute("role", "gridcell");
      const label = rankIdx === 0 || fileIdx === 0 ? ` (${name})` : "";
      button.setAttribute("aria-label", `${name}${label}`);
      board.appendChild(button);
      boardSquares.set(name, button);
    }
  }
}

const PIECE_LETTERS = { p: "p", n: "n", b: "b", r: "r", q: "q", k: "k" };

// Pieces that were just replaced by a piece of the other colour (a capture). They are kept for
// markLanded(), which plays the splat; anything not claimed by the next render is dropped.
let pendingGhosts = new Map();

function renderBoard() {
  pendingGhosts = new Map();
  for (const [name, button] of boardSquares) {
    const piece = state.chess.get(name);
    const type = piece ? PIECE_LETTERS[piece.toLowerCase()] : "";
    const color = piece ? (piece === piece.toUpperCase() ? "w" : "b") : "";
    // Compare against what this square currently shows: an empty square must clear a
    // piece that moved away (comparing only "which sprite is here" left stale pieces).
    const wanted = piece ? `${color}${type}` : "";
    if (button.dataset.piece !== wanted) {
      const before = button.querySelector(".piece");
      if (before && wanted && button.dataset.piece[0] !== color) {
        const ghost = before.cloneNode(true);
        ghost.classList.add("ghost");
        pendingGhosts.set(name, ghost);
      }
      button.replaceChildren();
      if (wanted) button.appendChild(pieceElement(color, type));
      button.dataset.piece = wanted;
    }
    button.classList.toggle("picked", state.selected === name);
    button.classList.toggle("target", state.legalTargets.includes(name));
  }
  paintLastMove();
  paintCheck();
}

function paintLastMove() {
  for (const button of boardSquares.values())
    button.classList.remove("last", "hint");
  const history = state.chess.history({ verbose: true });
  const last = history[history.length - 1];
  if (last) {
    boardSquares.get(last.from)?.classList.add("last");
    boardSquares.get(last.to)?.classList.add("last");
  }
  if (state.hintMove) {
    boardSquares.get(state.hintMove.from)?.classList.add("hint");
    boardSquares.get(state.hintMove.to)?.classList.add("hint");
  }
}

function paintCheck() {
  if (!state.chess.inCheck()) return;
  const turn = state.chess.turn();
  for (const [name, button] of boardSquares) {
    const piece = state.chess.get(name);
    if (
      piece &&
      piece.toUpperCase() === "K" &&
      (piece === piece.toUpperCase() ? "w" : "b") === turn
    ) {
      button.classList.add("check");
    } else {
      button.classList.remove("check");
    }
  }
}

function renderMoves() {
  const list = $("moves");
  list.innerHTML = "";
  const history = state.chess.history();
  history.forEach((san, i) => {
    const item = document.createElement("li");
    const mover = i % 2 === 0 ? "w" : "b";
    item.className = `move-pill ${mover === state.playerColor ? "you" : "engine"}`;
    item.textContent = `${Math.floor(i / 2) + 1}${mover === "w" ? "." : "…"} ${san}`;
    list.appendChild(item);
  });
  if (state.thinking) {
    const item = document.createElement("li");
    item.className = "move-pill thinking";
    item.textContent = "chessko is wobbling…";
    list.appendChild(item);
  }
}

function renderEval() {
  const learnedWeight = rig ? rig.level(effectiveLevel()).learnedWeight : 0.5;
  const board = state.chess.board();
  const turn = state.chess.turn();
  const blended = rig ? rig.evaluator.evaluate(board, turn, learnedWeight) : 0;
  const whiteCp = turn === "w" ? blended : -blended;
  const pct = Math.max(4, Math.min(96, 50 + (whiteCp / 800) * 46));
  $("eval-fill").style.width = `${pct}%`;
  const sign = whiteCp > 0 ? "+" : "";
  // The learned model has to extrapolate into the opening region (the corpus starts at ply 8),
  // so its level error there is about a pawn. Label a wide band honestly instead of
  // pretending to half-pawn precision.
  const BALANCED_BAND_CP = 100;
  $("eval-text").textContent =
    `${sign}${(whiteCp / 100).toFixed(2)} · ${Math.abs(whiteCp) < BALANCED_BAND_CP ? "balanced" : whiteCp > 0 ? "milk jelly ahead" : "blackcurrant ahead"}`;
  const desc = rig ? rig.describePosition(state.chess, learnedWeight) : null;
  $("eval-detail").textContent = desc
    ? `learned ${desc.learnedCp === null ? "off" : `${(desc.learnedCp / 100).toFixed(2)}`} · classical ${(desc.classicalCp / 100).toFixed(2)} · blend ${Math.round(learnedWeight * 100)}% learned (side to move wins ${(desc.winProb * 100).toFixed(0)}%)`
    : "model unavailable";
}

function setStatus(text, tone = "") {
  const node = $("status-text");
  node.textContent = text;
  node.className = `status ${tone}`;
  $("live-region").textContent = text;
}

function renderSideChips() {
  $("side-name").textContent = pieceFlavour(state.playerColor);
  const pieces = { p: 1, n: 3, b: 3, r: 5, q: 9 };
  const board = state.chess.board();
  let material = 0;
  for (let idx = 0; idx < 128; idx++) {
    if (idx & 0x88) continue;
    const piece = board[idx];
    if (!piece) continue;
    const value = pieces[Math.abs(piece)] || 0;
    material += piece > 0 ? value : -value;
  }
  const lead = state.playerColor === "w" ? material : -material;
  $("eval-bar").setAttribute(
    "aria-label",
    `Your material balance: ${lead > 0 ? "+" : ""}${lead}`,
  );
}

function renderLevels() {
  const list = $("level-list");
  list.replaceChildren();
  const levels = rig ? rig.levels : [];
  const playing = effectiveLevel();
  for (const level of levels) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "scoop";
    button.dataset.level = String(level.id);
    button.setAttribute("role", "radio");
    button.setAttribute("aria-checked", String(level.id === state.level));
    const blob = document.createElement("span");
    blob.className = "blob";
    blob.setAttribute("aria-hidden", "true");
    const name = document.createElement("span");
    name.className = "scoop-name";
    name.textContent = level.name;
    const tag = document.createElement("span");
    tag.className = "scoop-tag";
    tag.textContent = level.tagline;
    button.append(blob, name, tag);
    button.classList.toggle(
      "nudged",
      level.id === playing && level.id !== state.level,
    );
    button.addEventListener("click", () => selectLevel(level.id));
    list.appendChild(button);
  }
  const current = rig ? rig.level(state.level) : null;
  const note = current && current.wasm
    ? `Stockfish 19 in WebAssembly · ${current.wasm.elo ? `limited to UCI_Elo ${current.wasm.elo}` : "full strength"} · ${current.wasm.movetimeMs} ms per move · NNUE evaluation`
    : current
    ? `depth ${current.depth} · ${current.timeMs} ms · learned evaluation ${Math.round(current.learnedWeight * 100)}% · book ${current.bookPlies} plies · wobble ${current.temperature} cp${current.blunder ? ` · ${Math.round(current.blunder * 100)}% slips` : ""}`
    : "";
  $("level-note").textContent =
    state.appliedOffset === 0
      ? note
      : `${note} — the goo shifted play to ${rig ? rig.level(playing).name : playing} (${state.appliedOffset > 0 ? "+" : ""}${state.appliedOffset}).`;
}

function renderBrain() {
  if (!rig) return;
  const snap = rig.snapshot();
  const decision = state.lastDecision;
  $("ml-source").textContent = decision
    ? `last move came from: ${decision.source === "book" ? "the book (exact corpus position)" : decision.source === "knn-book" ? "the k-NN book" : decision.source === "stockfish" ? "Stockfish 19 (WebAssembly)" : "alpha-beta search"}${decision.detail ? ` — ${decision.detail}` : ""}${decision.san ? ` (${decision.san})` : ""}`
    : "click a piece to play; the brain panel fills in as the engine decides.";

  const stats = [
    [
      "model",
      snap.model.trained
        ? `${snap.model.positions} corpus positions, ${snap.model.weights} weights`
        : "not trained (classical evaluation only)",
    ],
    [
      "labels",
      snap.model.trained
        ? `${snap.model.labelKind === "teacher" ? "distilled from a deeper search" : "self-play results"} · calibration temperature ${snap.model.temperature}`
        : "—",
    ],
    ["validation", validationSummary(snap.model)],
    [
      "baseline log-loss",
      snap.model.baselineLogLoss === null
        ? "—"
        : `${snap.model.baselineLogLoss}`,
    ],
    ["trained at", snap.model.trainedAt || "—"],
    ["payload", payloadSummary()],
    [
      "book hits",
      `${snap.stats.bookHits} exact · ${snap.stats.knnHits} k-NN · ${snap.stats.bookMisses} misses`,
    ],
    [
      "last move quality",
      state.lastQuality
        ? `${state.lastQuality.quality} (engine eval moved ${state.lastQuality.delta > 0 ? "+" : ""}${state.lastQuality.delta} cp against you) · difficulty fit ${state.lastQuality.fit}`
        : "—",
    ],
    [
      "playing at",
      `${rig.level(effectiveLevel()).name}${state.appliedOffset ? ` (level ${state.level} ${state.appliedOffset > 0 ? "+" : ""}${state.appliedOffset})` : ""}`,
    ],
  ];
  const dl = $("ml-stats");
  dl.replaceChildren();
  for (const [key, value] of stats) {
    const dt = document.createElement("dt");
    dt.textContent = key;
    const dd = document.createElement("dd");
    dd.textContent = value;
    dl.append(dt, dd);
  }

  $("ml-book").textContent = snap.book
    ? `opening book: ${snap.book.entries} positions from ${snap.book.games} self-play games, k=${snap.book.k}, distance ≤ ${snap.book.threshold}`
    : "opening book unavailable — the engine opens with search only";

  const bandit = $("ml-bandit");
  bandit.replaceChildren();
  snap.bandit.arms.forEach((arm) => {
    const row = document.createElement("div");
    row.className = "bandit-row";
    const label = document.createElement("span");
    const sign = arm.offset > 0 ? "+" : "";
    label.textContent = `${sign}${arm.offset} level${arm.offset === snap.bandit.applied ? " ←" : ""}`;
    const bar = document.createElement("div");
    bar.className = "bandit-bar";
    const fill = document.createElement("span");
    fill.style.width = `${Math.round(arm.estimate * 100)}%`;
    bar.appendChild(fill);
    const value = document.createElement("span");
    value.textContent = arm.pulls
      ? `${arm.pulls}× · ${arm.meanReward} → ${arm.value}`
      : `untried · ${arm.value}`;
    row.append(label, bar, value);
    bandit.appendChild(row);
  });
  const suggestion = isWasmLevel(state.level) ? null : snap.bandit.suggestion;
  const nudge = document.createElement("p");
  nudge.className = "fineprint";
  if (isWasmLevel(state.level)) {
    nudge.textContent =
      "UCB1 bandit: resting — the Stockfish levels are fixed points, only the jelly levels get nudged.";
  } else if (suggestion === null) {
    nudge.textContent =
      snap.bandit.observations === 0
        ? "UCB1 bandit: no observations yet — it scores every move you play by how much chessko’s own evaluation improved afterwards."
        : `UCB1 bandit: this difficulty fits you (${snap.bandit.observations} observations, ${snap.bandit.arms[snap.bandit.keepArm].value} vs best ${Math.max(...snap.bandit.arms.map((a) => a.value))}).`;
  } else {
    const target = Math.max(1, Math.min(5, state.level + suggestion));
    const better = rig.level(target);
    const hint = document.createElement("span");
    hint.className = "bandit-nudge";
    hint.textContent = `try ${better.name}`;
    nudge.append(
      "UCB1 bandit: ",
      hint,
      ` — your moves are ${suggestion > 0 ? "stronger" : "weaker"} than this level expects (${snap.bandit.observations} observations). `,
    );
    const button = document.createElement("button");
    button.type = "button";
    button.className = "jbtn small";
    button.textContent = `switch to ${better.name}`;
    button.addEventListener("click", () => selectLevel(target));
    nudge.appendChild(button);
  }
  bandit.appendChild(nudge);
  const autoNote = document.createElement("p");
  autoNote.className = "fineprint";
  autoNote.textContent = state.autoNudge
    ? "auto-goo is on: the bandit moves the level itself between your moves."
    : "auto-goo is off: the bandit only suggests, the level stays where you put it.";
  bandit.appendChild(autoNote);

  const features = $("ml-features");
  features.replaceChildren();
  const desc = rig.describePosition(
    state.chess,
    rig.level(effectiveLevel()).learnedWeight,
  );
  for (const feature of desc.topFeatures) {
    const row = document.createElement("div");
    row.className = "feature-row";
    const name = document.createElement("span");
    name.textContent = feature.name;
    const bar = document.createElement("div");
    bar.className = "feature-bar";
    const fill = document.createElement("span");
    const magnitude = Math.min(50, Math.abs(feature.contribution) * 40);
    fill.style.width = `${magnitude}%`;
    fill.style.left = feature.contribution >= 0 ? "50%" : `${50 - magnitude}%`;
    if (feature.contribution < 0) fill.className = "neg";
    bar.appendChild(fill);
    const value = document.createElement("span");
    value.textContent = `${feature.contribution >= 0 ? "+" : ""}${feature.contribution.toFixed(2)}`;
    row.append(name, bar, value);
    features.appendChild(row);
  }

  drawLossSpark(snap);
  const algs = $("ml-algs");
  algs.replaceChildren();
  for (const algorithm of snap.algorithms) {
    const item = document.createElement("li");
    const strong = document.createElement("b");
    strong.textContent = `${algorithm.name} (${algorithm.kind})`;
    item.append(strong, document.createTextNode(` — ${algorithm.detail}`));
    algs.appendChild(item);
  }
}

function payloadSummary() {
  const sizes = rig?.payloadBytes || {};
  const parts = [];
  if (sizes.model) parts.push(`model ${(sizes.model / 1024).toFixed(1)} KiB`);
  if (sizes.book) parts.push(`book ${(sizes.book / 1024).toFixed(1)} KiB`);
  return parts.length ? parts.join(" + ") : "served from the backend API";
}

/**
 * Honest validation summary. With distilled teacher labels the meaningful numbers are the
 * mean absolute error against the teacher and how often the direction agrees; with raw
 * game results they are log loss and accuracy against the majority-class floor.
 */
function validationSummary(model) {
  if (!model.trained) return "—";
  if (model.labelKind === "teacher" && model.valMaeCp !== null) {
    const agreement =
      model.valSignAgreement === null
        ? "—"
        : `${(model.valSignAgreement * 100).toFixed(1)}%`;
    return `${model.valMaeCp} cp mean error vs the teacher (constant predictor: ${model.baselineMaeCp} cp) · direction agreement ${agreement} · log-loss ${model.valLogLoss} vs ${model.baselineLogLoss} baseline`;
  }
  return `${(model.valAccuracy * 100).toFixed(1)}% accuracy (majority class ${(model.baselineAccuracy * 100).toFixed(1)}%), log-loss ${model.valLogLoss} vs ${model.baselineLogLoss} baseline`;
}

function drawLossSpark(snap) {
  const svg = $("loss-spark");
  svg.replaceChildren();
  const model = rig?.evaluator?.model;
  const history =
    model && Array.isArray(model.loss_history) ? model.loss_history : [];
  if (history.length < 2) {
    $("ml-loss").textContent = "no training history available";
    return;
  }
  const min = Math.min(...history);
  const max = Math.max(...history);
  const span = max - min || 1;
  const points = history.map((loss, i) => {
    const x = (i / (history.length - 1)) * 120;
    const y = 30 - ((loss - min) / span) * 26 - 2;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });
  const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
  path.setAttribute("d", `M${points.join(" L")}`);
  svg.appendChild(path);
  const first = history[0];
  const last = history[history.length - 1];
  $("ml-loss").textContent =
    `training loss over ${history.length} epochs: ${first} → ${last} (soft-label cross entropy, lower is better)`;
  $("ml-spark-note")?.remove();
}

function renderPerf(think) {
  $("perf-value").textContent = think
    ? `${think.ms} ms · depth ${think.depth} · ${think.nodes.toLocaleString()} nodes (${Math.round(think.nps / 1000)}k nps)`
    : "—";
}

async function renderRecord() {
  try {
    const [stats, recent] = await Promise.all([
      fetch(`${API}/games/stats`, { headers: visitorHeaders() }).then((r) => r.json()),
      fetch(`${API}/games/recent?limit=6`, { headers: visitorHeaders() }).then((r) => r.json()),
    ]);
    const dl = $("record-stats");
    dl.replaceChildren();
    const total = stats.total || { games: 0, win: 0, loss: 0, draw: 0 };
    const rows = [
      ["games", String(total.games)],
      ["you won", String(total.win)],
      ["chessko won", String(total.loss)],
      ["draws", String(total.draw)],
    ];
    for (const [key, value] of rows) {
      const dt = document.createElement("dt");
      dt.textContent = key;
      const dd = document.createElement("dd");
      dd.textContent = value;
      dl.append(dt, dd);
    }
    const list = $("record-recent");
    list.replaceChildren();
    if (!recent.games || !recent.games.length) {
      const item = document.createElement("li");
      item.textContent =
        "no finished games recorded yet - the backend stores them in data/gameresults.json";
      list.appendChild(item);
    } else {
      for (const game of recent.games) {
        const item = document.createElement("li");
        item.textContent = `${new Date(game.t * 1000).toLocaleString()} · ${game.level_name || `level ${game.level}`} · you played ${game.player_color === "w" ? "milk" : "blackcurrant"} · ${game.result} after ${game.plies} plies`;
        list.appendChild(item);
      }
    }
  } catch (err) {
    const dl = $("record-stats");
    dl.replaceChildren();
    const list = $("record-recent");
    list.replaceChildren();
    const item = document.createElement("li");
    item.textContent = `record unavailable (${err.message})`;
    list.appendChild(item);
  }
}

function renderBackendLine(health) {
  const line = $("backend-line");
  const kind = health && health.backend ? health.backend : "python";
  line.textContent = health
    ? `served by the chessko ${kind} backend (${health.python || health.php || kind}) · model ${health.model ? `${health.model.positions} positions, trained ${health.model.trained_at}` : "not trained"} · ${health.games_recorded} games recorded`
    : "served by the chessko python backend";
}

// ---------------------------------------------------------------- game actions
function gameOverState() {
  if (state.chess.isCheckmate()) {
    const winner = state.chess.turn() === "w" ? "b" : "w";
    return {
      over: true,
      result: winner === state.playerColor ? "win" : "loss",
      text:
        winner === state.playerColor
          ? "checkmate — you won! 🍮"
          : "checkmate — chessko wins",
    };
  }
  if (state.chess.isStalemate())
    return {
      over: true,
      result: "draw",
      text: "stalemate — the jelly is stuck",
    };
  if (state.chess.isInsufficientMaterial())
    return { over: true, result: "draw", text: "draw — not enough jelly left" };
  if (state.chess.isThreefoldRepetition())
    return { over: true, result: "draw", text: "draw — threefold repetition" };
  if (state.chess.isFiftyMoves())
    return {
      over: true,
      result: "draw",
      text: "draw — fifty moves without a capture",
    };
  if (state.chess.isDraw()) return { over: true, result: "draw", text: "draw" };
  return { over: false };
}

async function selectLevel(id) {
  state.level = id;
  state.appliedOffset = 0; // a fresh explicit choice resets the bandit's nudge
  renderLevels();
  renderEval();
  if (isWasmLevel(id)) brain().ready().catch(() => {}); // start the download while the player thinks
  if (rig) rig.bandit.markApplied(0);
  setStatus(
    `Squishiness set to ${rig ? rig.level(id).name : id}. Your move.`,
    "",
  );
}

function persistLevel() {
  try {
    localStorage.setItem("chessko.level", String(state.level));
  } catch {
    /* storage may be unavailable */
  }
  return Promise.resolve();
}

function refresh() {
  renderBoard();
  renderMoves();
  renderEval();
  renderSideChips();
  renderBrain();
}

async function newGame({ keepSide = true } = {}) {
  bumpGeneration();
  // switch colours before rebuilding: the board orientation is derived from playerColor
  if (!keepSide) state.playerColor = state.playerColor === "w" ? "b" : "w";
  state.chess = new Chess();
  state.baseFen = START_FEN;
  state.selected = null;
  state.legalTargets = [];
  state.finished = false;
  state.thinking = false;
  state.hintMove = null;
  state.lastDecision = null;
  state.engineScoreAfter = 0;
  buildBoard();
  refresh();
  $("result-dialog").close();
  renderLevels();
  if (state.chess.turn() !== state.playerColor) {
    await engineMove();
  } else {
    setStatus(
      state.playerColor === "w"
        ? "Your move — click a milk-jelly piece."
        : "Your move — blackcurrant jelly is yours.",
    );
  }
}

function isPlayersTurn() {
  return state.chess.turn() === state.playerColor && !state.finished;
}

function movesFrom(square) {
  return state.chess.moves({ square, verbose: true });
}

async function selectSquare(square) {
  if (!isPlayersTurn()) return;
  const piece = state.chess.get(square);
  if (state.selected) {
    const target = state.legalTargets.includes(square);
    if (target) {
      const options = movesFrom(state.selected).filter(
        (move) => move.to === square,
      );
      if (options.some((move) => move.promotion)) {
        state.pendingPromotion = { from: state.selected, to: square };
        openPromotion(state.selected);
        return;
      }
      await playPlayerMove({ from: state.selected, to: square });
      return;
    }
    if (piece && pieceIsPlayers(piece)) {
      state.selected = square;
      state.legalTargets = movesFrom(square).map((move) => move.to);
      state.hintMove = null;
      refresh();
      return;
    }
    state.selected = null;
    state.legalTargets = [];
    refresh();
    return;
  }
  if (!piece || !pieceIsPlayers(piece)) {
    setStatus(
      piece
        ? "that piece is chessko’s — pick one of yours."
        : "Pick a piece of yours to move.",
    );
    return;
  }
  state.selected = square;
  state.legalTargets = movesFrom(square).map((move) => move.to);
  refresh();
}

function pieceIsPlayers(piece) {
  return (piece === piece.toUpperCase() ? "w" : "b") === state.playerColor;
}

async function playPlayerMove({ from, to, promotion = null }) {
  let played;
  try {
    // only pass `promotion` when this move really promotes: chess.js rejects
    // a promotion field on ordinary moves as an illegal move
    const isPromotion = movesFrom(from).some(
      (move) => move.to === to && move.promotion,
    );
    played = state.chess.move(
      isPromotion ? { from, to, promotion: promotion || "q" } : { from, to },
    );
  } catch (err) {
    setStatus(`that move is not legal (${err.message})`, "warn");
    state.selected = null;
    state.legalTargets = [];
    refresh();
    return;
  }
  state.selected = null;
  state.legalTargets = [];
  state.hintMove = null;
  refresh();
  markLanded(played.to);
  squish(1.2);

  const over = gameOverState();
  if (over.over) return finishGame(over);

  await observePlayerMove(played);
  await engineMove();
}

async function observePlayerMove(played) {
  if (isWasmLevel(state.level)) return; // the bandit only tunes the jelly levels
  // Bandit reward: how much the engine's own evaluation improved after your move.
  const generation = state.generation;
  try {
    const quick = await engine.quickScore({
      fen: state.chess.fen(),
      moves: [],
      engineColor: state.playerColor === "w" ? "b" : "w",
      depth: 2,
    });
    if (generation !== state.generation) return; // the position was undone or replaced meanwhile
    const delta = quick.enginePov - state.engineScoreAfter; // >0 means chessko gained
    const quality = Math.max(0, Math.min(1, 1 - delta / 250));
    rig.bandit.observeQuality(quality, state.appliedOffset);
    state.lastQuality = {
      quality: Number(quality.toFixed(3)),
      delta: Math.round(delta),
      move: played.san,
      fit: Number(
        rig.bandit
          .fitOf(quality, state.appliedOffset, state.appliedOffset)
          .toFixed(3),
      ),
    };

    if (state.autoNudge) {
      const suggestion = rig.bandit.snapshot().suggestion;
      if (suggestion !== null && suggestion !== state.appliedOffset) {
        state.appliedOffset = suggestion;
        rig.bandit.markApplied(suggestion);
        const name = rig.level(effectiveLevel()).name;
        setStatus(
          `auto-goo moved the level to ${name} (${suggestion > 0 ? "+" : ""}${suggestion}).`,
        );
        renderLevels();
      }
    }
    renderBrain();
  } catch (err) {
    console.warn("[chessko] bandit observation skipped:", err.message);
  }
}

async function engineMove() {
  if (state.finished) return;
  const generation = state.generation;
  state.thinking = true;
  $("think-chip").hidden = false;
  setStatus("chessko is wobbling…");
  renderMoves();
  try {
    const moves = state.chess
      .history({ verbose: true })
      .map((move) => ({
        from: move.from,
        to: move.to,
        promotion: move.promotion,
      }));
    const config = rig.level(effectiveLevel());
    const result = config.wasm
      ? await wasmMove(config, moves)
      : await engine.think({
          fen: state.baseFen,
          moves,
          level: effectiveLevel(),
          engineColor: state.playerColor === "w" ? "b" : "w",
          seed: Date.now() % 100000,
        });
    if (generation !== state.generation) {
      // the game moved on (undo / new game / loaded position): drop this reply
      state.thinking = false;
      $("think-chip").hidden = true;
      return;
    }
    if (result.stale) {
      state.thinking = false;
      $("think-chip").hidden = true;
      return;
    }
    if (result.error) throw new Error(result.error);
    if (result.gameOver || !result.move) {
      state.thinking = false;
      $("think-chip").hidden = true;
      const over = gameOverState();
      if (over.over) return finishGame(over);
      setStatus("chessko has no legal move.");
      return;
    }
    const played = state.chess.move(
      result.move.promotion
        ? {
            from: result.move.from,
            to: result.move.to,
            promotion: result.move.promotion,
          }
        : { from: result.move.from, to: result.move.to },
    );
    state.engineScoreAfter = result.engineScoreAfter;
    state.lastDecision = result.decision;
    state.lastDescribe = result.describe;
    state.thinking = false;
    $("think-chip").hidden = true;
    renderPerf(result.think);
    squish(0.8);
    markLanded(result.move.to);
    refresh();

    const over = gameOverState();
    if (over.over) return finishGame(over);
    if (state.chess.inCheck())
      setStatus("check! your king is wobbling.", "warn");
    else setStatus(`Your move. chessko played ${played.san}.`);
  } catch (err) {
    state.thinking = false;
    $("think-chip").hidden = true;
    setStatus(`engine trouble: ${err.message}`, "warn");
    console.error("[chessko] engine error", err);
  }
}

/**
 * A move from the WebAssembly Stockfish, shaped like a reply from the jelly worker so the rest of
 * engineMove() does not care which brain answered. Falls back to the jelly search at full effort
 * (level 5) if WebAssembly is unavailable.
 */
async function wasmMove(config, moves) {
  const engineColor = state.playerColor === "w" ? "b" : "w";
  let answer;
  try {
    setStatus(`${config.name} is loading Stockfish…`);
    answer = await brain().bestMove({
      fen: state.baseFen,
      moves,
      movetimeMs: config.wasm.movetimeMs,
      elo: config.wasm.elo,
    });
    setStatus(`${config.name} is thinking…`);
  } catch (err) {
    console.warn("[chessko] Stockfish unavailable, using the jelly search:", err.message);
    setStatus("Stockfish could not start here — the jelly search answers instead.", "warn");
    return engine.think({
      fen: state.baseFen,
      moves,
      level: 5,
      engineColor,
      seed: Date.now() % 100000,
    });
  }
  if (answer.stopped) return { stale: true };
  if (!answer.move) return { gameOver: true };

  const after = new Chess(state.chess.fen());
  const played = after.move(answer.move);
  const describe = rig.describePosition(after, config.learnedWeight);
  return {
    move: {
      from: answer.move.from,
      to: answer.move.to,
      promotion: answer.move.promotion,
      san: played.san,
    },
    think: {
      depth: answer.depth,
      nodes: answer.nodes,
      ms: answer.ms,
      nps: answer.nps,
      score: answer.cp,
    },
    engineScoreAfter: answer.cp,
    decision: {
      source: "stockfish",
      detail: `depth ${answer.depth}, ${answer.nodes.toLocaleString()} nodes${answer.mate !== undefined ? `, mate in ${Math.abs(answer.mate)}` : ""}${config.wasm.elo ? `, UCI_Elo ${config.wasm.elo}` : ", full strength"}`,
    },
    describe,
    fenAfter: after.fen(),
    learnedWeight: config.learnedWeight,
    level: config,
  };
}

function markLanded(square) {
  const button = boardSquares.get(square);
  if (!button) return;
  button.classList.add("landed");
  setTimeout(() => button.classList.remove("landed"), 950);

  const ghost = pendingGhosts.get(square);
  if (ghost) {
    pendingGhosts.delete(square);
    button.appendChild(ghost);
    ghost.addEventListener("animationend", () => ghost.remove(), { once: true });
    setTimeout(() => ghost.remove(), 700); // reduced-motion / hidden tab safety net
  }

  // the shock spreads through the squares around the landing: nearer squares move first
  const file = square.charCodeAt(0) - 97;
  const rank = Number(square[1]) - 1;
  for (const [name, neighbour] of boardSquares) {
    const ring = Math.max(
      Math.abs(name.charCodeAt(0) - 97 - file),
      Math.abs(Number(name[1]) - 1 - rank),
    );
    if (ring < 1 || ring > 2) continue;
    neighbour.style.setProperty("--ring", String(ring));
    neighbour.classList.remove("jolt");
    void neighbour.offsetWidth; // restart the animation if a previous ripple is still running
    neighbour.classList.add("jolt");
    setTimeout(() => neighbour.classList.remove("jolt"), 900);
  }
  const shell = $("board-shell");
  shell.classList.remove("thump");
  void shell.offsetWidth;
  shell.classList.add("thump");
  setTimeout(() => shell.classList.remove("thump"), 560);
}

async function finishGame(over) {
  state.finished = true;
  state.thinking = false;
  $("think-chip").hidden = true;
  setStatus(
    over.text,
    over.result === "win" ? "good" : over.result === "loss" ? "warn" : "",
  );
  renderMoves();
  $("result-title").textContent =
    over.result === "win"
      ? "you won! 🍮"
      : over.result === "loss"
        ? "chessko wins 🫐"
        : "a wobbly draw";
  $("result-text").textContent =
    `${over.text} — ${state.chess.history().length} plies against ${rig ? rig.level(state.level).name : "level " + state.level}.`;
  $("result-dialog").showModal();
  if (over.result === "win") celebrate();
  try {
    await fetch(`${API}/games`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...visitorHeaders() },
      body: JSON.stringify({
        engine: rig && rig.level(state.level).wasm ? "stockfish" : "jelly",
        level: state.level,
        level_name: rig ? rig.level(state.level).name : null,
        effective_level: effectiveLevel(),
        nudge: state.appliedOffset,
        result: over.result,
        player_color: state.playerColor,
        plies: state.chess.history().length,
        ml: {
          bookHits: rig?.stats.bookHits,
          knnHits: rig?.stats.knnHits,
          lastSource: state.lastDecision?.source ?? null,
          bandit: rig?.bandit.snapshot(),
        },
      }),
    });
  } catch (err) {
    console.warn("[chessko] could not record the game:", err.message);
  }
  renderRecord();
}

function celebrate() {
  if (typeof window.confetti === "function") {
    window.confetti({
      particleCount: 90,
      spread: 70,
      startVelocity: 34,
      scalar: 0.9,
      colors: ["#e0567c", "#5fd39a", "#4a2242", "#fffdf8"],
      origin: { y: 0.7 },
    });
  } else {
    document.body.classList.add("celebrate-fallback");
    setTimeout(() => document.body.classList.remove("celebrate-fallback"), 900);
  }
}

async function undo() {
  const history = state.chess.history();
  if (!history.length) return;
  // cancel anything in flight: its reply belongs to a position that no longer exists
  bumpGeneration();
  state.thinking = false;
  $("think-chip").hidden = true;
  state.chess.undo();
  if (state.chess.turn() !== state.playerColor && state.chess.history().length)
    state.chess.undo();
  state.selected = null;
  state.legalTargets = [];
  state.finished = false;
  state.hintMove = null;
  refresh();
  setStatus("Undone. Your move.");
}

async function hint() {
  if (!isPlayersTurn()) return;
  const generation = state.generation;
  setStatus("chessko is thinking about your position…");
  const moves = state.chess
    .history({ verbose: true })
    .map((move) => ({
      from: move.from,
      to: move.to,
      promotion: move.promotion,
    }));
  let result;
  try {
    // hints are always full strength Stockfish: a hint should be the best move, not a wobbly one
    const answer = await brain().bestMove({
      fen: state.baseFen,
      moves,
      movetimeMs: 600,
      elo: null,
    });
    if (answer.stopped || !answer.move) return;
    const probe = new Chess(state.chess.fen());
    const played = probe.move(answer.move);
    result = {
      move: { ...answer.move, san: played.san },
      think: { depth: answer.depth, ms: answer.ms },
    };
  } catch (err) {
    console.warn("[chessko] Stockfish hint unavailable, using the jelly search:", err.message);
    result = await engine.think({
      fen: state.baseFen,
      moves,
      level: Math.max(3, effectiveLevel()),
      engineColor: state.chess.turn() === "w" ? "b" : "w",
      seed: Date.now() % 100000,
    });
  }
  if (generation !== state.generation) return; // the position changed while thinking
  if (result.move) {
    state.hintMove = { from: result.move.from, to: result.move.to };
    refresh();
    setStatus(
      `hint: ${result.move.san} (depth ${result.think.depth}, ${result.think.ms} ms)`,
    );
  }
}

function openPromotion(from) {
  const picks = $("promo-picks");
  picks.replaceChildren();
  for (const type of PROMOTION_TYPES) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "promo-pick";
    button.dataset.promotion = type;
    button.setAttribute("aria-label", `promote to ${type}`);
    button.appendChild(pieceElement(state.playerColor, type));
    button.addEventListener("click", async () => {
      const pending = state.pendingPromotion;
      state.pendingPromotion = null;
      $("promotion-dialog").close();
      if (pending)
        await playPlayerMove({
          from: pending.from,
          to: pending.to,
          promotion: type,
        });
    });
    picks.appendChild(button);
  }
  $("promotion-dialog").showModal();
}

// ------------------------------------------------------------------ click + drag
function wireBoard() {
  const board = $("board");

  // click-to-move: first click picks a piece, second click plays (or re-picks)
  board.addEventListener("click", (event) => {
    const button = event.target.closest(".sq");
    if (!button) return;
    selectSquare(button.dataset.square);
  });

  board.addEventListener("dragstart", (event) => {
    const button = event.target.closest(".sq");
    if (!button) return;
    const piece = state.chess.get(button.dataset.square);
    if (!piece || !pieceIsPlayers(piece) || !isPlayersTurn()) {
      event.preventDefault();
      return;
    }
    event.dataTransfer.setData("text/plain", button.dataset.square);
    event.dataTransfer.effectAllowed = "move";
    button.querySelector(".piece")?.classList.add("dragging");
  });
  board.addEventListener("dragend", () => {
    board
      .querySelectorAll(".piece.dragging")
      .forEach((node) => node.classList.remove("dragging"));
  });
  board.addEventListener("dragover", (event) => {
    const button = event.target.closest(".sq");
    if (button) event.preventDefault();
  });
  board.addEventListener("drop", async (event) => {
    const button = event.target.closest(".sq");
    if (!button) return;
    event.preventDefault();
    const from = event.dataTransfer.getData("text/plain");
    if (!from || from === button.dataset.square) return;
    state.selected = from;
    state.legalTargets = movesFrom(from).map((move) => move.to);
    const options = movesFrom(from).filter(
      (move) => move.to === button.dataset.square,
    );
    if (options.some((move) => move.promotion)) {
      state.pendingPromotion = { from, to: button.dataset.square };
      openPromotion(from);
      return;
    }
    if (options.length)
      await playPlayerMove({ from, to: button.dataset.square });
    else {
      setStatus("that jelly cannot land there.", "warn");
      state.selected = null;
      state.legalTargets = [];
      refresh();
    }
  });
}

// ----------------------------------------------------------------------- sound
let audioContext = null;

function squish(pitch = 1) {
  if (!state.sound) return;
  try {
    audioContext =
      audioContext || new (window.AudioContext || window.webkitAudioContext)();
    const now = audioContext.currentTime;
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.type = "sine";
    oscillator.frequency.setValueAtTime(220 * pitch, now);
    oscillator.frequency.exponentialRampToValueAtTime(90 * pitch, now + 0.14);
    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.06, now + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.18);
  } catch {
    /* audio is a garnish — never block the game on it */
  }
}

// ------------------------------------------------------------------------ boot
function wireControls() {
  $("new-game").addEventListener("click", () => newGame());
  $("undo").addEventListener("click", () => undo());
  $("flip").addEventListener("click", async () => {
    await newGame({ keepSide: false });
  });
  $("hint").addEventListener("click", () => hint());
  $("result-again").addEventListener("click", () => {
    $("result-dialog").close();
    newGame();
  });
  $("result-close").addEventListener("click", () => $("result-dialog").close());
  $("fen-load").addEventListener("click", () => {
    const fen = $("fen-input").value.trim();
    if (!fen) return;
    try {
      bumpGeneration();
      state.thinking = false;
      $("think-chip").hidden = true;
      state.chess.load(fen);
      state.baseFen = state.chess.fen(); // replay origin for the engine
      state.finished = false;
      state.selected = null;
      state.legalTargets = [];
      state.hintMove = null;
      refresh();
      setStatus("Position loaded. Your move.");
    } catch (err) {
      setStatus(`could not load that FEN (${err.message})`, "warn");
    }
  });
  $("fen-copy").addEventListener("click", async () => {
    const fen = state.chess.fen();
    $("fen-input").value = fen;
    try {
      await navigator.clipboard.writeText(fen);
      setStatus("FEN copied to the clipboard.");
    } catch {
      setStatus("FEN is in the lab field — clipboard was blocked.");
    }
  });
  $("sound-toggle").addEventListener("click", (event) => {
    state.sound = !state.sound;
    event.currentTarget.textContent = `sound: ${state.sound ? "on" : "off"}`;
    event.currentTarget.setAttribute("aria-pressed", String(state.sound));
    if (state.sound) squish(1);
  });
  $("reset-bandit").addEventListener("click", () => {
    rig.bandit.reset();
    state.appliedOffset = 0;
    renderLevels();
    renderBrain();
    setStatus("Adaptation reset — the bandit starts learning you again.");
  });
  $("auto-nudge").addEventListener("change", (event) => {
    state.autoNudge = event.currentTarget.checked;
    renderBrain();
    setStatus(
      state.autoNudge
        ? "auto-goo is on: the bandit may shift the difficulty between your moves."
        : "auto-goo is off: the level stays where you put it.",
    );
  });
  $("retrain").addEventListener("click", async () => {
    const button = $("retrain");
    button.disabled = true;
    button.textContent = "training…";
    try {
      const res = await fetch(`${API}/train`, { method: "POST" });
      const payload = await res.json();
      if (!res.ok) throw new Error(payload.error || `HTTP ${res.status}`);
      await bootEngine({ reload: true });
      setStatus(
        `Re-trained: validation accuracy ${(payload.model.val_accuracy * 100).toFixed(1)}% in ${payload.model.train_seconds}s.`,
      );
    } catch (err) {
      setStatus(`training failed: ${err.message}`, "warn");
    } finally {
      button.disabled = false;
      button.textContent = "retrain on the backend";
    }
  });
}

async function bootEngine({ reload = false } = {}) {
  const payload = {
    model: rig.evaluator.model,
    book: rig.book,
    config: rig.config,
  };
  const result = await engine.boot(payload, {
    seed: reload ? Date.now() % 100000 : 20260929,
    reload,
  });
  document.body.dataset.engine = engine.mode;
  return result;
}

async function init() {
  wrapWordmark();
  buildBoard();
  wireBoard();
  wireControls();
  setStatus("loading the jelly brain…");

  rig = await MlRig.load({ persist: true });
  rig.payloadBytes = await measurePayloads();
  try {
    const stored = Number(localStorage.getItem("chessko.level"));
    if (stored >= 1 && stored <= 5) state.level = stored;
  } catch {
    /* storage unavailable */
  }
  $("auto-nudge").checked = state.autoNudge;

  engine = new EngineHost();
  const boot = await bootEngine();
  document.body.dataset.engine = boot && boot.mode ? boot.mode : "page";
  renderLevels();
  refresh();
  await renderRecord();

  try {
    const health = await fetch(`${API}/health`).then((r) => r.json());
    renderBackendLine(health);
    if (health.can_train === false) $("retrain").hidden = true;
    if (health.can_train !== false && !health.model)
      setStatus(
        "The backend has no trained model yet — press “retrain on the backend”.",
        "warn",
      );
  } catch (err) {
    renderBackendLine(null);
    console.warn("[chessko] health check failed:", err.message);
  }

  if (state.chess.turn() !== state.playerColor) await engineMove();
  else setStatus("Your move — click a milk-jelly piece.");
  document.body.dataset.ready = "true";

  window.chessko = {
    state,
    chess: () => state.chess,
    rig: () => rig,
    engine,
    level: (id) => selectLevel(id),
    fen: (fen) => {
      bumpGeneration();
      state.thinking = false;
      $("think-chip").hidden = true;
      state.chess.load(fen);
      state.baseFen = state.chess.fen(); // the engine replays from here, not from the start
      state.finished = false;
      state.hintMove = null;
      refresh();
      return state.chess.fen();
    },
    move: (move) =>
      playPlayerMove(typeof move === "string" ? parseSan(move) : move),
    engineMove: () => engineMove(),
    ml: () => rig.snapshot(),
    mode: () => engine.mode,
    levelInfo: () => ({
      level: state.level,
      effective: effectiveLevel(),
      offset: state.appliedOffset,
    }),
    // local model opinion for an arbitrary FEN — used by the e2e test to compare
    // the browser's evaluation against the python backend's /api/eval
    probe: (fen) => {
      const probeGame = new Chess(fen);
      const desc = rig.describePosition(
        probeGame,
        rig.level(effectiveLevel()).learnedWeight,
      );
      return {
        features: extractFeatures(probeGame.board(), probeGame.turn()),
        winProb: desc.winProb,
        blendedCp: desc.blendedCp,
      };
    },
    version: "1.0.0",
  };
}

function parseSan(san) {
  const legal = state.chess.moves({ verbose: true });
  const found = legal.find(
    (move) =>
      move.san === san ||
      move.san.replace(/[+#]/g, "") === san.replace(/[+#]/g, ""),
  );
  if (!found) throw new Error(`illegal move: ${san}`);
  return { from: found.from, to: found.to, promotion: found.promotion || null };
}

async function measurePayloads() {
  const sizes = {};
  try {
    const [model, book] = await Promise.all([
      fetch(`${API}/model`)
        .then((r) =>
          r.ok ? Number(r.headers.get("content-length")) || null : null,
        )
        .catch(() => null),
      fetch(`${API}/book`)
        .then((r) =>
          r.ok ? Number(r.headers.get("content-length")) || null : null,
        )
        .catch(() => null),
    ]);
    if (model) sizes.model = model;
    if (book) sizes.book = book;
  } catch {
    /* sizes are cosmetic */
  }
  return sizes;
}

function wrapWordmark() {
  const node = $("wordmark");
  const text = node.textContent.trim();
  node.textContent = "";
  for (const ch of text) {
    const span = document.createElement("span");
    span.className = "glyph";
    span.textContent = ch;
    node.appendChild(span);
  }
}

init().catch((err) => {
  console.error("[chessko] init failed", err);
  setStatus(`could not start: ${err.message}`, "warn");
});
