// web/js/chess.js — dependency-free chess rules engine (0x88 board).
//
// ESM, runs in browsers and Node, zero dependencies, no eval().
// Board layout: index = rank * 16 + file (a1 = 0x00, h1 = 0x07, a8 = 0x70).
// A square is off-board iff (sq & 0x88) !== 0.
// Piece codes: white P,N,B,R,Q,K = +1..+6, black = -1..-6, empty = 0.
// Internal moves are packed ints: from[0:7] to[7:14] captured+7[14:18]
// promotion[18:21] flag[21:24]. Public API converts to SAN / Move objects.

export const START_FEN =
  "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";

const EMPTY = 0;
const PAWN = 1;
const KNIGHT = 2;
const BISHOP = 3;
const ROOK = 4;
const QUEEN = 5;
const KING = 6;
const WHITE = 0;
const BLACK = 1;

// packed-move flag values
const FLAG_NORMAL = 0;
const FLAG_DOUBLE = 1; // pawn double push
const FLAG_EP = 2; // en-passant capture
const FLAG_CASTLE_K = 3;
const FLAG_CASTLE_Q = 4;

const KNIGHT_D = [-18, -33, -31, -14, 14, 31, 33, 18];
const KING_D = [-17, -16, -15, -1, 1, 15, 16, 17];
const BISHOP_D = [-17, -15, 15, 17];
const ROOK_D = [-16, -1, 1, 16];

const CASTLE_WK = 1;
const CASTLE_WQ = 2;
const CASTLE_BK = 4;
const CASTLE_BQ = 8;

// squares whose occupation changes castling rights: a1/h1/a8/h8 clear them
const CASTLE_MASK = (() => {
  const m = new Array(128).fill(15);
  m[0] = 13; // a1: drop white queenside
  m[7] = 14; // h1: drop white kingside
  m[112] = 7; // a8: drop black queenside
  m[119] = 11; // h8: drop black kingside
  return m;
})();

const FILES = "abcdefgh";
const CODE_LETTER = { 1: "p", 2: "n", 3: "b", 4: "r", 5: "q", 6: "k" };
const SAN_LETTER = { 2: "N", 3: "B", 4: "R", 5: "Q", 6: "K" };
const PROMO_CODE = { n: 2, b: 3, r: 4, q: 5 };

function sqName(sq) {
  return FILES[sq & 7] + ((sq >> 4) + 1);
}

function parseSquare(sq) {
  if (typeof sq !== "string" || sq.length !== 2) return -1;
  const f = FILES.indexOf(sq[0]);
  const r = sq.charCodeAt(1) - 49; // '1' -> 0
  if (f < 0 || r < 0 || r > 7) return -1;
  return r * 16 + f;
}

// Is `sq` attacked by side `byWhite`? Board `b` is a 128-array.
function attacked(b, sq, byWhite) {
  // pawn attacks
  if (byWhite) {
    if (!((sq - 15) & 0x88) && b[sq - 15] === PAWN) return true;
    if (!((sq - 17) & 0x88) && b[sq - 17] === PAWN) return true;
  } else {
    if (!((sq + 15) & 0x88) && b[sq + 15] === -PAWN) return true;
    if (!((sq + 17) & 0x88) && b[sq + 17] === -PAWN) return true;
  }
  // knight jumps
  const n = byWhite ? KNIGHT : -KNIGHT;
  for (let i = 0; i < 8; i++) {
    const t = sq + KNIGHT_D[i];
    if (!(t & 0x88 || t < 0) && b[t] === n) return true;
  }
  // king steps
  const k = byWhite ? KING : -KING;
  for (let i = 0; i < 8; i++) {
    const t = sq + KING_D[i];
    if (!(t & 0x88 || t < 0) && b[t] === k) return true;
  }
  // bishops / queens on diagonals
  for (let i = 0; i < 4; i++) {
    let t = sq + BISHOP_D[i];
    while (!(t & 0x88) && t >= 0) {
      const p = b[t];
      if (p !== EMPTY) {
        if (byWhite ? p > 0 : p < 0) {
          const a = p < 0 ? -p : p;
          if (a === BISHOP || a === QUEEN) return true;
        }
        break;
      }
      t += BISHOP_D[i];
    }
  }
  // rooks / queens on files and ranks
  for (let i = 0; i < 4; i++) {
    let t = sq + ROOK_D[i];
    while (!(t & 0x88) && t >= 0) {
      const p = b[t];
      if (p !== EMPTY) {
        if (byWhite ? p > 0 : p < 0) {
          const a = p < 0 ? -p : p;
          if (a === ROOK || a === QUEEN) return true;
        }
        break;
      }
      t += ROOK_D[i];
    }
  }
  return false;
}

export class Chess {
  constructor(fen = START_FEN) {
    this._b = new Array(128).fill(EMPTY);
    this._hist = [];
    this._keys = [];
    this._rep = new Map();
    this.load(fen);
  }

  // ---- FEN ----------------------------------------------------------

  load(fen) {
    if (typeof fen !== "string") throw new Error("invalid FEN: not a string");
    const parts = fen.trim().split(/\s+/);
    if (parts.length !== 6) throw new Error("invalid FEN: need 6 fields");
    const [placement, side, castling, ep, half, full] = parts;

    const b = new Array(128).fill(EMPTY);
    const rows = placement.split("/");
    if (rows.length !== 8) throw new Error("invalid FEN: need 8 ranks");
    for (let r = 0; r < 8; r++) {
      const row = rows[7 - r]; // FEN starts at rank 8
      let f = 0;
      for (const ch of row) {
        if (ch >= "1" && ch <= "8") {
          f += ch.charCodeAt(0) - 48;
        } else {
          const code = "pnbrqk".indexOf(ch.toLowerCase());
          if (code < 0 || f > 7)
            throw new Error(`invalid FEN: bad piece '${ch}'`);
          b[r * 16 + f] = ch === ch.toLowerCase() ? -(code + 1) : code + 1;
          f++;
        }
      }
      if (f !== 8) throw new Error("invalid FEN: rank does not sum to 8");
    }

    if (side !== "w" && side !== "b")
      throw new Error("invalid FEN: side to move");
    let rights = 0;
    if (castling !== "-") {
      if (!/^[KQkq]+$/.test(castling))
        throw new Error("invalid FEN: castling field");
      if (castling.includes("K")) rights |= CASTLE_WK;
      if (castling.includes("Q")) rights |= CASTLE_WQ;
      if (castling.includes("k")) rights |= CASTLE_BK;
      if (castling.includes("q")) rights |= CASTLE_BQ;
    }
    let epSq = -1;
    if (ep !== "-") {
      epSq = parseSquare(ep);
      if (epSq < 0) throw new Error("invalid FEN: en-passant square");
    }
    const halfmove = Number(half);
    const fullmove = Number(full);
    if (!Number.isInteger(halfmove) || halfmove < 0)
      throw new Error("invalid FEN: halfmove clock");
    if (!Number.isInteger(fullmove) || fullmove < 1)
      throw new Error("invalid FEN: fullmove number");

    this._b = b;
    this._turn = side === "w" ? WHITE : BLACK;
    this._castling = rights;
    this._ep = epSq;
    this._half = halfmove;
    this._full = fullmove;
    this._scanKings();
    this._hist = [];
    this._keys = [this._key()];
    this._rep = new Map([[this._keys[0], 1]]);
  }

  fen() {
    let out = "";
    for (let r = 7; r >= 0; r--) {
      let empty = 0;
      for (let f = 0; f < 8; f++) {
        const p = this._b[r * 16 + f];
        if (p === EMPTY) {
          empty++;
        } else {
          if (empty > 0) {
            out += empty;
            empty = 0;
          }
          const ch = CODE_LETTER[p < 0 ? -p : p];
          out += p > 0 ? ch.toUpperCase() : ch;
        }
      }
      if (empty > 0) out += empty;
      if (r > 0) out += "/";
    }
    out += this._turn === WHITE ? " w " : " b ";
    let c = "";
    if (this._castling & CASTLE_WK) c += "K";
    if (this._castling & CASTLE_WQ) c += "Q";
    if (this._castling & CASTLE_BK) c += "k";
    if (this._castling & CASTLE_BQ) c += "q";
    out += (c === "" ? "-" : c) + " ";
    out += (this._ep < 0 ? "-" : sqName(this._ep)) + " ";
    out += this._half + " " + this._full;
    return out;
  }

  turn() {
    return this._turn === WHITE ? "w" : "b";
  }

  board() {
    return Int8Array.from(this._b);
  }

  // ---- square helpers -------------------------------------------------

  get(square) {
    const sq = parseSquare(square);
    if (sq < 0) return null;
    const p = this._b[sq];
    if (p === EMPTY) return null;
    const ch = CODE_LETTER[p < 0 ? -p : p];
    return p > 0 ? ch.toUpperCase() : ch;
  }

  put(square, piece) {
    const sq = parseSquare(square);
    if (sq < 0) throw new Error(`invalid square: ${square}`);
    if (piece === "" || piece == null) {
      this._b[sq] = EMPTY;
    } else {
      const code = "pnbrqk".indexOf(String(piece).toLowerCase());
      if (code < 0) throw new Error(`invalid piece: ${piece}`);
      this._b[sq] = piece === piece.toLowerCase() ? -(code + 1) : code + 1;
    }
    this._scanKings();
    // put/remove are setup helpers: re-anchor repetition tracking to the new position
    this._keys = [this._key()];
    this._rep = new Map([[this._keys[0], 1]]);
  }

  remove(square) {
    this.put(square, null);
  }

  squareColor(square) {
    const sq = parseSquare(square);
    if (sq < 0) return null;
    return ((sq & 7) + (sq >> 4)) % 2 === 0 ? "dark" : "light";
  }

  isAttacked(square, by) {
    const sq = parseSquare(square);
    if (sq < 0) return false;
    return attacked(this._b, sq, by === "w");
  }

  // ---- move generation (pseudo-legal, never captures a king) ----------

  _gen(onlyCaptures) {
    const b = this._b;
    const white = this._turn === WHITE;
    const moves = [];
    const push = (from, to, flag, promo) => {
      moves.push(from | (to << 7) | (promo << 18) | (flag << 21));
    };
    for (let sq = 0; sq < 128; sq++) {
      if (sq & 0x88) continue;
      const p = b[sq];
      if (p === EMPTY || (white ? p < 0 : p > 0)) continue;
      const piece = p < 0 ? -p : p;
      const rank = sq >> 4;

      if (piece === PAWN) {
        const dir = white ? 16 : -16;
        const startRank = white ? 1 : 6;
        const promoRank = white ? 7 : 0;
        const one = sq + dir;
        if (!(one & 0x88) && b[one] === EMPTY) {
          if (!onlyCaptures) {
            if (one >> 4 === promoRank) {
              push(sq, one, FLAG_NORMAL, QUEEN);
              push(sq, one, FLAG_NORMAL, ROOK);
              push(sq, one, FLAG_NORMAL, BISHOP);
              push(sq, one, FLAG_NORMAL, KNIGHT);
            } else {
              push(sq, one, FLAG_NORMAL, 0);
              const two = sq + dir * 2;
              if (rank === startRank && b[two] === EMPTY)
                push(sq, two, FLAG_DOUBLE, 0);
            }
          } else if (one >> 4 === promoRank) {
            // quiet promotions are captures-only relevant: they gain material
            push(sq, one, FLAG_NORMAL, QUEEN);
            push(sq, one, FLAG_NORMAL, ROOK);
            push(sq, one, FLAG_NORMAL, BISHOP);
            push(sq, one, FLAG_NORMAL, KNIGHT);
          }
        }
        for (let k = 0; k < 2; k++) {
          const t = sq + dir + (k === 0 ? -1 : 1);
          if (t & 0x88) continue;
          const target = b[t];
          if (target !== EMPTY && (white ? target < 0 : target > 0)) {
            const tp = target < 0 ? -target : target;
            if (tp === KING) continue; // the king is never captured
            if (t >> 4 === promoRank) {
              push(sq, t, FLAG_NORMAL, QUEEN);
              push(sq, t, FLAG_NORMAL, ROOK);
              push(sq, t, FLAG_NORMAL, BISHOP);
              push(sq, t, FLAG_NORMAL, KNIGHT);
            } else {
              push(sq, t, FLAG_NORMAL, 0);
            }
          } else if (t === this._ep) {
            push(sq, t, FLAG_EP, 0);
          }
        }
      } else if (piece === KNIGHT || piece === KING) {
        const steps = piece === KNIGHT ? KNIGHT_D : KING_D;
        for (let i = 0; i < 8; i++) {
          const t = sq + steps[i];
          if (t & 0x88) continue;
          const target = b[t];
          if (target === EMPTY) {
            if (!onlyCaptures) push(sq, t, FLAG_NORMAL, 0);
          } else if (white ? target < 0 : target > 0) {
            const tp = target < 0 ? -target : target;
            if (tp !== KING) push(sq, t, FLAG_NORMAL, 0);
          }
        }
        if (piece === KING && !onlyCaptures) this._genCastles(sq, white, push);
      } else {
        const dirs =
          piece === BISHOP ? BISHOP_D : piece === ROOK ? ROOK_D : KING_D;
        for (let i = 0; i < dirs.length; i++) {
          let t = sq + dirs[i];
          while (!(t & 0x88)) {
            const target = b[t];
            if (target === EMPTY) {
              if (!onlyCaptures) push(sq, t, FLAG_NORMAL, 0);
            } else {
              if (white ? target < 0 : target > 0) {
                const tp = target < 0 ? -target : target;
                if (tp !== KING) push(sq, t, FLAG_NORMAL, 0);
              }
              break;
            }
            t += dirs[i];
          }
        }
      }
    }
    return moves;
  }

  _genCastles(kingSq, white, push) {
    const b = this._b;
    const home = white ? 4 : 116; // e1 / e8
    if (kingSq !== home) return;
    if (white) {
      if (this._castling & CASTLE_WK) {
        if (b[5] === EMPTY && b[6] === EMPTY) {
          if (
            !attacked(b, 4, false) &&
            !attacked(b, 5, false) &&
            !attacked(b, 6, false)
          ) {
            push(4, 6, FLAG_CASTLE_K, 0);
          }
        }
      }
      if (this._castling & CASTLE_WQ) {
        if (b[1] === EMPTY && b[2] === EMPTY && b[3] === EMPTY) {
          if (
            !attacked(b, 4, false) &&
            !attacked(b, 3, false) &&
            !attacked(b, 2, false)
          ) {
            push(4, 2, FLAG_CASTLE_Q, 0);
          }
        }
      }
    } else {
      if (this._castling & CASTLE_BK) {
        if (b[117] === EMPTY && b[118] === EMPTY) {
          if (
            !attacked(b, 116, true) &&
            !attacked(b, 117, true) &&
            !attacked(b, 118, true)
          ) {
            push(116, 118, FLAG_CASTLE_K, 0);
          }
        }
      }
      if (this._castling & CASTLE_BQ) {
        if (b[113] === EMPTY && b[114] === EMPTY && b[115] === EMPTY) {
          if (
            !attacked(b, 116, true) &&
            !attacked(b, 115, true) &&
            !attacked(b, 114, true)
          ) {
            push(116, 114, FLAG_CASTLE_Q, 0);
          }
        }
      }
    }
  }

  // ---- make / unmake ----------------------------------------------------

  // Applies a packed move, returns a snapshot for _revert.
  // NOTE: the captured piece is read from the board here, not packed in `m`.
  _apply(m) {
    const from = m & 127;
    const to = (m >> 7) & 127;
    const prom = (m >> 18) & 7;
    const flag = (m >> 21) & 7;
    const piece = this._b[from];
    const white = this._turn === WHITE;
    let captured;
    if (flag === FLAG_EP) {
      captured = white ? -PAWN : PAWN;
      this._b[to + (white ? -16 : 16)] = EMPTY;
    } else {
      captured = this._b[to];
    }
    const snap = {
      captured,
      castling: this._castling,
      ep: this._ep,
      half: this._half,
      wk: this._wk,
      bk: this._bk,
    };
    this._half =
      Math.abs(piece) === PAWN || captured !== EMPTY ? 0 : this._half + 1;
    if (!white) this._full++;
    this._b[to] = prom === 0 ? piece : white ? prom : -prom;
    this._b[from] = EMPTY;
    if (flag === FLAG_CASTLE_K) {
      if (white) {
        this._b[5] = this._b[7];
        this._b[7] = EMPTY;
      } else {
        this._b[117] = this._b[119];
        this._b[119] = EMPTY;
      }
    } else if (flag === FLAG_CASTLE_Q) {
      if (white) {
        this._b[3] = this._b[0];
        this._b[0] = EMPTY;
      } else {
        this._b[115] = this._b[112];
        this._b[112] = EMPTY;
      }
    }
    if (Math.abs(piece) === KING) {
      // a king move forfeits both of that side's castling rights (perft does not cover this,
      // the integration tests do: without it an illegal O-O can be played after a king walk)
      this._castling &= white ? CASTLE_BK | CASTLE_BQ : CASTLE_WK | CASTLE_WQ;
      if (white) this._wk = to;
      else this._bk = to;
    }
    this._castling &= CASTLE_MASK[from] & CASTLE_MASK[to];
    this._ep = flag === FLAG_DOUBLE ? (from + to) >> 1 : -1;
    this._turn ^= 1;
    return snap;
  }

  _revert(m, snap) {
    const from = m & 127;
    const to = (m >> 7) & 127;
    const prom = (m >> 18) & 7;
    const flag = (m >> 21) & 7;
    this._turn ^= 1;
    const white = this._turn === WHITE;
    if (!white) this._full--;
    if (flag === FLAG_CASTLE_K) {
      if (white) {
        this._b[7] = this._b[5];
        this._b[5] = EMPTY;
      } else {
        this._b[119] = this._b[117];
        this._b[117] = EMPTY;
      }
    } else if (flag === FLAG_CASTLE_Q) {
      if (white) {
        this._b[0] = this._b[3];
        this._b[3] = EMPTY;
      } else {
        this._b[112] = this._b[115];
        this._b[115] = EMPTY;
      }
    }
    this._b[from] = prom === 0 ? this._b[to] : white ? PAWN : -PAWN;
    if (flag === FLAG_EP) {
      this._b[to] = EMPTY;
      this._b[to + (white ? -16 : 16)] = snap.captured;
    } else {
      this._b[to] = snap.captured;
    }
    this._castling = snap.castling;
    this._ep = snap.ep;
    this._half = snap.half;
    this._wk = snap.wk;
    this._bk = snap.bk;
  }

  _scanKings() {
    this._wk = -1;
    this._bk = -1;
    for (let sq = 0; sq < 128; sq++) {
      if (sq & 0x88) continue;
      if (this._b[sq] === KING) this._wk = sq;
      else if (this._b[sq] === -KING) this._bk = sq;
    }
  }

  // All legal packed moves.
  _legalPacked() {
    const moves = this._gen(false);
    const out = [];
    for (let i = 0; i < moves.length; i++) {
      const m = moves[i];
      const snap = this._apply(m);
      const moverWhite = this._turn === BLACK; // turn already flipped
      const ksq = moverWhite ? this._wk : this._bk;
      const ok = ksq < 0 || !attacked(this._b, ksq, !moverWhite);
      this._revert(m, snap);
      if (ok) out.push(m);
    }
    return out;
  }

  _ownKingAttacked() {
    const white = this._turn === WHITE;
    const ksq = white ? this._wk : this._bk;
    if (ksq < 0) return false;
    return attacked(this._b, ksq, !white);
  }

  // True when the side to move has at least one legal reply.
  _hasLegalReply() {
    const moves = this._gen(false);
    for (let i = 0; i < moves.length; i++) {
      const m = moves[i];
      const snap = this._apply(m);
      const moverWhite = this._turn === BLACK;
      const ksq = moverWhite ? this._wk : this._bk;
      const ok = ksq < 0 || !attacked(this._b, ksq, !moverWhite);
      this._revert(m, snap);
      if (ok) return true;
    }
    return false;
  }

  // ---- fast path for the search -------------------------------------------
  // The search walks hundreds of thousands of nodes; SAN strings, history objects
  // and board copies are all avoidable there. These accessors expose the packed
  // encoding and the state fields a Zobrist key needs, while keeping the bit
  // layout private to this file. Everything below is read-only unless stated.

  /** Legal moves in the packed encoding (see packedInfo to decode one). */
  packedMoves() {
    return this._legalPacked();
  }

  /** Decode one packed move. Call it BEFORE applying the move. */
  packedInfo(m) {
    const from = m & 127;
    const to = (m >> 7) & 127;
    const promotion = (m >> 18) & 7;
    const flag = (m >> 21) & 7;
    const isEnPassant = flag === FLAG_EP;
    const captured = isEnPassant
      ? this._turn === WHITE
        ? -PAWN
        : PAWN
      : this._b[to];
    return {
      from,
      to,
      promotion,
      piece: this._b[from],
      captured,
      isCapture: captured !== EMPTY,
      isEnPassant,
      isDouble: flag === FLAG_DOUBLE,
      isCastle: flag === FLAG_CASTLE_K || flag === FLAG_CASTLE_Q,
    };
  }

  /** Cheap state snapshot (for incremental keys in the search). */
  packedState() {
    return {
      turn: this._turn === WHITE ? "w" : "b",
      castling: this._castling,
      ep: this._ep,
      half: this._half,
    };
  }

  /** Apply a packed move; returns the snapshot needed by packedRevert. */
  packedApply(m) {
    return this._apply(m);
  }

  /** Undo a packed move applied with packedApply. */
  packedRevert(m, snap) {
    this._revert(m, snap);
  }

  /** Does the side to move stand in check right now? */
  packedInCheck() {
    return this._ownKingAttacked();
  }

  /** Raw piece code on a 0x88 square (0 when empty). */
  packedPieceAt(sq) {
    return this._b[sq];
  }

  /** SAN for one packed move (only worth it for the move actually being played). */
  packedSan(m) {
    return this._san(m, this._legalPacked());
  }

  /** Public move object for one packed move. Call it BEFORE applying the move. */
  packedMove(m) {
    return this._toMoveObject(m, this.packedSan(m));
  }

  // ---- SAN ---------------------------------------------------------------

  _san(m, legalList) {
    const from = m & 127;
    const to = (m >> 7) & 127;
    const prom = (m >> 18) & 7;
    const flag = (m >> 21) & 7;
    const isCapture = this._b[to] !== EMPTY || flag === FLAG_EP;
    let san;
    if (flag === FLAG_CASTLE_K) {
      san = "O-O";
    } else if (flag === FLAG_CASTLE_Q) {
      san = "O-O-O";
    } else {
      const piece = this._b[from] < 0 ? -this._b[from] : this._b[from];
      if (piece === PAWN) {
        san = (isCapture ? FILES[from & 7] + "x" : "") + sqName(to);
        if (prom !== 0) san += "=" + SAN_LETTER[prom];
      } else {
        san = SAN_LETTER[piece];
        // disambiguation against other same-type pieces reaching `to`
        let others = 0;
        let sameFile = false;
        let sameRank = false;
        for (let i = 0; i < legalList.length; i++) {
          const o = legalList[i];
          if (o === m) continue;
          if ((o & 127) === from) continue;
          if (((o >> 7) & 127) !== to) continue;
          const op = this._b[o & 127];
          const opAbs = op < 0 ? -op : op;
          if (opAbs !== piece) continue;
          others++;
          if ((o & 127 & 7) === (from & 7)) sameFile = true;
          if ((o & 127) >> 4 === from >> 4) sameRank = true;
        }
        if (others > 0) {
          if (!sameFile) san += FILES[from & 7];
          else if (sameRank) san += sqName(from);
          else san += String((from >> 4) + 1);
        }
        if (isCapture) san += "x";
        san += sqName(to);
      }
    }
    const snap = this._apply(m);
    // after the move, is the side to move (the victim) in check?
    const victimWhite = this._turn === WHITE;
    const vksq = victimWhite ? this._wk : this._bk;
    if (vksq >= 0 && attacked(this._b, vksq, !victimWhite)) {
      san += this._hasLegalReply() ? "+" : "#";
    }
    this._revert(m, snap);
    return san;
  }

  // ---- public move API ------------------------------------------------------

  moves(opts = {}) {
    const { square, verbose = false, legal = true } = opts;
    let from = -1;
    if (square !== undefined) {
      from = parseSquare(square);
      if (from < 0) return [];
    }
    const packed = legal ? this._legalPacked() : this._gen(false);
    const list = from < 0 ? packed : packed.filter((m) => (m & 127) === from);
    if (!verbose) return list.map((m) => this._san(m, packed));
    return list.map((m) => this._toMoveObject(m, this._san(m, packed)));
  }

  // Call only with the move not yet applied: the victim is read from the board.
  _toMoveObject(m, san) {
    const from = m & 127;
    const to = (m >> 7) & 127;
    const prom = (m >> 18) & 7;
    const flag = (m >> 21) & 7;
    const captured =
      flag === FLAG_EP ? (this._turn === WHITE ? -PAWN : PAWN) : this._b[to];
    const mover = this._turn === WHITE ? "w" : "b";
    let flags;
    if (flag === FLAG_DOUBLE) flags = "b";
    else if (flag === FLAG_EP) flags = "e";
    else if (flag === FLAG_CASTLE_K) flags = "k";
    else if (flag === FLAG_CASTLE_Q) flags = "q";
    else flags = captured === EMPTY ? "n" : "c";
    if (prom !== 0) flags += "p";
    const obj = {
      color: mover,
      from: sqName(from),
      to: sqName(to),
      piece: CODE_LETTER[this._b[from] < 0 ? -this._b[from] : this._b[from]],
      flags,
      san,
      lan: sqName(from) + sqName(to) + (prom === 0 ? "" : CODE_LETTER[prom]),
    };
    if (captured !== EMPTY)
      obj.captured = CODE_LETTER[captured < 0 ? -captured : captured];
    if (prom !== 0) obj.promotion = CODE_LETTER[prom];
    return obj;
  }

  move(m) {
    let from = -1;
    let to = -1;
    let promo = 0;
    let label = "";
    if (typeof m === "string") {
      label = m;
      const norm = m.replace(/[+#!?]+$/, "").replace(/0/g, "O");
      const legal = this._legalPacked();
      for (const cand of legal) {
        if (
          this._san(cand, legal) === norm ||
          this._san(cand, legal).replace(/[+#]$/, "") === norm
        ) {
          return this._doPublicMove(cand);
        }
      }
      const lan = /^([a-h][1-8])([a-h][1-8])([qrbnQRBN])?$/.exec(norm);
      if (lan) {
        from = parseSquare(lan[1]);
        to = parseSquare(lan[2]);
        if (lan[3]) promo = PROMO_CODE[lan[3].toLowerCase()];
      } else {
        throw new Error(`illegal move: ${m}`);
      }
    } else if (m && typeof m === "object") {
      label = `${m.from || ""}${m.to || ""}${m.promotion || ""}`;
      from = parseSquare(m.from);
      to = parseSquare(m.to);
      if (m.promotion)
        promo = PROMO_CODE[String(m.promotion).toLowerCase()] || 0;
      if (from < 0 || to < 0) throw new Error(`illegal move: ${label}`);
    } else {
      throw new Error(`illegal move: ${String(m)}`);
    }

    const legal = this._legalPacked();
    const cands = legal.filter(
      (cand) => (cand & 127) === from && ((cand >> 7) & 127) === to,
    );
    if (cands.length === 0) throw new Error(`illegal move: ${label}`);
    let chosen = cands;
    if (promo !== 0)
      chosen = cands.filter((cand) => ((cand >> 18) & 7) === promo);
    else if (cands.some((cand) => ((cand >> 18) & 7) !== 0)) {
      // promotion without a named piece is ambiguous
      throw new Error(`illegal move: ${label} (promotion piece required)`);
    }
    if (chosen.length === 0) throw new Error(`illegal move: ${label}`);
    return this._doPublicMove(chosen[0]);
  }

  _doPublicMove(m) {
    const san = this._san(m, this._legalPacked());
    const before = this.fen();
    const obj = this._toMoveObject(m, san);
    const snap = this._apply(m);
    const after = this.fen();
    const key = this._key();
    this._hist.push({ m, snap, obj });
    this._keys.push(key);
    this._rep.set(key, (this._rep.get(key) || 0) + 1);
    obj.before = before;
    obj.after = after;
    return obj;
  }

  undo() {
    const h = this._hist.pop();
    if (!h) return null;
    this._revert(h.m, h.snap);
    const key = this._keys.pop();
    const count = (this._rep.get(key) || 1) - 1;
    if (count <= 0) this._rep.delete(key);
    else this._rep.set(key, count);
    return h.obj;
  }

  history(opts = {}) {
    if (opts && opts.verbose) return this._hist.map((h) => h.obj);
    return this._hist.map((h) => h.obj.san);
  }

  // ---- game state ------------------------------------------------------------

  inCheck() {
    return this._ownKingAttacked();
  }

  isCheckmate() {
    return this._ownKingAttacked() && !this._hasLegalReply();
  }

  isStalemate() {
    return !this._ownKingAttacked() && !this._hasLegalReply();
  }

  isFiftyMoves() {
    return this._half >= 100;
  }

  isThreefoldRepetition() {
    return (this._rep.get(this._keys[this._keys.length - 1]) || 0) >= 3;
  }

  isInsufficientMaterial() {
    const minor = [];
    for (let sq = 0; sq < 128; sq++) {
      if (sq & 0x88) continue;
      const p = this._b[sq];
      if (p === EMPTY) continue;
      const a = p < 0 ? -p : p;
      if (a === KING) continue;
      if (a === PAWN || a === ROOK || a === QUEEN) return false;
      minor.push({ piece: a, color: p > 0 ? "w" : "b", square: sqName(sq) });
    }
    if (minor.length === 0) return true; // K vs K
    if (minor.length === 1) return true; // K + minor vs K
    if (minor.length === 2) {
      // K+B vs K+B with bishops on the same square color is dead
      const [a, b] = minor;
      if (a.piece === BISHOP && b.piece === BISHOP && a.color !== b.color) {
        const ca = (a.square.charCodeAt(0) + a.square.charCodeAt(1)) & 1;
        const cb = (b.square.charCodeAt(0) + b.square.charCodeAt(1)) & 1;
        if (ca === cb) return true;
      }
    }
    return false;
  }

  isDraw() {
    return (
      this.isFiftyMoves() ||
      this.isThreefoldRepetition() ||
      this.isInsufficientMaterial()
    );
  }

  isGameOver() {
    return this.isCheckmate() || this.isStalemate() || this.isDraw();
  }

  // ---- perft -------------------------------------------------------------------

  perft(depth, opts = {}) {
    if (!Number.isInteger(depth) || depth < 0)
      throw new Error("perft needs a non-negative integer depth");
    if (opts && opts.divide) {
      const legal = this._legalPacked();
      const out = {};
      for (const m of legal) {
        const san = this._san(m, legal);
        const snap = this._apply(m);
        out[san] = depth <= 1 ? 1 : this._count(depth - 1);
        this._revert(m, snap);
      }
      return out;
    }
    if (depth === 0) return 1;
    const legal = this._legalPacked();
    if (depth === 1) return legal.length;
    let nodes = 0;
    for (const m of legal) {
      const snap = this._apply(m);
      nodes += this._count(depth - 1);
      this._revert(m, snap);
    }
    return nodes;
  }

  _count(depth) {
    const moves = this._gen(false);
    if (depth === 1) {
      let n = 0;
      for (let i = 0; i < moves.length; i++) {
        const snap = this._apply(moves[i]);
        const moverWhite = this._turn === BLACK;
        const ksq = moverWhite ? this._wk : this._bk;
        if (ksq < 0 || !attacked(this._b, ksq, !moverWhite)) n++;
        this._revert(moves[i], snap);
      }
      return n;
    }
    let nodes = 0;
    for (let i = 0; i < moves.length; i++) {
      const snap = this._apply(moves[i]);
      const moverWhite = this._turn === BLACK;
      const ksq = moverWhite ? this._wk : this._bk;
      if (ksq < 0 || !attacked(this._b, ksq, !moverWhite))
        nodes += this._count(depth - 1);
      this._revert(moves[i], snap);
    }
    return nodes;
  }

  // ---- misc ----------------------------------------------------------------------

  _key() {
    let s = this._turn === WHITE ? "w" : "b";
    s += this._castling + "." + this._ep + ".";
    for (let sq = 0; sq < 128; sq++) {
      if (sq & 0x88) continue;
      const p = this._b[sq];
      s +=
        p === EMPTY
          ? "."
          : p > 0
            ? CODE_LETTER[p].toUpperCase()
            : CODE_LETTER[-p];
    }
    return s;
  }

  clone() {
    const c = new Chess(START_FEN);
    c._b = this._b.slice();
    c._turn = this._turn;
    c._castling = this._castling;
    c._ep = this._ep;
    c._half = this._half;
    c._full = this._full;
    c._wk = this._wk;
    c._bk = this._bk;
    c._hist = this._hist.map((h) => ({
      m: h.m,
      snap: { ...h.snap },
      obj: { ...h.obj },
    }));
    c._keys = this._keys.slice();
    c._rep = new Map(this._rep);
    return c;
  }

  ascii() {
    let s = "   +------------------------+\n";
    for (let r = 7; r >= 0; r--) {
      s += " " + (r + 1) + " |";
      for (let f = 0; f < 8; f++) {
        const p = this._b[r * 16 + f];
        const ch =
          p === EMPTY
            ? "."
            : p > 0
              ? CODE_LETTER[p].toUpperCase()
              : CODE_LETTER[-p];
        s += " " + ch + " ";
      }
      s += "|\n";
    }
    s += "   +------------------------+\n";
    s += "     a  b  c  d  e  f  g  h";
    return s;
  }
}
