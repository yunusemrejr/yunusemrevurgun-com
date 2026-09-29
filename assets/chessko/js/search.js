// Chess search for chessko: iterative-deepening alpha-beta with quiescence.
//
// The search itself is classical (transposition table, MVV-LVA, killers, history
// reinforcement). What makes it "learned" is the evaluation it optimises and how
// the final move is picked:
//   * evaluate() delegates to Evaluator (logistic model trained in Python, blended with PST)
//   * pickMove() can sample with a temperature, which is what the level ladder uses
//   * think() reports its whole decision so the UI can show which component acted
//
// Performance: when chess.js offers the packed fast path (packedMoves/packedInfo/…)
// the search never builds SAN strings, history objects or board copies, and keeps a
// Zobrist key incrementally. It falls back to the public move API otherwise, so it
// still works against a plain chess.js. All randomness goes through an injectable
// PRNG so arena matches and corpus generation are reproducible.

import { classical } from "./eval.js";

export const MATE = 100000;
export const MATE_BOUND = MATE - 1000;

/** Deterministic PRNG (mulberry32) — reproducible self-play and arena runs. */
export function makeRng(seed = 1) {
  let a = seed >>> 0;
  return function next() {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const LETTER_CODE = { p: 1, n: 2, b: 3, r: 4, q: 5, k: 6 };
const PIECE_VALUE = { 1: 100, 2: 320, 3: 330, 4: 500, 5: 900, 6: 20000 };

function squareIndex(name) {
  return (Number(name[1]) - 1) * 16 + (name.charCodeAt(0) - 97);
}

/** 32-bit position hash for engines without the packed path (FNV-1a over the board). */
export function hashPosition(board, turn) {
  let h = 0x811c9dc5 ^ (turn === "w" ? 0x9e3779b9 : 0x85ebca6b);
  for (let idx = 0; idx < 128; idx++) {
    if (idx & 0x88) continue;
    const piece = board[idx];
    if (piece === 0) continue;
    h ^= (idx + 1) * 31 + (piece + 7);
    h = Math.imul(h, 0x01000193);
  }
  return h >>> 0;
}

// Zobrist tables, built from the same PRNG so the values are stable across runs.
// Two 32-bit halves are combined into one Number: exact integers below 2^53, so the
// transposition table gets a ~53-bit key without BigInt or string keys.
const ZOBRIST = (() => {
  const rng = makeRng(0x5eed1234);
  const makeHalf = (length) => {
    const table = new Int32Array(length);
    for (let i = 0; i < length; i++) table[i] = (rng() * 0x100000000) | 0;
    return table;
  };
  const piecesHi = makeHalf(128 * 16);
  const piecesLo = makeHalf(128 * 16);
  const castlingHi = makeHalf(16);
  const castlingLo = makeHalf(16);
  const epHi = makeHalf(129);
  const epLo = makeHalf(129);
  const sideHi = makeHalf(1)[0];
  const sideLo = makeHalf(1)[0];
  return {
    piecesHi,
    piecesLo,
    castlingHi,
    castlingLo,
    epHi,
    epLo,
    sideHi,
    sideLo,
  };
})();

const TWO_21 = 2097152;

/** Combine two 32-bit halves into one exact 53-bit integer (< 2^53). */
function combine(hi, lo) {
  return (hi >>> 0) * TWO_21 + ((lo >>> 11) >>> 0);
}

export class Search {
  constructor({
    evaluator = null,
    ttEntries = 65536,
    rng = makeRng(1),
    quiescenceMaxDepth = 6,
    usePacked = true,
  } = {}) {
    this.evaluator = evaluator;
    this.ttEntries = ttEntries;
    this.rng = rng;
    this.quiescenceMaxDepth = quiescenceMaxDepth;
    this.usePacked = usePacked;
    this.tt = new Map();
    this.killers = [];
    this.history = new Map();
    this.path = []; // position keys along the current search path (repetition detection)
    this.nodes = 0;
    this.deadline = Infinity;
    this.aborted = false;
    this.packedStats = 0;
    this.publicStats = 0;
  }

  /** Evaluation in centipawns, side-to-move POV. */
  evaluate(board, turn, learnedWeight) {
    if (this.evaluator)
      return this.evaluator.evaluate(board, turn, learnedWeight);
    return classical(board, turn);
  }

  reset() {
    this.tt.clear();
    this.killers = [];
    this.history.clear();
  }

  get usesPackedPath() {
    return this.usePacked;
  }

  /**
   * Move list as [{m, info}] — packed integers via the engine fast path when available.
   * info: {from, to, promotion, piece, captured, isCapture, isEnPassant, isDouble, isCastle}
   */
  listMoves(game) {
    if (this.usePacked && typeof game.packedMoves === "function") {
      const packed = game.packedMoves();
      const out = new Array(packed.length);
      for (let i = 0; i < packed.length; i++) {
        const m = packed[i];
        out[i] = { m, info: game.packedInfo(m) };
      }
      this.packedStats++;
      return out;
    }
    this.publicStats++;
    const verbose = game.moves({ verbose: true });
    return verbose.map((move) => {
      const sign = move.color === "w" ? 1 : -1;
      return {
        m: move,
        info: {
          from: squareIndex(move.from),
          to: squareIndex(move.to),
          promotion: move.promotion ? LETTER_CODE[move.promotion] : 0,
          piece: sign * LETTER_CODE[move.piece],
          captured: move.captured ? -sign * LETTER_CODE[move.captured] : 0,
          isCapture: !!move.captured,
          isEnPassant:
            typeof move.flags === "string" && move.flags.includes("e"),
          isDouble: typeof move.flags === "string" && move.flags.includes("b"),
          isCastle:
            typeof move.flags === "string" &&
            (move.flags.includes("k") || move.flags.includes("q")),
        },
      };
    });
  }

  apply(game, entry) {
    if (typeof entry.m === "number") return game.packedApply(entry.m);
    game.move({
      from: entry.m.from,
      to: entry.m.to,
      promotion: entry.m.promotion,
    });
    return null;
  }

  revert(game, entry, snapshot) {
    if (typeof entry.m === "number") game.packedRevert(entry.m, snapshot);
    else game.undo();
  }

  /** Zobrist delta for one move; only valid on the packed path. */
  nextKey(game, entry, stateBefore) {
    const { info } = entry;
    const stateAfter = game.packedState();
    let hi = this.keyHi;
    let lo = this.keyLo;
    hi ^= ZOBRIST.sideHi;
    lo ^= ZOBRIST.sideLo;
    let idx = info.from * 16 + (info.piece + 8);
    hi ^= ZOBRIST.piecesHi[idx];
    lo ^= ZOBRIST.piecesLo[idx];
    if (info.isCapture) {
      const capSq = info.isEnPassant
        ? info.to + (info.piece > 0 ? -16 : 16)
        : info.to;
      idx = capSq * 16 + (info.captured + 8);
      hi ^= ZOBRIST.piecesHi[idx];
      lo ^= ZOBRIST.piecesLo[idx];
    }
    const placed = info.promotion
      ? info.piece > 0
        ? info.promotion
        : -info.promotion
      : info.piece;
    idx = info.to * 16 + (placed + 8);
    hi ^= ZOBRIST.piecesHi[idx];
    lo ^= ZOBRIST.piecesLo[idx];
    if (info.isCastle) {
      const rook = info.piece > 0 ? 4 : -4;
      const rookFrom = info.to > info.from ? info.to + 1 : info.to - 2;
      const rookTo = info.to > info.from ? info.to - 1 : info.to + 1;
      idx = rookFrom * 16 + (rook + 8);
      hi ^= ZOBRIST.piecesHi[idx];
      lo ^= ZOBRIST.piecesLo[idx];
      idx = rookTo * 16 + (rook + 8);
      hi ^= ZOBRIST.piecesHi[idx];
      lo ^= ZOBRIST.piecesLo[idx];
    }
    const castleBefore = stateBefore.castling & 15;
    const castleAfter = stateAfter.castling & 15;
    hi ^= ZOBRIST.castlingHi[castleBefore] ^ ZOBRIST.castlingHi[castleAfter];
    lo ^= ZOBRIST.castlingLo[castleBefore] ^ ZOBRIST.castlingLo[castleAfter];
    hi ^= ZOBRIST.epHi[stateBefore.ep + 1] ^ ZOBRIST.epHi[stateAfter.ep + 1];
    lo ^= ZOBRIST.epLo[stateBefore.ep + 1] ^ ZOBRIST.epLo[stateAfter.ep + 1];
    this.keyHi = hi;
    this.keyLo = lo;
    this.key = combine(hi, lo);
    return this.key;
  }

  /**
   * Think about the current position.
   * config: {depth, timeMs, temperature, blunder, learnedWeight, quiescence, seed}
   */
  think(game, config = {}) {
    const {
      depth = 3,
      timeMs = 500,
      temperature = 0,
      blunder = 0,
      learnedWeight = 1,
      quiescence = 3,
      seed = null,
    } = config;

    this.nodes = 0;
    this.aborted = false;
    this.deadline = Date.now() + Math.max(20, timeMs);
    this.quiescenceMaxDepth = Math.max(0, quiescence);
    if (seed !== null) this.rng = makeRng(seed);

    const rootMoves = this.listMoves(game);
    const started = Date.now();
    if (rootMoves.length === 0) {
      return {
        move: null,
        san: null,
        score: 0,
        depth: 0,
        nodes: 0,
        ms: 0,
        nps: 0,
        considered: [],
        aborted: false,
        packed: typeof rootMoves[0]?.m === "number",
      };
    }

    this.key =
      typeof rootMoves[0].m === "number"
        ? this.setPositionKey(game)
        : hashPosition(game.board(), game.turn());
    this.orderMoves(rootMoves, 0, null);

    let scored = rootMoves.map((entry) => ({ entry, score: -Infinity }));
    let bestScored = scored[0];
    let completedDepth = 0;

    for (let depthNow = 1; depthNow <= depth; depthNow++) {
      const iteration = this.searchRoot(
        game,
        rootMoves,
        depthNow,
        learnedWeight,
        bestScored.entry,
      );
      // A time-aborted iteration is *incomplete* and unordered: using it would pick whatever
      // the move ordering put first (captures) instead of the best move found so far, which
      // made the deepest level play worse than the shallow ones. Keep the last complete
      // iteration instead; only depth 1 falls back to the partial list, sorted.
      if (this.aborted && depthNow > 1) break;
      iteration.sort((a, b) => b.score - a.score);
      scored = iteration;
      bestScored = scored[0];
      completedDepth = depthNow;
      if (Math.abs(bestScored.score) > MATE_BOUND) break; // mate found; deeper search cannot improve it
      if (Date.now() > this.deadline) break;
    }

    const chosen = this.pickMove(scored, temperature, blunder);
    const chosenMove = this.toPublicMove(game, chosen.entry);
    const ms = Date.now() - started;
    return {
      move: chosenMove,
      san: chosenMove ? chosenMove.san : null,
      score: Math.round(bestScored.score),
      playedScore: Math.round(chosen.score || 0),
      depth: completedDepth,
      nodes: this.nodes,
      ms,
      nps: ms > 0 ? Math.round(this.nodes / (ms / 1000)) : this.nodes,
      considered: scored
        .slice(0, 5)
        .map((item) => ({
          san: this.sanOf(game, item.entry),
          score: Math.round(item.score),
        })),
      sampled: chosen.entry !== bestScored.entry,
      aborted: this.aborted,
      packed: typeof rootMoves[0].m === "number",
    };
  }

  /** Full-board Zobrist key, computed without touching the search's own key state. */
  zobristKey(game) {
    const state = game.packedState();
    let hi = state.turn === "w" ? 0 : ZOBRIST.sideHi;
    let lo = state.turn === "w" ? 0 : ZOBRIST.sideLo;
    for (let sq = 0; sq < 128; sq++) {
      if (sq & 0x88) continue;
      const piece = game.packedPieceAt(sq);
      if (piece === 0) continue;
      const idx = sq * 16 + (piece + 8);
      hi ^= ZOBRIST.piecesHi[idx];
      lo ^= ZOBRIST.piecesLo[idx];
    }
    hi ^= ZOBRIST.castlingHi[state.castling & 15];
    lo ^= ZOBRIST.castlingLo[state.castling & 15];
    hi ^= ZOBRIST.epHi[state.ep + 1];
    lo ^= ZOBRIST.epLo[state.ep + 1];
    return combine(hi, lo);
  }

  /** (Re)anchor the incremental key on the position on the board. */
  setPositionKey(game) {
    const key = this.zobristKey(game);
    const state = game.packedState();
    this.keyHi = 0;
    this.keyLo = 0;
    // recompute the halves once so nextKey() can stay incremental
    const halves = this.zobristHalves(game, state);
    this.keyHi = halves.hi;
    this.keyLo = halves.lo;
    this.key = key;
    return key;
  }

  zobristHalves(game, state) {
    let hi = state.turn === "w" ? 0 : ZOBRIST.sideHi;
    let lo = state.turn === "w" ? 0 : ZOBRIST.sideLo;
    for (let sq = 0; sq < 128; sq++) {
      if (sq & 0x88) continue;
      const piece = game.packedPieceAt(sq);
      if (piece === 0) continue;
      const idx = sq * 16 + (piece + 8);
      hi ^= ZOBRIST.piecesHi[idx];
      lo ^= ZOBRIST.piecesLo[idx];
    }
    hi ^= ZOBRIST.castlingHi[state.castling & 15];
    lo ^= ZOBRIST.castlingLo[state.castling & 15];
    hi ^= ZOBRIST.epHi[state.ep + 1];
    lo ^= ZOBRIST.epLo[state.ep + 1];
    return { hi, lo };
  }

  /** Occurrences of the current key along the search path (2 means a twofold). */
  pathRepeats(key) {
    let count = 0;
    for (let i = 0; i < this.path.length; i++)
      if (this.path[i] === key) count++;
    return count;
  }

  /** SAN (or the object's san) for one entry — only used for display/considered list. */
  sanOf(game, entry) {
    if (typeof entry.m !== "number") return entry.m.san;
    return entry.san || (entry.san = game.packedSan(entry.m));
  }

  toPublicMove(game, entry) {
    if (typeof entry.m !== "number") return entry.m;
    return entry.publicMove || (entry.publicMove = game.packedMove(entry.m));
  }

  /** Temperature sampling + deliberate blunders — this is what creates the difficulty ladder. */
  pickMove(scored, temperature, blunder) {
    if (!scored.length) return { entry: null, score: 0 };
    if (scored.length === 1) return scored[0];
    const best = scored[0];
    if (blunder > 0 && this.rng() < blunder) {
      const candidates = scored.filter(
        (item) => best.score - item.score > 25 && best.score - item.score < 900,
      );
      const pool = candidates.length ? candidates : scored.slice(1, 3);
      if (pool.length)
        return pool[Math.floor(this.rng() * pool.length) % pool.length];
    }
    if (temperature > 0) {
      const weights = scored.map((item) =>
        Math.exp(Math.max(-30, (item.score - best.score) / temperature)),
      );
      const total = weights.reduce((a, b) => a + b, 0);
      let roll = this.rng() * total;
      for (let i = 0; i < scored.length; i++) {
        roll -= weights[i];
        if (roll <= 0) return scored[i];
      }
    }
    return best;
  }

  /**
   * Key of the current node after applying `entry`.
   * Packed path: incremental Zobrist. Fallback path: a fresh board hash per node
   * (the public API cannot give us a delta, and reusing one key for every child would
   * make the transposition table return scores from unrelated positions).
   */
  childKeyFor(game, entry, stateBefore) {
    if (stateBefore) return this.nextKey(game, entry, stateBefore);
    this.key = hashPosition(game.board(), game.turn());
    this.keyHi = 0;
    this.keyLo = 0;
    return this.key;
  }

  searchRoot(game, rootMoves, depth, learnedWeight, previousBest) {
    const ordered = previousBest
      ? [previousBest, ...rootMoves.filter((entry) => entry !== previousBest)]
      : rootMoves;
    const results = [];
    let alpha = -Infinity;
    const parentKey = this.key;
    const parentKeyHi = this.keyHi;
    const parentKeyLo = this.keyLo;
    for (const entry of ordered) {
      const stateBefore =
        typeof entry.m === "number" ? game.packedState() : null;
      const snapshot = this.apply(game, entry);
      const childKey = this.childKeyFor(game, entry, stateBefore);
      this.path.push(childKey);
      const score = -this.negamax(
        game,
        depth - 1,
        -Infinity,
        -alpha,
        learnedWeight,
        1,
        childKey,
      );
      this.path.pop();
      this.revert(game, entry, snapshot);
      this.key = parentKey;
      this.keyHi = parentKeyHi;
      this.keyLo = parentKeyLo;
      results.push({ entry, score });
      if (score > alpha) alpha = score;
      if (this.aborted) break;
    }
    return results;
  }

  negamax(game, depth, alpha, beta, learnedWeight, ply, key) {
    if ((this.nodes & 511) === 0 && Date.now() > this.deadline) {
      this.aborted = true;
      return 0;
    }
    this.nodes++;

    if (game.isFiftyMoves()) return 0;
    // a position repeated twice on the search path (threefold counting the game) is a draw;
    // the engine's own history is checked too at the shallow plies where it matters most
    if (ply <= 3 && game.isThreefoldRepetition()) return 0;
    if (this.pathRepeats(key) >= 2) return 0;

    const inCheck = game.packedInCheck ? game.packedInCheck() : game.inCheck();

    if (depth <= 0) {
      return inCheck
        ? this.quiescence(
            game,
            this.quiescenceMaxDepth,
            alpha,
            beta,
            learnedWeight,
            ply,
            key,
          )
        : this.evaluate(game.board(), game.turn(), learnedWeight);
    }

    let entry = null;
    if (depth >= 2 && key) {
      entry = this.tt.get(key);
      if (entry && entry.depth >= depth) {
        const score = this.fromTT(entry.score, ply);
        if (entry.flag === "exact") return score;
        if (entry.flag === "lower" && score >= beta) return score;
        if (entry.flag === "upper" && score <= alpha) return score;
      }
    }

    const moves = this.listMoves(game);
    if (moves.length === 0) {
      return inCheck ? -MATE + ply : 0; // checkmate or stalemate
    }
    this.orderMoves(moves, ply, entry ? entry.best : null);

    let best = -Infinity;
    let bestMove = null;
    const originalAlpha = alpha;
    const parentKey = this.key;
    const parentKeyHi = this.keyHi;
    const parentKeyLo = this.keyLo;
    for (const move of moves) {
      const isQuiet = !move.info.isCapture && !move.info.promotion;
      const stateBefore =
        typeof move.m === "number" ? game.packedState() : null;
      const snapshot = this.apply(game, move);
      const childKey = this.childKeyFor(game, move, stateBefore);
      this.path.push(childKey);
      let score;
      if (isQuiet && depth <= 2 && !inCheck) {
        // late move reduction for quiet moves at shallow depth, verified at full depth
        score = -this.negamax(
          game,
          depth - 2,
          -beta,
          -beta + 1,
          learnedWeight,
          ply + 1,
          childKey,
        );
        if (score > alpha)
          score = -this.negamax(
            game,
            depth - 1,
            -beta,
            -alpha,
            learnedWeight,
            ply + 1,
            childKey,
          );
      } else {
        score = -this.negamax(
          game,
          depth - 1,
          -beta,
          -alpha,
          learnedWeight,
          ply + 1,
          childKey,
        );
      }
      this.path.pop();
      this.revert(game, move, snapshot);
      this.key = parentKey;
      this.keyHi = parentKeyHi;
      this.keyLo = parentKeyLo;

      if (this.aborted) return best === -Infinity ? 0 : best;
      if (score > best) {
        best = score;
        bestMove = move;
      }
      if (score > alpha) alpha = score;
      if (alpha >= beta) {
        if (isQuiet) {
          this.rememberKiller(ply, move.info);
          this.bumpHistory(move.info, depth);
        }
        break; // beta cutoff
      }
    }

    if (key && !this.aborted) {
      const flag =
        best <= originalAlpha ? "upper" : best >= beta ? "lower" : "exact";
      if (this.tt.size >= this.ttEntries) this.tt.clear();
      this.tt.set(key, {
        depth,
        score: this.toTT(best, ply),
        flag,
        best: bestMove.info,
      });
    }
    return best;
  }

  /** Captures-only quiescence with a static-eval stand-pat. */
  quiescence(game, depth, alpha, beta, learnedWeight, ply, key) {
    // the deadline matters here too: without this check a deep capture sequence can run
    // for seconds past the level's time budget (measured: 20 s/move on level 5)
    if ((this.nodes & 511) === 0 && Date.now() > this.deadline) {
      this.aborted = true;
      return this.evaluate(game.board(), game.turn(), learnedWeight);
    }
    this.nodes++;
    const standPat = this.evaluate(game.board(), game.turn(), learnedWeight);
    if (depth <= 0) return standPat;
    if (standPat >= beta) return beta;
    if (standPat > alpha) alpha = standPat;

    const captures = this.listMoves(game).filter(
      (move) => move.info.isCapture || move.info.promotion,
    );
    captures.sort(
      (a, b) => this.captureScore(b.info) - this.captureScore(a.info),
    );
    const parentKey = this.key;
    for (const move of captures) {
      const stateBefore =
        typeof move.m === "number" ? game.packedState() : null;
      const snapshot = this.apply(game, move);
      const score = -this.quiescence(
        game,
        depth - 1,
        -beta,
        -alpha,
        learnedWeight,
        ply + 1,
        this.childKeyFor(game, move, stateBefore),
      );
      this.revert(game, move, snapshot);
      this.key = parentKey;
      if (this.aborted) return alpha;
      if (score >= beta) return beta;
      if (score > alpha) alpha = score;
    }
    return alpha;
  }

  orderMoves(moves, ply, ttMove) {
    const killers = this.killers[ply] || [];
    for (const move of moves) {
      const { info } = move;
      let score = 0;
      if (
        ttMove &&
        info.from === ttMove.from &&
        info.to === ttMove.to &&
        info.promotion === ttMove.promotion
      )
        score += 100000;
      if (info.isCapture) score += 10000 + this.captureScore(info);
      if (info.promotion) score += 8000 + PIECE_VALUE[info.promotion];
      if (killers.some((k) => k.from === info.from && k.to === info.to))
        score += 5000;
      score += this.history.get(info.from * 128 + info.to) || 0;
      move.orderScore = score;
    }
    moves.sort((a, b) => b.orderScore - a.orderScore);
    return moves;
  }

  captureScore(info) {
    const victim = info.captured ? PIECE_VALUE[Math.abs(info.captured)] : 100;
    const attacker = PIECE_VALUE[Math.abs(info.piece)] || 100;
    return victim - attacker / 10;
  }

  rememberKiller(ply, info) {
    const list = this.killers[ply] || (this.killers[ply] = []);
    if (!list.some((k) => k.from === info.from && k.to === info.to))
      list.unshift({ from: info.from, to: info.to });
    if (list.length > 2) list.pop();
  }

  bumpHistory(info, depth) {
    const key = info.from * 128 + info.to;
    this.history.set(key, (this.history.get(key) || 0) + depth * depth);
  }

  toTT(score, ply) {
    return score > MATE_BOUND
      ? score + ply
      : score < -MATE_BOUND
        ? score - ply
        : score;
  }

  fromTT(score, ply) {
    return score > MATE_BOUND
      ? score - ply
      : score < -MATE_BOUND
        ? score + ply
        : score;
  }

  /** Cheap depth-limited opinion of a position (used by the bandit reward signal). */
  quickScore(game, { depth = 2, learnedWeight = 0.6, timeMs = 120 } = {}) {
    const rng = this.rng;
    const result = this.think(game, {
      depth,
      timeMs,
      temperature: 0,
      blunder: 0,
      learnedWeight,
      quiescence: 1,
    });
    this.rng = rng;
    return result.score;
  }

  stats() {
    return {
      nodes: this.nodes,
      ttSize: this.tt.size,
      packedLists: this.packedStats,
      publicLists: this.publicStats,
    };
  }
}
