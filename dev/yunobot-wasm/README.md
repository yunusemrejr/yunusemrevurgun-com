# YunoBot C++ / WebAssembly

The browser runs `core.wasm` inside a dedicated worker. JavaScript handles downloads,
UTF-8 transport, the DOM and the device clock. Tokenization, Turkish case folding,
feature extraction, both classifiers, source ranking, response selection/composition,
arithmetic and dialogue state run in C++.

The core combines the existing 96-dimensional int8 embedding-bag neural classifier
with a separately trained 33-class conversational logistic classifier (word and
character trigram features, including an out-of-scope class). Offline training stays
in the build script. Conversation data lives in `conversation.json`; polite wrappers
augment its examples. Facts are reviewed source-linked text, not learned biography.
Casual replies combine reviewed language-specific text and conversational slots.
This is not a general-purpose generative LLM or an automatic translator.

Source matching uses BM25-style term weights, title/body evidence, bilingual term
aliases and definition preference. Follow-ups stay in the previous source and skip
already displayed passages. Clear resets state without downloading another model.
Mixed-language questions select mixed replies; quotations preserve source language.
“Latest” questions require a successfully refreshed source snapshot.

## Build

Requires Node.js, clang++ and wasm-ld (no npm packages or Emscripten runtime):

```
node dev/yunobot-wasm/build.mjs
node dev/yunobot-wasm/test.mjs
node dev/yunobot-wasm/differential.mjs
```

`generated.hpp` is ignored and reproduced from tracked data and model weights.
`assets/js/yunobot/core.wasm` is the deployable binary and is committed. The old JS
classifier and knowledge engine remain only as comparison/build inputs for the
existing tests; the production YunoBot page does not load them.

The module imports no host functions and has a fixed 32 MiB memory ceiling. Input
is limited to 2,000 browser characters / 8,000 UTF-8 bytes. Index capacity is 4,096
passages and a 10 MiB text pool. Over-capacity passages are skipped, not truncated
into misleading source quotes. A worker keeps computation off the main UI thread.
No SIMD, threads, shared memory or cross-origin-isolation headers are required.

## Validation

- `test.mjs`: curated conversation, code switching, factual retrieval, abstention,
  dialogue reset, provenance and fixed-memory regressions. This is a regression
  suite, not an independent statistical accuracy estimate.
- `differential.mjs`: compares C++ probabilities with the previous JS neural model.
- `native-test.cpp`: 2,000 byte-input cases under AddressSanitizer/UBSan. Build using
  `clang++ -std=c++17 -O1 -g -fsanitize=address,undefined dev/yunobot-wasm/native-test.cpp -o /tmp/yunobot-native-test`.
  In ptraced environments use `ASAN_OPTIONS=detect_leaks=0` (the core has no heap
  allocation); leak detection cannot operate there.
- `php dev/qa/yunobot_snapshot.php`: drafts, archived gallery items and internal
  integration fields are excluded from the live public-source snapshot.

Messages are never sent to the source endpoint. The worker fetches public content
once at initialization; unavailable refreshes fall back to bundled content.
