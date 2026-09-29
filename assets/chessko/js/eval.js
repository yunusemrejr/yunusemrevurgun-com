// Evaluation for chessko.
//
// Two evaluators, both returning centipawns from the SIDE-TO-MOVE point of view:
//   * classical()  — hand-tuned material + piece-square tables + mobility (always available)
//   * learned()    — logistic model trained offline by the Python backend (engine/train.py)
// The level config decides the blend, and the blend is what the search optimises.
//
// Performance note: the blend used to cost two full feature passes per leaf (one for the
// learned model, one for the hand-tuned terms), which roughly halved search speed. Both
// now share a single scanPosition() pass.
//
// extractFeatures() is a line-by-line mirror of engine/features.py; tests/js/features.test.mjs
// pins both implementations to tests/fixtures/features.json.

export const FEATURE_NAMES = [
  'bias', 'mat_p', 'mat_n', 'mat_b', 'mat_r', 'mat_q', 'center', 'activity',
  'shield', 'structure', 'passed', 'tempo', 'phase', 'mat_p_phase', 'mat_r_phase', 'mat_q_phase',
];

const PAWN = 1, KNIGHT = 2, BISHOP = 3, ROOK = 4, QUEEN = 5, KING = 6;
const CENTER = [0x33, 0x34, 0x43, 0x44]; // d4, e4, d5, e5
const KNIGHT_DELTAS = [[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]];
const KING_DELTAS = [[1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1]];
const BISHOP_DIRS = [[1, 1], [1, -1], [-1, 1], [-1, -1]];
const ROOK_DIRS = [[1, 0], [-1, 0], [0, 1], [0, -1]];
const START_NONPAWN = 2 * (2 * 3 + 2 * 3 + 2 * 5 + 9); // 62
const PIECE_VALUE = { [PAWN]: 1, [KNIGHT]: 3, [BISHOP]: 3, [ROOK]: 5, [QUEEN]: 9 };

// Lookup tables instead of array scans inside the hot loop
const CENTER_LOOKUP = new Uint8Array(128);
for (const sq of CENTER) CENTER_LOOKUP[sq] = 1;
const MATERIAL_SLOTS = 6; // index by piece kind (1..5 used)

/** Squares attacked by the piece on `idx` (pseudo-legal: includes the first blocker). */
export function attacksFrom(board, idx) {
  const piece = board[idx];
  if (!piece) return [];
  const kind = piece < 0 ? -piece : piece;
  const file = idx & 7;
  const rank = idx >> 4;
  const out = [];
  if (kind === PAWN) {
    const step = piece > 0 ? 1 : -1;
    for (const df of [-1, 1]) {
      const f = file + df, r = rank + step;
      if (f >= 0 && f < 8 && r >= 0 && r < 8) out.push(r * 16 + f);
    }
  } else if (kind === KNIGHT || kind === KING) {
    for (const [df, dr] of (kind === KNIGHT ? KNIGHT_DELTAS : KING_DELTAS)) {
      const f = file + df, r = rank + dr;
      if (f >= 0 && f < 8 && r >= 0 && r < 8) out.push(r * 16 + f);
    }
  } else {
    const dirs = kind === BISHOP ? BISHOP_DIRS : kind === ROOK ? ROOK_DIRS : BISHOP_DIRS.concat(ROOK_DIRS);
    for (const [df, dr] of dirs) {
      let f = file + df, r = rank + dr;
      while (f >= 0 && f < 8 && r >= 0 && r < 8) {
        const sq = r * 16 + f;
        out.push(sq);
        if (board[sq]) break;
        f += df; r += dr;
      }
    }
  }
  return out;
}

/**
 * One pass over the board for everything both evaluators need:
 * material, mobility, centre control, king squares, pawn-file statistics, PST terms.
 * Allocation-free apart from the small fixed arrays.
 */
function scanPosition(board, turn) {
  const persp = turn === 'w' ? 1 : -1;
  const material = new Int32Array(MATERIAL_SLOTS);
  const mobility = [0, 0, 0]; // index 1 = white, 2 = black
  const center = [0, 0, 0];
  const kingIdx = [-1, -1];
  const bishops = [0, 0];
  const pawnCountWhite = new Int32Array(8);
  const pawnCountBlack = new Int32Array(8);
  const pawnMinWhite = new Int32Array(8).fill(8);
  const pawnMaxWhite = new Int32Array(8).fill(-1);
  const pawnMinBlack = new Int32Array(8).fill(8);
  const pawnMaxBlack = new Int32Array(8).fill(-1);
  let nonpawnUnits = 0;
  let pst = 0;
  let mobilityCp = 0;

  for (let idx = 0; idx < 128; idx++) {
    if (idx & 0x88) continue;
    const piece = board[idx];
    if (piece === 0) continue;
    const color = piece > 0 ? 1 : -1;
    const kind = piece > 0 ? piece : -piece;
    const white = color > 0;
    const sign = color === persp ? 1 : -1;

    if (kind === KING) {
      kingIdx[white ? 0 : 1] = idx;
      continue;
    }

    // material (units) — pawns count too, they are the largest part of the feature vector
    if (kind !== PAWN) {
      nonpawnUnits += PIECE_VALUE[kind];
    }
    material[kind] += color;

    // pawn bookkeeping
    if (kind === PAWN) {
      const file = idx & 7;
      const rank = idx >> 4;
      if (white) {
        pawnCountWhite[file]++;
        if (rank < pawnMinWhite[file]) pawnMinWhite[file] = rank;
        if (rank > pawnMaxWhite[file]) pawnMaxWhite[file] = rank;
      } else {
        pawnCountBlack[file]++;
        if (rank < pawnMinBlack[file]) pawnMinBlack[file] = rank;
        if (rank > pawnMaxBlack[file]) pawnMaxBlack[file] = rank;
      }
    }

    // mobility + centre control (one attack pass shared by both evaluators)
    const targets = attacksFrom(board, idx);
    const count = targets.length;
    mobility[white ? 1 : 2] += count;
    mobilityCp += sign * count * 3;
    for (let i = 0; i < count; i++) {
      if (CENTER_LOOKUP[targets[i]]) center[white ? 1 : 2]++;
    }

    // piece-square table term (classical evaluator)
    if (kind === QUEEN || kind === ROOK || kind === BISHOP || kind === KNIGHT || kind === PAWN) {
      const file = idx & 7;
      const rank = idx >> 4;
      const table = PST[kind];
      const tableIdx = white ? (7 - rank) * 8 + file : rank * 8 + file;
      pst += sign * table[tableIdx];
      if (kind === BISHOP) bishops[white ? 1 : 0]++;
    }
  }

  // pawn structure: doubled + isolated per colour, and passed pawns
  const doubledIsolated = [0, 0];
  const passed = [0, 0];
  for (let f = 0; f < 8; f++) {
    const whiteHere = pawnCountWhite[f];
    const blackHere = pawnCountBlack[f];
    doubledIsolated[0] += Math.max(0, whiteHere - 1);
    doubledIsolated[1] += Math.max(0, blackHere - 1);
    const whiteIsolated = whiteHere > 0 && !(f > 0 && pawnCountWhite[f - 1]) && !(f < 7 && pawnCountWhite[f + 1]);
    const blackIsolated = blackHere > 0 && !(f > 0 && pawnCountBlack[f - 1]) && !(f < 7 && pawnCountBlack[f + 1]);
    if (whiteIsolated) doubledIsolated[0] += whiteHere;
    if (blackIsolated) doubledIsolated[1] += blackHere;
  }
  for (let idx = 0; idx < 128; idx++) {
    if (idx & 0x88) continue;
    const piece = board[idx];
    if (piece !== PAWN && piece !== -PAWN) continue;
    const file = idx & 7;
    const rank = idx >> 4;
    const white = piece > 0;
    let blocked = false;
    if (white) {
      for (let nf = file - 1; nf <= file + 1 && !blocked; nf++) {
        if (nf < 0 || nf > 7) continue;
        if (pawnMaxBlack[nf] > rank) blocked = true;
      }
      if (!blocked) passed[0]++;
    } else {
      for (let nf = file - 1; nf <= file + 1 && !blocked; nf++) {
        if (nf < 0 || nf > 7) continue;
        if (pawnMinWhite[nf] < rank) blocked = true;
      }
      if (!blocked) passed[1]++;
    }
  }

  // king shields
  const shield = [0, 0];
  for (let side = 0; side < 2; side++) {
    const ksq = kingIdx[side];
    if (ksq < 0) continue;
    const white = side === 0;
    const kingFile = ksq & 7;
    const kingRank = ksq >> 4;
    const step = white ? 1 : -1;
    const own = white ? pawnCountWhite : pawnCountBlack;
    for (let df = -1; df <= 1; df++) {
      const f = kingFile + df;
      if (f < 0 || f > 7) continue;
      for (const dr of [1, 2, 3]) {
        const r = kingRank + step * dr;
        if (r >= 0 && r < 8 && board[r * 16 + f] === PAWN * (white ? 1 : -1)) shield[side]++;
      }
    }
  }

  return {
    persp, material, mobility, center, kingIdx, bishops, nonpawnUnits, pst, mobilityCp,
    doubledIsolated, passed, shield, pawnCountWhite, pawnCountBlack,
    phase: Math.min(1, nonpawnUnits / START_NONPAWN),
  };
}

function featuresFrom(scan) {
  const { persp } = scan;
  const mine = persp === 1 ? 1 : 2; // array index of the side to move
  const theirs = persp === 1 ? 2 : 1;
  const pawn = scan.material[PAWN] * persp / 4;
  const knight = scan.material[KNIGHT] * persp / 3;
  const bishop = scan.material[BISHOP] * persp / 3;
  const rook = scan.material[ROOK] * persp / 5;
  const queen = scan.material[QUEEN] * persp / 9;
  const centerF = (scan.center[mine] - scan.center[theirs]) / 4;
  const activityF = (scan.mobility[mine] - scan.mobility[theirs]) / 10;
  const shieldF = (scan.shield[mine - 1] - scan.shield[theirs - 1]) / 3;
  const structureF = -(scan.doubledIsolated[mine - 1] - scan.doubledIsolated[theirs - 1]) / 4;
  const passedF = (scan.passed[mine - 1] - scan.passed[theirs - 1]) / 2;
  return [
    1, pawn, knight, bishop, rook, queen, centerF, activityF, shieldF, structureF, passedF,
    1, scan.phase, pawn * scan.phase, rook * scan.phase, queen * scan.phase,
  ];
}

/** Feature vector from the side-to-move point of view (mirror of engine/features.py). */
export function extractFeatures(board, turn) {
  return featuresFrom(scanPosition(board, turn));
}

// Similarity vector for the k-NN opening book, always from WHITE's point of view
// (mirror of engine/features.py book_vector()).
export const BOOK_VECTOR_NAMES = ['mat_p', 'mat_n', 'mat_b', 'mat_r', 'mat_q', 'center', 'phase'];
export const BOOK_VECTOR_WEIGHTS = [1, 1, 1, 1, 1, 0.5, 0.5];

export function bookVector(board, turn) {
  const feats = extractFeatures(board, turn);
  const persp = turn === 'w' ? 1 : -1;
  return BOOK_VECTOR_NAMES.map((name) => {
    const value = feats[FEATURE_NAMES.indexOf(name)];
    return name === 'phase' ? value : value * persp;
  });
}

export function bookDistance(a, b) {
  let total = 0;
  for (let i = 0; i < a.length; i++) total += BOOK_VECTOR_WEIGHTS[i] * (a[i] - b[i]) ** 2;
  return Math.sqrt(total);
}

export function winProb(features, weights, temperature = 1) {
  const scale = temperature > 0 ? temperature : 1;
  let z = 0;
  for (let i = 0; i < features.length; i++) z += features[i] * weights[i];
  z /= scale;
  if (z >= 0) {
    const ez = Math.exp(-z);
    return 1 / (1 + ez);
  }
  const ez = Math.exp(z);
  return ez / (1 + ez);
}

export function winProbToCp(prob, scale = 400, limit = 900) {
  const p = Math.min(Math.max(prob, 1e-4), 1 - 1e-4);
  const cp = scale * Math.log(p / (1 - p));
  return Math.max(-limit, Math.min(limit, cp));
}

// Piece-square tables (simplified evaluation function). Written visually from rank 8 down to
// rank 1, indexed with (7 - rank) * 8 + file so they line up with white's home rank.
const PST_PAWN = [
  0, 0, 0, 0, 0, 0, 0, 0,
  50, 50, 50, 50, 50, 50, 50, 50,
  10, 10, 20, 30, 30, 20, 10, 10,
  5, 5, 10, 25, 25, 10, 5, 5,
  0, 0, 0, 20, 20, 0, 0, 0,
  5, -5, -10, 0, 0, -10, -5, 5,
  5, 10, 10, -20, -20, 10, 10, 5,
  0, 0, 0, 0, 0, 0, 0, 0,
];
const PST_KNIGHT = [
  -50, -40, -30, -30, -30, -30, -40, -50,
  -40, -20, 0, 0, 0, 0, -20, -40,
  -30, 0, 10, 15, 15, 10, 0, -30,
  -30, 5, 15, 20, 20, 15, 5, -30,
  -30, 0, 15, 20, 20, 15, 0, -30,
  -30, 5, 10, 15, 15, 10, 5, -30,
  -40, -20, 0, 5, 5, 0, -20, -40,
  -50, -40, -30, -30, -30, -30, -40, -50,
];
const PST_BISHOP = [
  -20, -10, -10, -10, -10, -10, -10, -20,
  -10, 0, 0, 0, 0, 0, 0, -10,
  -10, 0, 5, 10, 10, 5, 0, -10,
  -10, 5, 5, 10, 10, 5, 5, -10,
  -10, 0, 10, 10, 10, 10, 0, -10,
  -10, 10, 10, 10, 10, 10, 10, -10,
  -10, 5, 0, 0, 0, 0, 5, -10,
  -20, -10, -10, -10, -10, -10, -10, -20,
];
const PST_ROOK = [
  0, 0, 0, 0, 0, 0, 0, 0,
  5, 10, 10, 10, 10, 10, 10, 5,
  -5, 0, 0, 0, 0, 0, 0, -5,
  -5, 0, 0, 0, 0, 0, 0, -5,
  -5, 0, 0, 0, 0, 0, 0, -5,
  -5, 0, 0, 0, 0, 0, 0, -5,
  -5, 0, 0, 0, 0, 0, 0, -5,
  0, 0, 0, 5, 5, 0, 0, 0,
];
const PST_QUEEN = [
  -20, -10, -10, -5, -5, -10, -10, -20,
  -10, 0, 0, 0, 0, 0, 0, -10,
  -10, 0, 5, 5, 5, 5, 0, -10,
  -5, 0, 5, 5, 5, 5, 0, -5,
  0, 0, 5, 5, 5, 5, 0, -5,
  -10, 5, 5, 5, 5, 5, 0, -10,
  -10, 0, 5, 0, 0, 0, 0, -10,
  -20, -10, -10, -5, -5, -10, -10, -20,
];
const PST_KING_MID = [
  -30, -40, -40, -50, -50, -40, -40, -30,
  -30, -40, -40, -50, -50, -40, -40, -30,
  -30, -40, -40, -50, -50, -40, -40, -30,
  -30, -40, -40, -50, -50, -40, -40, -30,
  -20, -30, -30, -40, -40, -30, -30, -20,
  -10, -20, -20, -20, -20, -20, -20, -10,
  20, 20, 0, 0, 0, 0, 20, 20,
  20, 30, 10, 0, 0, 10, 30, 20,
];
const PST_KING_END = [
  -50, -40, -30, -20, -20, -30, -40, -50,
  -30, -20, -10, 0, 0, -10, -20, -30,
  -30, -10, 20, 30, 30, 20, -10, -30,
  -30, -10, 30, 40, 40, 30, -10, -30,
  -30, -10, 30, 40, 40, 30, -10, -30,
  -30, -10, 20, 30, 30, 20, -10, -30,
  -30, -30, 0, 0, 0, 0, -30, -30,
  -50, -30, -30, -30, -30, -30, -30, -50,
];

const PST = { [PAWN]: PST_PAWN, [KNIGHT]: PST_KNIGHT, [BISHOP]: PST_BISHOP, [ROOK]: PST_ROOK, [QUEEN]: PST_QUEEN };
const MATERIAL_CP = { [PAWN]: 100, [KNIGHT]: 320, [BISHOP]: 330, [ROOK]: 500, [QUEEN]: 900 };

function classicalFrom(scan, phaseHint = null) {
  const persp = scan.persp;
  let materialCp = 0;
  for (const kind of [PAWN, KNIGHT, BISHOP, ROOK, QUEEN]) {
    materialCp += scan.material[kind] * persp * MATERIAL_CP[kind];
  }
  const phase = phaseHint === null ? scan.phase : phaseHint;

  // kings interpolate between middlegame and endgame tables by phase
  let kingPst = 0;
  for (let side = 0; side < 2; side++) {
    const sq = scan.kingIdx[side];
    if (sq < 0) continue;
    const white = side === 0;
    const file = sq & 7;
    const rank = sq >> 4;
    const tableIdx = white ? (7 - rank) * 8 + file : rank * 8 + file;
    const value = PST_KING_END[tableIdx] + (PST_KING_MID[tableIdx] - PST_KING_END[tableIdx]) * phase;
    kingPst += (white ? 1 : -1) === persp ? value : -value;
  }

  const bishopPair = (scan.bishops[persp === 1 ? 0 : 1] >= 2 ? 30 : 0) - (scan.bishops[persp === 1 ? 1 : 0] >= 2 ? 30 : 0);
  return Math.round(materialCp + scan.pst + scan.mobilityCp + bishopPair + 10);
}

/** Hand-tuned evaluation, centipawns from the side-to-move point of view. */
export function classical(board, turn, phaseHint = null) {
  return classicalFrom(scanPosition(board, turn), phaseHint);
}

/** Fetch JSON with the tags' no-store policy; throws on a non-OK response. */
export async function fetchJson(url) {
  const res = await fetch(url, { cache: 'no-store' });
  if (!res.ok) throw new Error(`${url} -> HTTP ${res.status}`);
  return res.json();
}

/** Loads web/data/model.json and turns it into centipawns. */
export class Evaluator {
  constructor(model = null) {
    this.model = model;
    this.weights = model && Array.isArray(model.weights) ? model.weights : null;
    this.cpScale = (model && model.cp_scale) || 400;
    this.cpLimit = (model && model.cp_limit) || 900;
    // calibration fitted on the validation split by the backend trainer:
    // `temperature` scales the logit, `neutralOffsetCp` moves the zero point so that a
    // balanced position does not read as "white is +1.6".
    this.temperature = model && model.temperature > 0 ? model.temperature : 1;
    this.neutralOffsetCp = model && Number.isFinite(model.neutral_offset_cp) ? model.neutral_offset_cp : 0;
    // Guard the contract: a model whose feature order drifted must not be used blindly.
    if (this.weights && Array.isArray(model.feature_names)) {
      const same = model.feature_names.length === FEATURE_NAMES.length
        && model.feature_names.every((n, i) => n === FEATURE_NAMES[i]);
      if (!same) { this.weights = null; this.contractMismatch = true; }
    }
  }

  static async load(url) {
    try {
      return new Evaluator(await fetchJson(url));
    } catch (err) {
      // graceful degrade: the engine keeps working with the classical evaluator only
      const evaluator = new Evaluator(null);
      evaluator.loadError = String(err && err.message ? err.message : err);
      return evaluator;
    }
  }

  get trained() { return !!this.weights; }

  /** Learned evaluation in centipawns (side-to-move POV); 0 when no model is loaded. */
  learnedFromScan(scan) {
    if (!this.weights) return 0;
    return Math.round(this.cpFromFeatures(featuresFrom(scan)));
  }

  cpFromFeatures(features) {
    const raw = winProbToCp(winProb(features, this.weights, this.temperature), this.cpScale, this.cpLimit + Math.abs(this.neutralOffsetCp));
    return raw - this.neutralOffsetCp;
  }

  learned(board, turn) {
    if (!this.weights) return 0;
    return Math.round(this.cpFromFeatures(extractFeatures(board, turn)));
  }

  /** Blended evaluation: learnedWeight 0 = classical only, 1 = learned only. One scan. */
  evaluate(board, turn, learnedWeight = 1) {
    if (!this.weights || learnedWeight <= 0) return classical(board, turn);
    const scan = scanPosition(board, turn);
    const classic = classicalFrom(scan);
    if (learnedWeight >= 1) return Math.round(this.cpFromFeatures(featuresFrom(scan)));
    const learned = this.cpFromFeatures(featuresFrom(scan));
    return Math.round(classic * (1 - learnedWeight) + learned * learnedWeight);
  }

  /** Diagnostics used by the UI "brain goo" panel. */
  describe(board, turn, learnedWeight = 1) {
    const scan = scanPosition(board, turn);
    const feats = featuresFrom(scan);
    const p = this.weights ? winProb(feats, this.weights, this.temperature) : 0.5;
    return {
      tuned: !!this.weights,
      winProb: p,
      temperature: this.temperature,
      neutralOffsetCp: this.neutralOffsetCp,
      learnedCp: this.weights ? this.cpFromFeatures(feats) : null,
      classicalCp: classicalFrom(scan),
      blendedCp: this.evaluate(board, turn, learnedWeight),
      topFeatures: feats
        .map((v, i) => ({ name: FEATURE_NAMES[i], value: v, weight: this.weights ? this.weights[i] : 0, contribution: this.weights ? v * this.weights[i] : 0 }))
        .filter((f) => f.name !== 'bias')
        .sort((a, b) => Math.abs(b.contribution) - Math.abs(a.contribution))
        .slice(0, 4),
    };
  }
}
