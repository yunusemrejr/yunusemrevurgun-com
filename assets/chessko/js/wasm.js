// WebAssembly brain: Stockfish 19 (lite, single-threaded) driven over the UCI text protocol.
//
// The engine is a plain classic Worker (web/vendor/stockfish/stockfish-19-lite-single.js) that
// loads its own .wasm from next to the script. It is loaded lazily — nothing is downloaded until a
// Stockfish level is played or a hint is asked for — and it needs no SharedArrayBuffer, so the page
// does not have to be cross-origin isolated.
//
// Only one search runs at a time. Asking for a second move while one is running stops the first
// (its promise resolves with `stopped: true`), so a new game or an undo never waits for an old
// search to finish.

const UCI_PROMOTIONS = new Set(["q", "r", "b", "n"]);

/** "e7e8q" -> { from: "e7", to: "e8", promotion: "q" } */
export function parseUci(uci) {
  if (typeof uci !== "string" || !/^[a-h][1-8][a-h][1-8][qrbn]?$/.test(uci))
    return null;
  const promotion = uci[4];
  return {
    from: uci.slice(0, 2),
    to: uci.slice(2, 4),
    promotion: promotion && UCI_PROMOTIONS.has(promotion) ? promotion : null,
  };
}

/** { from, to, promotion } -> "e7e8q" */
export function toUci(move) {
  return `${move.from}${move.to}${move.promotion || ""}`;
}

/** Fold a UCI "info ..." line into { depth, cp, mate, nodes, nps, ms, pv }, or null. */
export function parseInfo(line) {
  if (!line.startsWith("info ") || !line.includes(" score ")) return null;
  const tokens = line.split(/\s+/);
  const info = { pv: [] };
  for (let i = 1; i < tokens.length; i++) {
    const key = tokens[i];
    if (key === "depth") info.depth = Number(tokens[++i]);
    else if (key === "nodes") info.nodes = Number(tokens[++i]);
    else if (key === "nps") info.nps = Number(tokens[++i]);
    else if (key === "time") info.ms = Number(tokens[++i]);
    else if (key === "multipv") info.multipv = Number(tokens[++i]);
    else if (key === "score") {
      const kind = tokens[++i];
      const value = Number(tokens[++i]);
      if (kind === "cp") info.cp = value;
      else if (kind === "mate") info.mate = value;
      if (tokens[i + 1] === "lowerbound" || tokens[i + 1] === "upperbound") i++;
    } else if (key === "pv") {
      info.pv = tokens.slice(i + 1);
      break;
    }
  }
  if (info.cp === undefined && info.mate === undefined) return null;
  return info;
}

/** Centipawns from a parsed score; a forced mate counts as a very large score. */
export function scoreToCp(info, mateScore = 100000) {
  if (!info) return 0;
  if (info.mate !== undefined)
    return info.mate > 0
      ? mateScore - info.mate
      : -mateScore - info.mate;
  return info.cp || 0;
}

export class WasmBrain {
  /**
   * @param {{ url: string, hashMb?: number, Worker?: typeof Worker }} options
   *   url — the stockfish-19-lite-single.js file; its .wasm sits beside it.
   */
  constructor({ url, hashMb = 16, Worker: WorkerImpl } = {}) {
    if (!url) throw new Error("WasmBrain needs the engine url");
    this.url = url;
    this.hashMb = hashMb;
    this.WorkerImpl = WorkerImpl || globalThis.Worker;
    this.worker = null;
    this.booting = null;
    this.listeners = new Set();
    this.current = null; // the running search: { resolve, info, uci }
    this.appliedElo = undefined; // last UCI_Elo state sent to the engine (null = full strength)
    this.name = "Stockfish 19 (lite)";
  }

  get supported() {
    return (
      typeof this.WorkerImpl === "function" &&
      typeof WebAssembly === "object" &&
      typeof WebAssembly.instantiate === "function"
    );
  }

  get loaded() {
    return Boolean(this.worker) && !this.booting;
  }

  /** Start the engine (once) and resolve when it answers "readyok". */
  ready() {
    if (this.booting) return this.booting;
    if (this.worker) return Promise.resolve(this);
    if (!this.supported)
      return Promise.reject(new Error("WebAssembly workers are not available"));

    this.booting = new Promise((resolve, reject) => {
      let worker;
      try {
        worker = new this.WorkerImpl(this.url);
      } catch (err) {
        reject(err);
        return;
      }
      this.worker = worker;
      const timer = setTimeout(
        () => fail(new Error("the Stockfish engine did not start in time")),
        20000,
      );
      const fail = (err) => {
        clearTimeout(timer);
        this.dispose();
        reject(err);
      };
      worker.addEventListener("error", (event) =>
        fail(new Error(event.message || "the Stockfish worker failed to load")),
      );
      worker.addEventListener("message", (event) =>
        this.onLine(String(event.data)),
      );

      let stage = "uci";
      const step = (line) => {
        if (stage === "uci" && line === "uciok") {
          stage = "ready";
          this.send(`setoption name Hash value ${this.hashMb}`);
          this.send("setoption name Threads value 1");
          this.send("isready");
        } else if (stage === "ready" && line === "readyok") {
          clearTimeout(timer);
          this.listeners.delete(step);
          this.booting = null;
          resolve(this);
        } else if (line.startsWith("id name ")) {
          this.name = line.slice(8).trim() || this.name;
        }
      };
      this.listeners.add(step);
      this.send("uci");
    });
    return this.booting;
  }

  send(line) {
    this.worker?.postMessage(line);
  }

  onLine(line) {
    for (const listener of [...this.listeners]) listener(line);
    const run = this.current;
    if (!run) return;
    const info = parseInfo(line);
    // keep the deepest principal variation (multipv 1) as the running answer
    if (info && (info.multipv === undefined || info.multipv === 1))
      run.info = info;
    if (line.startsWith("bestmove")) {
      this.current = null;
      const uci = line.split(/\s+/)[1];
      run.resolve({ uci: uci === "(none)" ? null : uci, info: run.info });
    }
  }

  /** Wait for the search in flight (if any) to end, stopping it first. */
  async halt() {
    if (!this.current) return;
    const running = this.current;
    const done = new Promise((resolve) => {
      const original = running.resolve;
      running.resolve = (value) => {
        original({ ...value, stopped: true });
        resolve();
      };
    });
    this.send("stop");
    await done;
  }

  async configure(elo) {
    const wanted = elo || null;
    if (this.appliedElo === wanted) return;
    if (wanted) {
      this.send("setoption name UCI_LimitStrength value true");
      this.send(`setoption name UCI_Elo value ${wanted}`);
    } else {
      this.send("setoption name UCI_LimitStrength value false");
    }
    this.appliedElo = wanted;
  }

  /**
   * Best move for a position.
   * @param {{ fen: string, moves?: {from,to,promotion}[], movetimeMs?: number, depth?: number,
   *           elo?: number|null }} request
   * @returns {Promise<{ move: {from,to,promotion}|null, uci: string|null, cp: number,
   *           mate: number|undefined, depth: number, nodes: number, nps: number, ms: number,
   *           pv: string[], stopped: boolean }>}
   */
  async bestMove({ fen, moves = [], movetimeMs = 500, depth = 0, elo = null }) {
    await this.ready();
    await this.halt();
    await this.configure(elo);

    const position = fen && fen !== "startpos" ? `fen ${fen}` : "startpos";
    const tail = moves.length ? ` moves ${moves.map(toUci).join(" ")}` : "";
    const started = performance.now();
    const result = await new Promise((resolve) => {
      this.current = { resolve, info: null };
      this.send(`position ${position}${tail}`);
      this.send(depth > 0 ? `go depth ${depth}` : `go movetime ${movetimeMs}`);
    });
    const info = result.info || {};
    return {
      move: parseUci(result.uci),
      uci: result.uci,
      cp: scoreToCp(info),
      mate: info.mate,
      depth: info.depth || 0,
      nodes: info.nodes || 0,
      nps: info.nps || 0,
      ms: info.ms || Math.round(performance.now() - started),
      pv: info.pv || [],
      stopped: Boolean(result.stopped),
    };
  }

  /** Abort the running search (its promise resolves with stopped: true). */
  stop() {
    if (this.current) this.send("stop");
  }

  dispose() {
    this.worker?.terminate();
    this.worker = null;
    this.booting = null;
    this.listeners.clear();
    this.current = null;
    this.appliedElo = undefined;
  }
}
