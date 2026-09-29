<?php
/**
 * Chessko explainer articles: content for /chessko/<slug>.
 *
 * Every figure below comes from the shipped files (assets/chessko/data/*.json, levels.json, the
 * engine sources) or from measurements in a desktop Chrome, and is worded that way. `{{base}}` is
 * replaced with the site base URL when an article is rendered.
 *
 * Shape: slug => [title (<title>, under ~60 chars), h1, description (meta, under ~160 chars),
 *                 eyebrow, published, modified (Y-m-d), lead, sections[[heading, html]], faq?]
 */
return [
    'how-it-works' => [
        'title' => 'How Chessko Works: A Chess Engine in Your Browser',
        'h1' => 'How Chessko works: a chess engine that runs entirely in your browser',
        'description' => 'A tour of Chessko, a free browser chess game: the rules engine, a home-grown search, Stockfish in WebAssembly, Web Workers and a small PHP backend.',
        'eyebrow' => 'Chessko · Architecture',
        'published' => '2026-09-29',
        'modified' => '2026-09-29',
        'lead' => 'Chessko looks like a toy: squares of lime and strawberry jelly, pieces that wobble when they land. Underneath it is a complete chess program, and almost all of it runs in your browser tab. This is the map of how the parts fit together.',
        'sections' => [
            ['The short version', <<<'HTML'
<p>When you move a piece, four things happen, all on your device:</p>
<ol>
<li>A <strong>rules engine</strong> written in JavaScript checks the move is legal and updates the board.</li>
<li>The move is sent to an <strong>engine worker</strong>, a background thread, so the board keeps animating while the computer thinks.</li>
<li>Depending on the difficulty you picked, the reply comes from Chessko's <a href="{{base}}chessko/search-and-evaluation">own alpha-beta search</a> with a <a href="{{base}}chessko/machine-learning">small trained evaluation</a>, or from <a href="{{base}}chessko/stockfish-webassembly">Stockfish 19 compiled to WebAssembly</a>.</li>
<li>The reply is played on the board, and the jelly does its wobble.</li>
</ol>
<p>The server is not in that loop. It hands out the page and a few small files, and it remembers how your finished games ended. It never sees a move.</p>
HTML],
            ['The layers', <<<'HTML'
<table>
<thead><tr><th>Layer</th><th>Runs</th><th>Job</th></tr></thead>
<tbody>
<tr><td>Rules engine (<code>chess.js</code>)</td><td>Browser</td><td>Legal moves, castling, en passant, promotion, check and mate, SAN and FEN. Uses a 0x88 board and is checked against the standard perft node counts.</td></tr>
<tr><td>Jelly search (<code>search.js</code>, <code>eval.js</code>)</td><td>Browser, Web Worker</td><td>Levels 1 to 5. Iterative-deepening alpha-beta with a learned-plus-classical evaluation.</td></tr>
<tr><td>Stockfish 19 (WebAssembly)</td><td>Browser, second Web Worker</td><td>Levels 6 and 7, and the hint button.</td></tr>
<tr><td>Opening book and difficulty bandit (<code>ml.js</code>)</td><td>Browser</td><td>Varied openings for weak levels; suggests when the level is too easy or too hard for you.</td></tr>
<tr><td>Board and jelly (<code>app.js</code>, <code>pieces.js</code>, CSS)</td><td>Browser</td><td>Click and drag moves, SVG pieces, animation, sound made with the Web Audio API (no audio files).</td></tr>
<tr><td>Backend (PHP)</td><td>Server</td><td>Serves the model, book and level files with caching, and stores each visitor's results.</td></tr>
</tbody>
</table>
HTML],
            ['Why a Web Worker matters', <<<'HTML'
<p>A chess search is a tight loop that can run for a second or more. Run it on the page's main thread and the whole interface freezes: no animation, no hover, no sound. Chessko puts the search in a Web Worker. The page sends the position and the level, and the worker sends back a move and some statistics (depth reached, nodes searched, time). While it works, the main thread is free to keep the jelly moving.</p>
<p>Stockfish gets its own worker for the same reason. If a new game starts or you undo a move while Stockfish is thinking, the page sends the UCI <code>stop</code> command and discards the stale answer, so an old search can never play a move in the new position.</p>
HTML],
            ['What the PHP backend does', <<<'HTML'
<p>The backend is deliberately small, because a browser cannot do three things for itself:</p>
<ul>
<li><strong>Serve the trained files</strong> (<code>/api/chessko/model</code>, <code>/book</code>, <code>/config</code>) with an <code>ETag</code>, so a return visit costs a 304 response instead of a download.</li>
<li><strong>Remember results.</strong> When a game ends, the browser posts the result, the level, the side you played and the number of plies. That row is stored under a random identifier your browser generated and keeps in <code>localStorage</code>. There is no account, and no IP address or user agent is stored.</li>
<li><strong>Report health</strong> (<code>/api/chessko/health</code>): the model's metadata and how many games have been recorded.</li>
</ul>
<p>The endpoint validates every field, caps the request body at 16 KB, rate-limits how many games one visitor can record per minute, and keeps only the newest 200 games per visitor.</p>
HTML],
            ['What is deliberately not here', <<<'HTML'
<p>There is no server-side chess: you cannot lose because the server was slow, and the game keeps working if the network drops mid-game. There is no neural-network training in the page. Training happens offline in Python and only the finished weights (about 2 KB) are shipped. There is no tracking of your moves.</p>
<p>Next: <a href="{{base}}chessko/search-and-evaluation">how the search works</a>, or jump to <a href="{{base}}chessko">the game</a>.</p>
HTML],
        ],
    ],

    'search-and-evaluation' => [
        'title' => 'Alpha-Beta Search and Chess Evaluation, Explained',
        'h1' => 'Chess engine basics: alpha-beta search, quiescence and evaluation',
        'description' => 'How a chess engine picks a move: negamax, alpha-beta pruning, move ordering, transposition tables, quiescence search and evaluation, with examples from Chessko.',
        'eyebrow' => 'Chessko · Engine internals',
        'published' => '2026-09-29',
        'modified' => '2026-09-29',
        'lead' => 'Every chess engine, from a hobby project to Stockfish, does the same two things: look ahead through possible moves, and score the positions it reaches. Chessko\'s own engine is small enough to read in an afternoon, which makes it a good place to see how each idea works.',
        'sections' => [
            ['Look ahead with negamax', <<<'HTML'
<p>Chess is a zero-sum game: what is good for White is exactly as bad for Black. That lets an engine use <strong>negamax</strong>, a compact form of minimax. To score a position for the side to move, try every legal move, ask &ldquo;how good is the resulting position for my opponent?&rdquo;, negate that score, and keep the best. Repeat until you reach the depth limit, then call an evaluation function.</p>
<p>The trouble is the size of the tree. A typical position has around 30 legal moves, so looking four plies (half-moves) ahead means on the order of 30<sup>4</sup>, about 810,000 positions, and every extra ply multiplies that again. Everything else in this article is about looking at fewer of them.</p>
HTML],
            ['Alpha-beta pruning', <<<'HTML'
<p>Alpha-beta keeps two numbers while searching: the best score the side to move is already guaranteed (<em>alpha</em>) and the best score the opponent is already guaranteed (<em>beta</em>). If a move turns out to be so good for the mover that the opponent would never allow the position to arise, the search stops examining the rest of that move's siblings. That is a <strong>cutoff</strong>, and it is safe: the result is identical to plain minimax, just faster.</p>
<p>How much faster depends on <strong>move ordering</strong>. If the best move is tried first, most of the other moves are cut off almost immediately. Chessko orders moves with the classic set of cheap heuristics:</p>
<ul>
<li><strong>Transposition-table move:</strong> the best move found for this position in an earlier, shallower search goes first.</li>
<li><strong>MVV-LVA</strong> (most valuable victim, least valuable attacker): capturing a queen with a pawn is tried before capturing a pawn with a queen.</li>
<li><strong>Killer moves:</strong> quiet moves that caused a cutoff at the same depth in a sibling branch are tried early.</li>
<li><strong>History heuristic:</strong> a running count of how often each quiet move has caused cutoffs, so moves that keep working keep being tried early. This is a small piece of online learning inside the search.</li>
</ul>
HTML],
            ['Iterative deepening and the clock', <<<'HTML'
<p>Instead of searching straight to depth 4, the engine searches depth 1, then 2, then 3, then 4. That sounds wasteful, but each shallow pass is cheap compared with the next one, and it pays for itself twice. The best move from depth <em>n</em> becomes the first move tried at depth <em>n+1</em>, which sharpens the ordering. And because there is always a finished result from the previous depth, the search can obey a <strong>time budget</strong>: when the deadline arrives it stops and plays the best move from the last completed depth. Chessko checks the clock inside the search, including inside quiescence, so a long capture sequence cannot overrun it.</p>
HTML],
            ['Transposition tables and Zobrist hashing', <<<'HTML'
<p>The same position can be reached by different move orders (1.e4 Nf6 2.Nc3 and 1.Nc3 Nf6 2.e4 give the same board). A <strong>transposition table</strong> caches the result of searching a position, so the second visit is free. To look positions up quickly, each one is reduced to a number with <strong>Zobrist hashing</strong>: every (piece, square) pair gets a fixed random number, and the position's key is the XOR of the numbers for every piece on the board. Making a move only XORs out the piece from its old square and XORs it into the new one, so the key is updated incrementally instead of recomputed.</p>
<p>Chessko builds a key of roughly 53 bits from two 32-bit halves. That is the largest integer a JavaScript number can hold exactly, so it avoids BigInt and string keys in the hot loop.</p>
HTML],
            ['Quiescence search: not stopping mid-fight', <<<'HTML'
<p>If the search stops at depth 4 right after your queen takes a pawn, the evaluation sees a free pawn. It cannot see that the queen is about to be recaptured on the next move. This is the <strong>horizon effect</strong>. <strong>Quiescence search</strong> fixes it: at the depth limit, instead of evaluating immediately, the engine keeps searching captures only, until the position is quiet. Only then does it score the board.</p>
HTML],
            ['Evaluation: turning a board into a number', <<<'HTML'
<p>The leaf positions need a score, in centipawns (hundredths of a pawn), from the side to move's point of view. Chessko's hand-tuned evaluation adds three things: <strong>material</strong> (pawn 100, knight 320, bishop 330, rook 500, queen 900), <strong>piece-square tables</strong> (a knight on the rim is worth less than in the centre) and <strong>mobility</strong> (how many squares the pieces can reach), plus a small bishop-pair bonus.</p>
<p>That is enough to play recognisable chess at a few plies of depth. The interesting part is what happens when the evaluation is <em>learned</em> instead: see <a href="{{base}}chessko/machine-learning">machine learning in a chess engine</a>. And for an engine that has all of the above at industrial strength, see <a href="{{base}}chessko/stockfish-webassembly">Stockfish in the browser</a>.</p>
HTML],
        ],
        'faq' => [
            ['What is alpha-beta pruning in chess?', 'Alpha-beta pruning is an optimisation of minimax search. It skips branches that cannot change the final decision because the opponent already has a better alternative elsewhere, so the engine reaches the same move while examining far fewer positions.'],
            ['What is quiescence search?', 'Quiescence search extends the search at the depth limit by following capture moves only, until the position is quiet. It avoids the horizon effect, where an engine misjudges a position because it stopped in the middle of an exchange.'],
            ['What is a transposition table?', 'A transposition table is a cache of positions already searched, keyed by a hash of the board (usually a Zobrist hash). When the same position is reached by another move order, the engine reuses the stored result instead of searching it again.'],
        ],
    ],

    'machine-learning' => [
        'title' => 'Machine Learning in a Chess Engine: A Small Example',
        'h1' => 'Machine learning in a chess engine: logistic regression, a k-NN book and a UCB1 bandit',
        'description' => 'What machine learning does in a small chess engine: a 16-feature logistic-regression evaluation, a k-NN opening book and a UCB1 bandit, with real numbers.',
        'eyebrow' => 'Chessko · Machine learning',
        'published' => '2026-09-29',
        'modified' => '2026-09-29',
        'lead' => 'Chessko\'s learned parts are small on purpose: a 16-number evaluation, a book of 33 positions, and a three-armed bandit. They are simple enough to inspect, and they come with a result worth being honest about: the learned evaluation is a better predictor than it is a player.',
        'sections' => [
            ['The learned evaluation: 16 numbers', <<<'HTML'
<p>The learned evaluation is a <strong>logistic regression</strong>. A position is turned into 16 features, all measured from the side to move's point of view (own value minus opponent value), so swapping colours cannot change the result:</p>
<p><code>bias</code>, material for each piece type, <code>center</code> control, <code>activity</code>, king <code>shield</code>, pawn <code>structure</code>, <code>passed</code> pawns, <code>tempo</code>, game <code>phase</code>, and three phase-interaction terms (pawns, rooks and queens weighted by how much of the game is left), so a linear model can say that a rook matters more in the endgame.</p>
<p>The model multiplies each feature by a learned weight, adds them, and squashes the sum into a probability that the side to move wins. That probability is converted back to centipawns so the search can use it. The whole model is 16 weights, about 2 KB of JSON.</p>
HTML],
            ['Where the training data comes from', <<<'HTML'
<p>Chessko trains on positions from <strong>200 self-play games</strong> (7,805 positions after filtering). The labels are not game results: most self-play games are drawn, and the same opening recurs with different outcomes, which makes results a very noisy signal. Instead the labels are <strong>distilled from a deeper search</strong>: a stronger &ldquo;teacher&rdquo; search scores each position, and the model learns to predict that score from the cheap features.</p>
<p>Training runs offline in Python (standard library only): mini-batch stochastic gradient descent on soft targets, 80 epochs, followed by a <strong>temperature calibration</strong> and neutral-offset fit on a held-out split of <em>games</em> (not positions, so the validation set has no positions from training games).</p>
HTML],
            ['What the model learned, and how good it is', <<<'HTML'
<p>On the held-out games, the model's log loss is <strong>0.437</strong> against <strong>0.643</strong> for a constant predictor, and it agrees with the teacher on which side is better in about 93% of positions where the teacher has an opinion.</p>
<p>The learned material weights are the most readable part: pawn 0.83, knight 2.05, bishop 2.06, rook 3.31, queen 8.49. The ordering pawn &lt; knight &asymp; bishop &lt; rook &lt; queen is the one every chess book teaches, rediscovered from data. The queen weight, in particular, comes out well above the rook's, as expected.</p>
HTML],
            ['The honest negative result', <<<'HTML'
<p>A better predictor is not automatically a better player. In a quick round robin at search depth 4 (8 games per pairing, fixed openings, both colours), an evaluation that was 50% learned scored 34%, the pure hand-tuned evaluation 56%, and a 25% blend 59%. The sample is small, so the honest reading is that 50% is clearly worse and that 25% versus none is too close to call. That is why the learned model contributes <strong>25%</strong> of the blend on every level, with the classical evaluation supplying the rest. There is also a known weak spot: the training set starts at ply 8, so the model extrapolates in the first moves and reads roughly a pawn off there. The eval bar in the game calls a wide band around zero &ldquo;balanced&rdquo; for that reason instead of pretending to half-pawn precision.</p>
<p>That is a real limitation of a 16-feature linear model trained on 7,805 positions, and it is why the top two levels hand over to <a href="{{base}}chessko/stockfish-webassembly">Stockfish</a>.</p>
HTML],
            ['The k-NN opening book', <<<'HTML'
<p>To keep weak levels from playing the same game every time, Chessko has a small opening book built from the same 200 games: 33 positions, up to 12 plies deep. Lookup is two-stage. First an exact match on the position's hash. If there is none, a <strong>k-nearest-neighbours</strong> vote: the position is turned into a short vector (material, centre control, phase), the five nearest book positions inside a distance threshold vote for their moves, and each vote is weighted by 1 / (0.15 + distance). Only legal moves are ever returned. All five jelly levels use the book for the first four plies.</p>
HTML],
            ['The UCB1 difficulty bandit', <<<'HTML'
<p>The third component learns about <em>you</em>, in your browser. After each move you play, Chessko compares the position's score with what it expected and turns the difference into a <strong>move-quality</strong> number between 0 and 1. A <strong>UCB1 bandit</strong> with three arms (&minus;1, 0, +1: make it easier, keep, make it harder) treats each arm as a difficulty offset. It uses the upper-confidence-bound rule, average reward plus an exploration bonus that shrinks the more an arm has been tried, to decide which offset fits you best. It suggests a change only when another arm clearly beats the current one, and it can move the level for you if you turn on &ldquo;auto-goo&rdquo;. Its counts live in <code>localStorage</code>, so it never leaves your device.</p>
<p>See <a href="{{base}}chessko/difficulty-levels">how the difficulty levels work</a> for what a level actually changes.</p>
HTML],
        ],
        'faq' => [
            ['Does Chessko use a neural network?', 'The learned evaluation is a logistic regression with 16 weights, not a neural network. The strongest levels use Stockfish 19, which does use an NNUE neural-network evaluation, compiled to WebAssembly.'],
            ['What is a UCB1 bandit?', 'UCB1 is a multi-armed-bandit algorithm that picks the option with the highest average reward plus an exploration bonus. Chessko uses it to decide whether the difficulty should go down, stay or go up, based on how well you are playing.'],
        ],
    ],

    'stockfish-webassembly' => [
        'title' => 'Stockfish in the Browser: WebAssembly and Web Workers',
        'h1' => 'Running Stockfish in the browser with WebAssembly and Web Workers',
        'description' => 'How Chessko runs Stockfish 19 (a 1.8 MB WebAssembly build) in a Web Worker: UCI over postMessage, lazy loading, Elo limiting, stopping searches, and the GPL.',
        'eyebrow' => 'Chessko · WebAssembly',
        'published' => '2026-09-29',
        'modified' => '2026-09-29',
        'lead' => 'Stockfish is written in C++. Chessko\'s two strongest levels run it inside your browser tab, with no server doing the thinking. This is how that works, and what it costs.',
        'sections' => [
            ['What WebAssembly is doing here', <<<'HTML'
<p>WebAssembly (WASM) is a compact binary format that browsers run at close to native speed. The Stockfish team's C++ is compiled to a <code>.wasm</code> file, and a small JavaScript loader instantiates it. Chessko ships <strong>Stockfish 19, lite single-threaded build</strong>, from the <a href="https://github.com/nmrugg/stockfish.js" rel="noopener">stockfish.js</a> project: a 1.8 MB <code>.wasm</code> plus a 21 KB loader, with its NNUE network embedded in the binary.</p>
<p>In testing on a desktop Chrome, it searched roughly 700,000 positions per second and reached depth 20 from the starting position in about half a second. That is far past what a JavaScript engine of Chessko's size manages, which is the point.</p>
HTML],
            ['Why the lite, single-threaded build', <<<'HTML'
<p>Stockfish.js comes in several builds. The full multi-threaded engine is about 94 MB and needs the page to be <em>cross-origin isolated</em> (special <code>COOP</code> and <code>COEP</code> headers so <code>SharedArrayBuffer</code> is allowed). That would constrain every other page on this site. The single-threaded lite build needs none of that, downloads in a moment, and is still stronger than any human player. The trade-off is that it is weaker than the full engine, and it uses one thread.</p>
HTML],
            ['Talking to it: UCI over postMessage', <<<'HTML'
<p>The engine is a plain classic Web Worker that speaks <strong>UCI</strong>, the text protocol chess engines have used for decades. The page sends lines with <code>postMessage</code> and receives lines back:</p>
<pre><code>uci                                  → uciok
setoption name UCI_LimitStrength value true
setoption name UCI_Elo value 2000
isready                              → readyok
position fen &lt;fen&gt; moves e2e4 e7e5
go movetime 700                      → info depth 14 score cp 21 … pv …
                                     → bestmove g1f3</code></pre>
<p>Chessko's wrapper parses the <code>info</code> lines (depth, nodes, score in centipawns or mate distance, principal variation) and resolves a promise on <code>bestmove</code>. Because a search is a promise, &ldquo;stop the old search when the position changes&rdquo; is a single <code>stop</code> command and a discarded answer.</p>
HTML],
            ['Loading it lazily', <<<'HTML'
<p>Nothing is downloaded until it is needed. The jelly levels never touch the WASM file. The first time you select a Stockfish level or press <em>hint</em>, the worker starts and the 1.8 MB file is fetched; selecting the level starts that download while you are still thinking about your first move. In local testing the engine answered <code>readyok</code> in about 190 ms. If the browser has no WebAssembly or Web Worker support, or the file cannot load, Chessko falls back to its own search at full effort and tells you so.</p>
HTML],
            ['Strength limiting', <<<'HTML'
<p>Stockfish has a built-in limiter. With <code>UCI_LimitStrength</code> on, <code>UCI_Elo</code> makes the engine deliberately play worse, choosing weaker moves in a calibrated way instead of just searching shallowly. Level 6, <em>Gelatitan</em>, is capped at <code>UCI_Elo</code> 2000; level 7, <em>Set in Stone</em>, plays at full strength for 1.5 seconds a move. Note that <code>UCI_Elo</code> is Stockfish's own scale, calibrated against its own test conditions, not a rating from any rating list. The hint button always uses full strength, because a hint should be the best move, not a wobbly one.</p>
HTML],
            ['Licence', <<<'HTML'
<p>Stockfish is free software under the <strong>GNU General Public License v3</strong>. The files served here are the unmodified <code>stockfish-19-lite-single</code> build from the <code>stockfish</code> npm package, with its licence text alongside. The corresponding source and build scripts are in the <a href="https://github.com/nmrugg/stockfish.js" rel="noopener">stockfish.js</a> and <a href="https://github.com/official-stockfish/Stockfish" rel="noopener">Stockfish</a> repositories, and the network was trained by Chris Bao. Chessko only talks to the engine over the UCI text protocol.</p>
<p>Want to try it? <a href="{{base}}chessko">Pick <em>Set in Stone</em></a>.</p>
HTML],
        ],
        'faq' => [
            ['Can Stockfish run in a web browser?', 'Yes. Stockfish can be compiled to WebAssembly and run in a Web Worker. Chessko uses a 1.8 MB single-threaded lite build that needs no special server headers.'],
            ['Is the Stockfish in Chessko free software?', 'Yes. It is distributed under the GNU General Public License v3, and the licence and source links are published with the files.'],
        ],
    ],

    'difficulty-levels' => [
        'title' => 'How Chessko\'s Difficulty Levels Work (Jelly to Stockfish)',
        'h1' => 'How Chessko\'s difficulty levels work: temperature, slips and Stockfish limits',
        'description' => 'The seven Chessko levels explained: move sampling and deliberate slips weaken the jelly engine; Stockfish is capped at UCI_Elo 2000 or runs at full strength.',
        'eyebrow' => 'Chessko · Difficulty',
        'published' => '2026-09-29',
        'modified' => '2026-09-29',
        'lead' => 'A weak chess opponent is harder to build than a strong one. Making a program play badly in a way that feels human, without hanging its queen for no reason, takes deliberate design. Here is how each of Chessko\'s seven levels is made.',
        'sections' => [
            ['The seven levels', <<<'HTML'
<table>
<thead><tr><th>#</th><th>Name</th><th>Brain</th><th>How it is weakened</th></tr></thead>
<tbody>
<tr><td>1</td><td>Jiggly</td><td>Jelly search, depth 4, 800 ms</td><td>Wobble 340 cp, 45% slips</td></tr>
<tr><td>2</td><td>Wobbly</td><td>Jelly search, depth 4, 800 ms</td><td>Wobble 210 cp, 28% slips</td></tr>
<tr><td>3</td><td>Bouncy</td><td>Jelly search, depth 4, 800 ms</td><td>Wobble 120 cp, 14% slips</td></tr>
<tr><td>4</td><td>Springy</td><td>Jelly search, depth 4, 800 ms</td><td>Wobble 55 cp, 5% slips</td></tr>
<tr><td>5</td><td>Blobmaster</td><td>Jelly search, depth 4, 800 ms</td><td>None: always the best move it finds</td></tr>
<tr><td>6</td><td>Gelatitan</td><td>Stockfish 19 (WASM), 700 ms</td><td><code>UCI_Elo</code> 2000</td></tr>
<tr><td>7</td><td>Set in Stone</td><td>Stockfish 19 (WASM), 1500 ms</td><td>None: full strength</td></tr>
</tbody>
</table>
<p>Levels 1 to 5 share the same search depth. What differs is how often, and how far, the engine steps away from the best move it found.</p>
HTML],
            ['Wobble: sampling instead of always picking the best', <<<'HTML'
<p>After searching, the jelly engine has a score for each legal root move. At level 5 it plays the top one. At lower levels it <strong>samples</strong>: each move gets weight <code>exp((score &minus; best) / temperature)</code>, the weights are normalised, and a move is drawn at random. A move 100 centipawns worse than the best is barely less likely at a high temperature, and rare at a low one. The temperature is the &ldquo;wobble&rdquo; number in the table, in centipawns: bigger means a wilder spread of moves.</p>
<p>This is the same idea as temperature in language-model sampling, applied to chess moves. It makes the engine's mistakes graded (it is much more likely to play the second-best move than the fifth-best) instead of uniformly random.</p>
HTML],
            ['Slips: deliberate blunders', <<<'HTML'
<p>Sampling alone makes mistakes small. Beginners also make occasional large ones, so each low level has a &ldquo;slip&rdquo; probability. On a slip, the engine picks at random from the moves that are worse than the best by more than 25 centipawns but less than 900, so it errs noticeably but does not throw the queen away. Level 1 slips in about 45% of moves, level 4 in about 5%.</p>
HTML],
            ['Why the top levels use Stockfish', <<<'HTML'
<p>The jelly search is a small JavaScript engine with a 16-weight evaluation. It is meant to be beatable, and to wobble. To give stronger players something real to play against, the ladder changes engine above level 5 instead of stretching the same one: Stockfish limited by its own Elo limiter at level 6, unlimited at level 7. See <a href="{{base}}chessko/stockfish-webassembly">Stockfish in the browser</a> for how that works.</p>
HTML],
            ['Adapting to you', <<<'HTML'
<p>Not knowing which level to pick is normal. The <a href="{{base}}chessko/machine-learning">UCB1 bandit</a> watches how well you play on the jelly levels and suggests a step up or down. It leaves the two Stockfish levels alone: they are fixed points on the ladder.</p>
<p>Ready? <a href="{{base}}chessko">Play Chessko</a>.</p>
HTML],
        ],
        'faq' => [
            ['How does Chessko make the computer play worse?', 'On the lower levels it samples among the engine\'s candidate moves with a temperature, so it sometimes plays a slightly worse move, and it adds an occasional deliberate slip. The two Stockfish levels use Stockfish\'s own UCI_Elo limiter, or no limit at all.'],
        ],
    ],
];
