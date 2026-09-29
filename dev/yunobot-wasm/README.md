# YunoBot C++ / WebAssembly

The browser runs `core.wasm` inside a dedicated worker. JavaScript handles downloads,
UTF-8 transport, the DOM and the device clock. Tokenization, Turkish case folding,
feature extraction, both classifiers, source ranking, response selection/composition,
arithmetic and dialogue state run in C++.

The core combines the existing 96-dimensional int8 embedding-bag neural classifier
(reviewed site facts) with a conversational network: hashed word, bigram and
character-trigram features (4,096 buckets, L2-normalised) into a 96-unit ReLU layer
(int8 weights with a per-feature scale) and a softmax over 82 conversational classes
plus one out-of-scope class, about 401k parameters. It is trained by `build.mjs`
(seeded Adam, dropout, label smoothing, class weights, augmentation with typos,
contractions, code-switching prefixes and suffixes) on `conversation.json`: about
2,000 English, Turkish and mixed example sentences and 330 held-out paraphrases
that the reported run never sees. A second run over everything ships. Out-of-scope
examples are the site-fact questions plus general questions the bot should decline.

`conversation.json` fields per class: `q_en` / `q_tr` (tagged training sentences),
`samples` (older untagged ones), `heldout`, and reply lists `en` / `tr` / `mix`.
Replies rotate through their variants, so a repeated intent does not repeat the
sentence. A reply starting `?` is used only once the user has given a name and `!`
only before that; `{name}` is replaced. `after` maps a previous class to a different
reply for short follow-ups ("why?", "and you?"). The dialogue state keeps the last
class, the last source, the user's name (from "my name is X", "adım X", "bana X de";
only for this conversation) and a joke/riddle context. Language identification is the
older marker words plus a learned naive-Bayes token table that decides when the
markers see nothing. A confident site-fact match outranks a weak conversational
guess. Facts remain reviewed source-linked text, not learned biography. This is not
a general-purpose generative LLM or an automatic translator.

`metrics.json` (written by every build) reports honest numbers from the run that
never saw the held-out sentences: top-1 accuracy on unseen paraphrases about 78%,
and at the confidence gate the bot accepts about 198 of 330 unseen paraphrases with
about 92% precision, wrongly accepting 3 of 92 out-of-scope questions. Everything it
does not accept falls through to the site-fact and retrieval paths or an honest
"I'm not sure" reply. More example sentences per class is what raises these numbers.

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
node dev/yunobot-wasm/chat-parity.mjs
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
- `chat-parity.mjs`: the C++ conversational network reproduces the JS reference forward pass written by the build (`chat-reference.json`, generated).
- `differential.mjs`: compares C++ probabilities with the previous JS neural model.
- `native-test.cpp`: 2,000 byte-input cases under AddressSanitizer/UBSan. Build using
  `clang++ -std=c++17 -O1 -g -fsanitize=address,undefined dev/yunobot-wasm/native-test.cpp -o /tmp/yunobot-native-test`.
  In ptraced environments use `ASAN_OPTIONS=detect_leaks=0` (the core has no heap
  allocation); leak detection cannot operate there.
- `php dev/qa/yunobot_snapshot.php`: drafts, archived gallery items and internal
  integration fields are excluded from the live public-source snapshot.

Messages are never sent to the source endpoint. The worker fetches public content
once at initialization; unavailable refreshes fall back to bundled content.
