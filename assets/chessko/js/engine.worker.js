// Engine worker: keeps the search off the main thread so the jelly keeps wobbling
// smoothly while chessko thinks. Mirrors the in-page fallback in app.js.

import * as ChessModule from "./chess.js";
import { MlRig } from "./ml.js";
import { Search, makeRng } from "./search.js";

const Chess = ChessModule.Chess || ChessModule.default;
const START_FEN =
  ChessModule.START_FEN ||
  "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";

let rig = null;
let search = null;

function ensureRig(payload = {}) {
  rig = new MlRig({
    model: payload.model || (rig ? rig.evaluator.model : null),
    book: payload.book || (rig ? rig.book : null),
    config: payload.config || (rig ? rig.config : null),
    persist: false, // the bandit lives on the main thread, not in the worker
    seed: payload.seed || 20260929,
  });
  return rig;
}

function ensureSearch(seed) {
  if (!search)
    search = new Search({
      evaluator: rig.evaluator,
      ttEntries: (rig.engineConfig && rig.engineConfig.ttEntries) || 65536,
      rng: makeRng(seed),
    });
  else search.evaluator = rig.evaluator;
  return search;
}

function replay(fen, moves) {
  const game = new Chess(fen || START_FEN);
  for (const move of moves || []) game.move(move);
  return game;
}

self.addEventListener("message", (event) => {
  const { id, type, payload, fen, moves, level, seed, engineColor } =
    event.data || {};
  try {
    if (type === "init") {
      ensureRig(payload || {});
      ensureSearch((payload && payload.seed) || 20260929);
      search.reset();
      postMessage({
        id,
        type: "ok",
        payload: {
          mode: "worker",
          trained: rig.evaluator.trained,
          bookEntries: rig.book ? rig.book.entries : 0,
        },
      });
      return;
    }

    if (!rig || !search) throw new Error("worker not initialised");

    const game = replay(fen, moves);
    const config = rig.level(level);
    const engineColour = engineColor || (game.turn() === "w" ? "b" : "w");

    if (type === "quick") {
      const score = search.quickScore(game, {
        depth: (payload && payload.depth) || 2,
        learnedWeight: config.learnedWeight,
      });
      postMessage({
        id,
        type: "ok",
        payload: {
          score,
          enginePov: game.turn() === engineColour ? score : -score,
          turn: game.turn(),
        },
      });
      return;
    }

    if (type === "think") {
      const bookMove = rig.bookMove(game, config.bookPlies);
      const decision = bookMove
        ? {
            source: bookMove.source,
            detail: bookMove.detail,
            san: bookMove.san,
            count: bookMove.count,
            distance: bookMove.distance,
          }
        : { source: "search", detail: `alpha-beta depth ${config.depth}` };

      let chosen = bookMove ? bookMove.move : null;
      let think;
      if (chosen) {
        // still run a tiny search so the UI gets timing numbers and a score for the position
        think = search.think(game, {
          depth: 1,
          timeMs: 60,
          temperature: 0,
          blunder: 0,
          learnedWeight: config.learnedWeight,
          quiescence: 0,
          seed: seed === undefined ? null : seed,
        });
      } else {
        think = search.think(game, {
          depth: config.depth,
          timeMs: config.timeMs,
          temperature: config.temperature,
          blunder: config.blunder,
          learnedWeight: config.learnedWeight,
          quiescence: config.quiescence,
          seed: seed === undefined ? null : seed,
        });
        chosen = think.move;
      }
      if (!chosen) {
        postMessage({ id, type: "ok", payload: { gameOver: true } });
        return;
      }

      game.move({
        from: chosen.from,
        to: chosen.to,
        promotion: chosen.promotion,
      });
      const afterScore = search.quickScore(game, {
        depth: 2,
        learnedWeight: config.learnedWeight,
      });
      const engineScoreAfter =
        game.turn() === engineColour ? afterScore : -afterScore;
      const describe = rig.describePosition(game, config.learnedWeight);

      postMessage({
        id,
        type: "ok",
        payload: {
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
          level: config,
          degraded: rig.degraded,
        },
      });
    }
  } catch (err) {
    postMessage({
      id,
      type: "error",
      message: String((err && err.message) || err),
    });
  }
});
