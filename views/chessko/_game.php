<?php
/**
 * The Chessko game markup. app.js finds everything by id, so ids must stay in step with
 * chessko/web/index.html (the standalone page). Headings inside the game are paragraphs on
 * purpose: the page's own outline (h1, then the article sections) is what search engines read.
 */
?>
<div class="ck" id="chessko" data-ready="false">
    <div class="mold">
        <div class="masthead">
            <p class="wordmark" id="wordmark" aria-hidden="true">chessko</p>
            <p class="tagline">jelly chess against a trained brain, or Stockfish running as WebAssembly in your browser</p>
        </div>

        <div class="panes">
            <section class="board-pane" aria-label="Chess board">
                <div class="board-shell" id="board-shell">
                    <div class="board" id="board" role="grid" aria-label="Chess board"></div>
                </div>
                <div class="board-meta">
                    <span class="chip" id="side-chip">you play <b id="side-name">milk jelly</b></span>
                    <span class="chip" id="perf-chip" title="search effort for the last engine move">engine <b id="perf-value">—</b></span>
                    <span class="chip" id="think-chip" hidden>wobbling…</span>
                </div>
            </section>

            <section class="panel" aria-label="Controls and diagnostics">
                <div class="status-card">
                    <p class="status" id="status-text">loading the jelly brain…</p>
                    <div class="controls">
                        <button type="button" class="jbtn" id="new-game">new game</button>
                        <button type="button" class="jbtn" id="undo">undo</button>
                        <button type="button" class="jbtn" id="flip">flip sides</button>
                        <button type="button" class="jbtn" id="hint">hint</button>
                    </div>
                </div>

                <p class="panel-title">squishiness</p>
                <div class="levels" id="level-list" role="radiogroup" aria-label="Difficulty level"></div>
                <p class="level-note" id="level-note"></p>

                <p class="panel-title">chessko's opinion</p>
                <div class="tube" id="eval-bar" role="img" aria-label="Evaluation">
                    <div class="tube-fill" id="eval-fill"></div>
                    <span class="tube-label" id="eval-text">…</span>
                </div>
                <p class="fineprint" id="eval-detail">waiting for the model</p>

                <p class="panel-title">moves</p>
                <ol class="moves" id="moves" aria-label="Move list"></ol>

                <details class="nib" id="brain">
                    <summary>jelly brain</summary>
                    <div class="nib-body">
                        <p class="fineprint" id="ml-source">—</p>
                        <dl class="stats" id="ml-stats"></dl>
                        <p class="fineprint" id="ml-book">—</p>
                        <div class="bandit" id="ml-bandit"></div>
                        <div class="features" id="ml-features"></div>
                        <svg class="spark" id="loss-spark" viewBox="0 0 120 32" preserveAspectRatio="none" aria-label="Training loss over epochs"></svg>
                        <p class="fineprint" id="ml-loss">—</p>
                        <ul class="algs" id="ml-algs"></ul>
                        <button type="button" class="jbtn small" id="retrain" hidden>retrain on the backend</button>
                    </div>
                </details>

                <details class="nib" id="record">
                    <summary>your jelly record</summary>
                    <div class="nib-body">
                        <dl class="stats" id="record-stats"></dl>
                        <ol class="recent" id="record-recent"></ol>
                    </div>
                </details>

                <details class="nib" id="lab">
                    <summary>lab</summary>
                    <div class="nib-body">
                        <label class="field">load a position (FEN)
                            <input type="text" id="fen-input" spellcheck="false" autocomplete="off" placeholder="rnbqkbnr/pppppppp/…">
                        </label>
                        <div class="controls">
                            <button type="button" class="jbtn small" id="fen-load">load</button>
                            <button type="button" class="jbtn small" id="fen-copy">copy current FEN</button>
                            <button type="button" class="jbtn small" id="sound-toggle" aria-pressed="true">sound: on</button>
                            <button type="button" class="jbtn small" id="reset-bandit">reset adaptation</button>
                        </div>
                        <p class="fineprint" id="lab-note">pieces squish with a small synthesised blip — no audio files, no libraries.</p>
                        <label class="check">
                            <input type="checkbox" id="auto-nudge">
                            let the bandit move the difficulty itself (auto-goo)
                        </label>
                    </div>
                </details>
            </section>
        </div>

        <div class="footer">
            <p id="backend-line">served by the chessko php backend</p>
        </div>
    </div>

    <dialog class="jelly-dialog" id="promotion-dialog">
        <form method="dialog">
            <h2>pick a flavour</h2>
            <div class="promo-picks" id="promo-picks"></div>
        </form>
    </dialog>

    <dialog class="jelly-dialog" id="result-dialog">
        <form method="dialog">
            <h2 id="result-title">game over</h2>
            <p id="result-text"></p>
            <button type="button" class="jbtn" id="result-again">play again</button>
            <button type="button" class="jbtn ghost" id="result-close">close</button>
        </form>
    </dialog>

    <div class="sr-only" id="live-region" role="status" aria-live="polite"></div>
</div>
