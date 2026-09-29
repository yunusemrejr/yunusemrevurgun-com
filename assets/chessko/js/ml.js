// chessko's learned components that run in the browser.
//
//   * Evaluator        — the logistic model trained by the Python backend (see eval.js)
//   * book             — exact position lookup + k-nearest-neighbour fallback over the corpus
//   * Bandit           — online UCB1 that watches your move quality and suggests a level nudge
//
// Everything degrades gracefully: with no model/book/config available the rig reports
// `degraded: true` and the engine keeps playing with the classical evaluator.

import { Evaluator, bookDistance, bookVector, fetchJson } from "./eval.js";
import { makeRng } from "./search.js";
import { API, ASSETS } from "./config.js";

const STATIC_FALLBACKS = {
  model: `${ASSETS}/data/model.json`,
  book: `${ASSETS}/data/book.json`,
  config: `${ASSETS}/data/levels.json`,
};

/** Fetch from the backend API first, then from the static copy of the same file. */
async function fetchFirst(urls) {
  const errors = [];
  for (const url of urls) {
    try {
      return { data: await fetchJson(url), url };
    } catch (err) {
      errors.push(`${url}: ${err.message}`);
    }
  }
  throw new Error(errors.join(" | "));
}

export const BANDIT_ARMS = [-1, 0, 1]; // make it easier, keep, make it harder
const BANDIT_STORAGE_KEY = "chessko.bandit.v1";

/** Index of the "keep the current level" arm. */
export const BANDIT_KEEP = 1;

/**
 * Map "how well the player just played" (0..1) onto a *difficulty fit* reward.
 *
 * A bandit over difficulty must not reward raw quality: crushing an easy level is
 * high quality but a bad fit. Fit peaks when the player is stretched but keeping up
 * (target 0.6 of the engine's own line) and falls off towards both edges.
 */
export function fitReward(quality, target = 0.6, tolerance = 0.4) {
  return Math.max(0, Math.min(1, 1 - Math.abs(quality - target) / tolerance));
}

/**
 * How much one difficulty step is assumed to change the player's move quality.
 * This is the bandit's stretch model: stated here, used to turn one observation into
 * a reward estimate for every offset, so the app does not need to experiment on the
 * player to learn which direction to move.
 */
export const STRETCH_PER_STEP = 0.18;

export class Bandit {
  /**
   * Difficulty bandit.
   *
   * Every move you play gives a quality estimate; the stretch model maps that estimate
   * onto all three offsets at once (one step up ≈ STRETCH_PER_STEP lower quality), and
   * the resulting fit is folded into that arm's running average. UCB1's exploration term
   * keeps offsets with few observations in the running, estimates are smoothed towards a
   * neutral 0.5, and a change is suggested only when it clearly beats the offset in effect.
   */
  constructor({
    c = 0.25,
    prior = 0.5,
    priorWeight = 2,
    stretch = STRETCH_PER_STEP,
    persist = true,
    storageKey = BANDIT_STORAGE_KEY,
  } = {}) {
    this.c = c;
    this.prior = prior;
    this.priorWeight = priorWeight;
    this.stretch = stretch;
    this.persist = persist;
    this.storageKey = storageKey;
    this.counts = BANDIT_ARMS.map(() => 0);
    this.rewards = BANDIT_ARMS.map(() => 0);
    this.observed = 0; // real observations fed in (one per move), not per-arm pulls
    this.history = [];
    this.applied = 0; // offset currently in effect on the main thread
    this.load();
  }

  get observations() {
    return this.observed;
  }

  /** Total arm pulls (with the stretch model one observation pulls every arm). */
  get pulls() {
    return this.counts.reduce((a, b) => a + b, 0);
  }

  /** Smoothed reward estimate for one arm. */
  estimate(arm) {
    return (
      (this.rewards[arm] + this.prior * this.priorWeight) /
      (this.counts[arm] + this.priorWeight)
    );
  }

  /** UCB1 value for one arm. */
  value(arm) {
    return (
      this.estimate(arm) +
      this.c * Math.sqrt(Math.log(this.pulls + 1) / (this.counts[arm] + 1))
    );
  }

  /**
   * Which way should the level move? Returns an arm index, or null while there is
   * not enough evidence. It only asks for a change when a different arm is clearly
   * better than the one currently in effect.
   */
  choose() {
    if (this.observations < 3) return null;
    let bestArm = BANDIT_KEEP;
    let bestValue = -Infinity;
    for (let arm = 0; arm < BANDIT_ARMS.length; arm++) {
      const value = this.value(arm);
      if (value > bestValue) {
        bestValue = value;
        bestArm = arm;
      }
    }
    const keepArm =
      BANDIT_ARMS.indexOf(this.applied) >= 0
        ? BANDIT_ARMS.indexOf(this.applied)
        : BANDIT_KEEP;
    return bestValue - this.value(keepArm) < 0.08 ? keepArm : bestArm;
  }

  /** Arm index that corresponds to an offset. */
  armFor(offset) {
    const index = BANDIT_ARMS.indexOf(offset);
    return index >= 0 ? index : BANDIT_KEEP;
  }

  observe(arm, reward) {
    const index = Math.max(0, Math.min(BANDIT_ARMS.length - 1, arm));
    this.counts[index] += 1;
    this.rewards[index] += Math.max(0, Math.min(1, reward));
    this.observed += 1;
    this.history.push({
      arm: BANDIT_ARMS[index],
      reward: Number(reward.toFixed(3)),
      t: Date.now(),
    });
    if (this.history.length > 200) this.history = this.history.slice(-200);
    this.save();
  }

  /** Predicted fit of one offset, given a measured quality at another offset. */
  fitOf(quality, fromOffset, offset) {
    const predicted = Math.max(
      0,
      Math.min(1, quality - this.stretch * (offset - (fromOffset ?? 0))),
    );
    return fitReward(predicted);
  }

  /**
   * One move played at `appliedOffset` with quality `quality`: update every arm through
   * the stretch model. This is the online half of the difficulty adaptation.
   */
  observeQuality(quality, appliedOffset = 0) {
    this.applied = appliedOffset;
    for (let arm = 0; arm < BANDIT_ARMS.length; arm++) {
      this.rewards[arm] += this.fitOf(quality, appliedOffset, BANDIT_ARMS[arm]);
      this.counts[arm] += 1;
    }
    this.observed += 1;
    this.history.push({
      offset: appliedOffset,
      quality: Number(quality.toFixed(3)),
      t: Date.now(),
    });
    if (this.history.length > 200) this.history = this.history.slice(-200);
    this.save();
  }

  /** Remember which offset the app is actually playing at. */
  markApplied(offset) {
    this.applied = offset;
    this.save();
  }

  snapshot() {
    const best = this.choose();
    const keepArm = this.armFor(this.applied);
    return {
      observations: this.observations,
      pulls: this.pulls,
      applied: this.applied,
      stretch: this.stretch,
      arms: BANDIT_ARMS.map((offset, i) => ({
        offset,
        pulls: this.counts[i],
        meanReward: this.counts[i]
          ? Number((this.rewards[i] / this.counts[i]).toFixed(3))
          : null,
        estimate: Number(this.estimate(i).toFixed(3)),
        value: Number(this.value(i).toFixed(3)),
      })),
      suggestion: best === null || best === keepArm ? null : BANDIT_ARMS[best],
      keepArm,
      recent: this.history.slice(-8),
    };
  }

  load() {
    if (!this.persist) return;
    try {
      const raw = globalThis.localStorage?.getItem(this.storageKey);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (
        Array.isArray(parsed.counts) &&
        Array.isArray(parsed.rewards) &&
        parsed.counts.length === BANDIT_ARMS.length
      ) {
        this.counts = parsed.counts;
        this.rewards = parsed.rewards;
        this.observed = Number.isFinite(parsed.observed)
          ? parsed.observed
          : this.counts.reduce((a, b) => a + b, 0);
        this.history = Array.isArray(parsed.history)
          ? parsed.history.slice(-200)
          : [];
        this.applied = Number.isFinite(parsed.applied) ? parsed.applied : 0;
      }
    } catch {
      /* storage unavailable (private mode / file://) — start fresh */
    }
  }

  save() {
    if (!this.persist) return;
    try {
      globalThis.localStorage?.setItem(
        this.storageKey,
        JSON.stringify({
          counts: this.counts,
          rewards: this.rewards,
          observed: this.observed,
          applied: this.applied,
          history: this.history.slice(-60),
        }),
      );
    } catch {
      /* ignore */
    }
  }

  reset() {
    this.counts = BANDIT_ARMS.map(() => 0);
    this.rewards = BANDIT_ARMS.map(() => 0);
    this.observed = 0;
    this.history = [];
    this.applied = 0;
    this.save();
  }
}

export class MlRig {
  constructor({
    model = null,
    book = null,
    config = null,
    seed = 20260929,
    persist = true,
  } = {}) {
    this.evaluator = new Evaluator(model);
    this.book = book && Array.isArray(book.keys) ? book : null;
    this.bookIndex = new Map();
    if (this.book)
      this.book.keys.forEach((key, i) => this.bookIndex.set(key, i));
    this.config = config && Array.isArray(config.levels) ? config : null;
    this.engineConfig =
      config && config.engine
        ? config.engine
        : { ttEntries: 65536, mateScore: 100000 };
    this.algorithms = config && config.algorithms ? config.algorithms : [];
    this.bandit = new Bandit({ persist });
    this.rng = makeRng(seed);
    this.stats = { bookHits: 0, knnHits: 0, bookMisses: 0, movesPlayed: 0 };
    this.sources = { model: null, book: null, config: null };
  }

  static async load({ persist = true, seed = 20260929 } = {}) {
    const [model, book, config] = await Promise.allSettled([
      fetchFirst([`${API}/model`, STATIC_FALLBACKS.model]),
      fetchFirst([`${API}/book`, STATIC_FALLBACKS.book]),
      fetchFirst([`${API}/config`, STATIC_FALLBACKS.config]),
    ]);
    const rig = new MlRig({
      model: model.status === "fulfilled" ? model.value.data : null,
      book: book.status === "fulfilled" ? book.value.data : null,
      config: config.status === "fulfilled" ? config.value.data : null,
      persist,
      seed,
    });
    rig.sources = {
      model: model.status === "fulfilled" ? model.value.url : null,
      book: book.status === "fulfilled" ? book.value.url : null,
      config: config.status === "fulfilled" ? config.value.url : null,
    };
    rig.errors = [model, book, config]
      .filter((r) => r.status === "rejected")
      .map((r) => r.reason.message);
    return rig;
  }

  get degraded() {
    return !this.evaluator.trained || !this.book;
  }

  get levels() {
    return this.config && this.config.levels ? this.config.levels : [];
  }

  level(id) {
    return (
      this.levels.find((l) => l.id === id) ||
      this.levels[0] || {
        id: 1,
        name: "Jelly",
        depth: 2,
        timeMs: 300,
        temperature: 100,
        blunder: 0.1,
        learnedWeight: 0.5,
        bookPlies: 0,
        quiescence: 2,
      }
    );
  }

  evaluate(game, learnedWeight = 1) {
    return this.evaluator.evaluate(game.board(), game.turn(), learnedWeight);
  }

  describePosition(game, learnedWeight = 1) {
    return this.evaluator.describe(game.board(), game.turn(), learnedWeight);
  }

  fenKey(fen) {
    return fen.split(" ").slice(0, 4).join(" ");
  }

  /**
   * Opening book move for the current position.
   * Exact hash hit first, then a distance-weighted k-NN vote over the corpus vectors.
   * Only legal moves are ever returned.
   */
  bookMove(game, maxPlies) {
    if (!this.book || !maxPlies) return null;
    if (game.history().length >= maxPlies) return null;

    const legal = new Map(
      game.moves({ verbose: true }).map((move) => [move.san, move]),
    );
    const index = this.bookIndex.get(this.fenKey(game.fen()));
    if (index !== undefined) {
      const picked = this.weightedPick(
        this.book.moves[index].filter(([san]) => legal.has(san)),
      );
      if (picked) {
        this.stats.bookHits++;
        this.stats.movesPlayed++;
        return {
          move: legal.get(picked.san),
          source: "book",
          san: picked.san,
          count: picked.count,
          detail: "exact position from the training corpus",
        };
      }
    }

    const vectors = this.book.vectors;
    if (!Array.isArray(vectors) || !vectors.length) return null;
    const probe = bookVector(game.board(), game.turn());
    const neighbours = [];
    for (let i = 0; i < vectors.length; i++) {
      const distance = bookDistance(probe, vectors[i]);
      if (distance <= this.book.distance_threshold)
        neighbours.push({ index: i, distance });
    }
    if (!neighbours.length) {
      this.stats.bookMisses++;
      return null;
    }
    neighbours.sort((a, b) => a.distance - b.distance);
    const votes = new Map();
    for (const { index: i, distance } of neighbours.slice(
      0,
      this.book.k || 5,
    )) {
      const weight = 1 / (0.15 + distance);
      for (const [san, count] of this.book.moves[i]) {
        if (!legal.has(san)) continue;
        votes.set(san, (votes.get(san) || 0) + weight * count);
      }
    }
    if (!votes.size) return null;
    const picked = this.weightedPick(
      [...votes.entries()].map(([san, count]) => [san, count]),
    );
    if (!picked) return null;
    this.stats.knnHits++;
    this.stats.movesPlayed++;
    return {
      move: legal.get(picked.san),
      source: "knn-book",
      san: picked.san,
      distance: Number(neighbours[0].distance.toFixed(3)),
      neighbours: Math.min(neighbours.length, this.book.k || 5),
      detail: `${Math.min(neighbours.length, this.book.k || 5)} nearest corpus positions`,
    };
  }

  weightedPick(entries) {
    const usable = entries.filter(([, count]) => count > 0);
    if (!usable.length) return null;
    const total = usable.reduce((sum, [, count]) => sum + count, 0);
    let roll = this.rng() * total;
    for (const [san, count] of usable) {
      roll -= count;
      if (roll <= 0) return { san, count };
    }
    const [san, count] = usable[usable.length - 1];
    return { san, count };
  }

  /** Cheap depth-2 opinion of a position, engine point of view (centipawns). */
  quickScore(
    search,
    game,
    { depth = 2, learnedWeight = 0.6, timeMs = 120 } = {},
  ) {
    const previous = search.rng;
    const result = search.think(game, {
      depth,
      timeMs,
      temperature: 0,
      blunder: 0,
      learnedWeight,
      quiescence: 1,
    });
    search.rng = previous;
    return result.score;
  }

  snapshot() {
    return {
      degraded: this.degraded,
      errors: this.errors,
      sources: this.sources,
      model: {
        trained: this.evaluator.trained,
        trainedAt: this.evaluator.model
          ? this.evaluator.model.trained_at
          : null,
        labelKind: this.evaluator.model
          ? (this.evaluator.model.label_kind ?? "result")
          : null,
        temperature: this.evaluator.temperature,
        weights: this.evaluator.weights ? this.evaluator.weights.length : 0,
        valAccuracy: this.evaluator.model
          ? this.evaluator.model.val_accuracy
          : null,
        valLogLoss: this.evaluator.model
          ? this.evaluator.model.val_log_loss
          : null,
        baselineLogLoss: this.evaluator.model
          ? this.evaluator.model.baseline_log_loss
          : null,
        valMaeCp: this.evaluator.model
          ? (this.evaluator.model.val_mae_cp ?? null)
          : null,
        baselineMaeCp: this.evaluator.model
          ? (this.evaluator.model.baseline_mae_cp ?? null)
          : null,
        valSignAgreement: this.evaluator.model
          ? (this.evaluator.model.val_sign_agreement ?? null)
          : null,
        positions: this.evaluator.model ? this.evaluator.model.positions : null,
        games: this.evaluator.model ? this.evaluator.model.games : null,
        teacherDepth:
          this.evaluator.model && this.evaluator.model.hyperparameters
            ? null
            : null,
      },
      book: this.book
        ? {
            entries: this.book.entries,
            games: this.book.games,
            plies: this.book.plies,
            k: this.book.k,
            threshold: this.book.distance_threshold,
          }
        : null,
      stats: { ...this.stats },
      bandit: this.bandit.snapshot(),
      algorithms: this.algorithms,
    };
  }
}
