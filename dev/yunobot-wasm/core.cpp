#include "generated.hpp"
#include "text.hpp"
#define API extern "C" __attribute__((visibility("default")))
#ifdef __wasm__
extern "C" void *memcpy(void *d, const void *s, unsigned long n) {
  auto *a = (unsigned char *)d;
  auto *b = (const unsigned char *)s;
  for (unsigned long i = 0; i < n; i++)
    a[i] = b[i];
  return d;
}
extern "C" void *memset(void *d, int c, unsigned long n) {
  auto *a = (unsigned char *)d;
  for (unsigned long i = 0; i < n; i++)
    a[i] = c;
  return d;
}
#endif
static char input[16384], output[16384], source[2048], title[512];
static int resultKind = 0, language = 0, lastDoc = -1, lastChat = -1, turn = 0;
static float confidence = 0;
static bool freshSources = false;
static int vocId(const char *w) {
  for (int i = 0; i < VOCAB; i++)
    if (equal(w, vocab[i]))
      return i;
  return -1;
}
static float probabilities[INTENTS], hidden[DIM];
static int neural(const Tokens &t, float &margin, float &known) {
  for (int d = 0; d < DIM; d++)
    hidden[d] = 0;
  float count = 0;
  known = 0;
  for (int i = 0; i < t.n; i++) {
    const char *w = t.word[i];
    int id = vocId(w);
    if (id >= 0) {
      known++;
      for (int d = 0; d < DIM; d++)
        hidden[d] += ev[id * DIM + d] * evScale[id] * 2;
      count += 2;
    }
    char padded[68] = "<";
    append(padded, w, 68);
    append(padded, ">", 68);
    int n = length(padded);
    for (int j = 0; j < n - 2; j++) {
      char g[5] = {'t', padded[j], padded[j + 1], padded[j + 2], 0};
      U b = hash(g) % BUCKETS;
      for (int d = 0; d < DIM; d++)
        hidden[d] += eb[b * DIM + d] * ebScale[b];
      count++;
    }
    if (i) {
      char pair[132] = "w";
      append(pair, t.word[i - 1], 132);
      append(pair, " ", 132);
      append(pair, w, 132);
      U b = hash(pair) % BUCKETS;
      for (int d = 0; d < DIM; d++)
        hidden[d] += eb[b * DIM + d] * ebScale[b];
      count++;
    }
  }
  known /= t.n ? t.n : 1;
  for (int d = 0; d < DIM; d++)
    hidden[d] /= count ? count : 1;
  float max = -1e30f;
  int best = 0;
  for (int c = 0; c < INTENTS; c++) {
    float v = bias[c];
    for (int d = 0; d < DIM; d++)
      v += head[c * DIM + d] * hidden[d];
    probabilities[c] = v;
    if (v > max) {
      max = v;
      best = c;
    }
  }
  float sum = 0;
  for (int c = 0; c < INTENTS; c++) {
    probabilities[c] = expApprox(probabilities[c] - max);
    sum += probabilities[c];
  }
  float second = 0;
  for (int c = 0; c < INTENTS; c++) {
    probabilities[c] /= sum;
    if (c != best && probabilities[c] > second)
      second = probabilities[c];
  }
  margin = probabilities[best] - second;
  return best;
}
static float chatProbs[CHATS + 1];
// Conversation classifier: hashed word, bigram and character-trigram features
// into a 96-unit ReLU layer (int8 weights, per-feature scale), then softmax.
static int chatClass(const Tokens &t, float &prob, float &margin) {
  static float features[MLP_F];
  for (float &v : features)
    v = 0;
  for (int i = 0; i < t.n; i++) {
    char w[70] = "W";
    append(w, t.word[i], 70);
    features[hash(w) % MLP_F] += 2;
    if (i) {
      char pair[136] = "B";
      append(pair, t.word[i - 1], 136);
      append(pair, " ", 136);
      append(pair, t.word[i], 136);
      features[hash(pair) % MLP_F] += 2;
    }
    char p[68] = "<";
    append(p, t.word[i], 68);
    append(p, ">", 68);
    for (int k = 0; k < length(p) - 2; k++) {
      char g[5] = {'C', p[k], p[k + 1], p[k + 2], 0};
      features[hash(g) % MLP_F]++;
    }
  }
  float norm = 0;
  for (float v : features)
    norm += v * v;
  norm = __builtin_sqrtf(norm);
  if (norm == 0) {
    prob = margin = 0;
    return CHATS;
  }
  float h[MLP_H];
  for (int j = 0; j < MLP_H; j++)
    h[j] = mlpB1[j];
  for (int k = 0; k < MLP_F; k++)
    if (features[k]) {
      float v = features[k] / norm * mlpScale[k];
      for (int j = 0; j < MLP_H; j++)
        h[j] += v * mlpW1[k * MLP_H + j];
    }
  for (int j = 0; j < MLP_H; j++)
    if (h[j] < 0)
      h[j] = 0;
  float max = -1e30f;
  int best = 0;
  for (int c = 0; c <= CHATS; c++) {
    float v = mlpB2[c];
    for (int j = 0; j < MLP_H; j++)
      v += mlpW2[c * MLP_H + j] * h[j];
    chatProbs[c] = v;
    if (v > max) {
      max = v;
      best = c;
    }
  }
  float sum = 0;
  for (int c = 0; c <= CHATS; c++) {
    chatProbs[c] = expApprox(chatProbs[c] - max);
    sum += chatProbs[c];
  }
  float second = 0;
  for (int c = 0; c <= CHATS; c++) {
    chatProbs[c] /= sum;
    if (c != best && chatProbs[c] > second)
      second = chatProbs[c];
  }
  prob = chatProbs[best];
  margin = prob - second;
  return best;
}
static int detectLanguage(const Tokens &t) {
  int en = 0, tr = (contains(input, "ı") || contains(input, "ğ") ||
                    contains(input, "ş") || contains(input, "ç") ||
                    contains(input, "ü") || contains(input, "ö") ||
                    contains(input, "İ"))
                       ? 1
                       : 0;
  for (int i = 0; i < t.n; i++) {
    Tokens one;
    one.n = 1;
    copy(one.word[0], t.word[i], 64);
    en += any(
        one, "hello hey hi what whats your name how are you thanks please find "
             "tell about my is explain good bye feeling doing tired bored");
    tr += any(
        one,
        "selam merhaba kanka dostum naber nasilsin nasil nedir ne adin ismin "
        "sen senin iyiyim sagol eyvallah tesekkurler evet hayir tamam olur "
        "bana bir benim bugun yorgunum sikildim turkce nerede hangi projeleri "
        "yazilari anlat seyahat galeri guncellemeler hakkinda");
  }
  // Learned evidence: a naive-Bayes log-odds table over hashed tokens.
  int nbEn = 0, nbTr = 0;
  for (int i = 0; i < t.n; i++) {
    char w[70] = "L";
    append(w, t.word[i], 70);
    int score = langScore[hash(w) & 4095];
    if (score <= -24)
      nbEn++;
    else if (score >= 24)
      nbTr++;
  }
  // The marker words above decide first; the learned table only speaks when
  // they saw nothing, which is where unfamiliar Turkish used to fall back to
  // English.
  if (!en && !tr) {
    if (nbEn >= 2 && nbTr >= 2)
      return 2;
    if (nbTr > nbEn)
      return 1;
    if (nbEn > nbTr)
      return 0;
    return language;
  }
  if (en && tr)
    return 2;
  if (tr)
    return 1;
  if (en)
    return 0;
  return language;
}
static const char *pick(const char *en, const char *tr,
                        const char *mix = nullptr) {
  return language == 1 ? tr : language == 2 && mix ? mix : en;
}
static void answer(int kind, const char *text) {
  resultKind = kind;
  copy(output, text, sizeof(output));
}
static int unknownTurn = 0, lastJokeTurn = -9, replyCounter[CHATS];
static char userName[40];
// One reply from a class's rotating pool. '?' variants need the user's name,
// '!' variants need it to be unknown; {name} is replaced.
static void chatReply(int chat, char *dst, int cap) {
  const char *const *pool = language == 1   ? replyPool_tr
                            : language == 2 ? replyPool_mix
                                            : replyPool_en;
  const int *start = language == 1   ? replyStart_tr
                     : language == 2 ? replyStart_mix
                                     : replyStart_en;
  const int *count = language == 1   ? replyCount_tr
                     : language == 2 ? replyCount_mix
                                     : replyCount_en;
  int n = count[chat], st = start[chat], chosen = -1;
  if (userName[0])
    for (int k = 0; k < n && chosen < 0; k++) {
      int v = (replyCounter[chat] + k) % n;
      if (pool[st + v][0] == '?')
        chosen = v;
    }
  for (int k = 0; k < n && chosen < 0; k++) {
    int v = (replyCounter[chat] + k) % n;
    char c = pool[st + v][0];
    if ((c == '?' && !userName[0]) || (c == '!' && userName[0]))
      continue;
    chosen = v;
  }
  if (chosen < 0)
    chosen = 0;
  replyCounter[chat] = (chosen + 1) % n;
  const char *text = pool[st + chosen];
  if (text[0] == '?' || text[0] == '!')
    text++;
  int o = 0;
  for (; *text && o < cap - 1; text++) {
    if (text[0] == '{' && text[1] == 'n' && text[2] == 'a' && text[3] == 'm' &&
        text[4] == 'e' && text[5] == '}') {
      for (const char *u = userName; *u && o < cap - 1; u++)
        dst[o++] = *u;
      text += 5;
    } else
      dst[o++] = *text;
  }
  dst[o] = 0;
}
// "my name is X", "call me X", "adim X", "bana X de": X is copied from the
// original text so diacritics survive.
static bool extractName(char *out, int cap) {
  char words[24][48];
  char folded[24][48];
  int n = 0;
  for (const char *p = input; *p && n < 24;) {
    while (*p && !((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') ||
                   (unsigned char)*p >= 128))
      p++;
    int k = 0;
    while (*p && ((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') ||
                  (unsigned char)*p >= 128) &&
           k < 47)
      words[n][k++] = *p++;
    words[n][k] = 0;
    if (k) {
      normalize(words[n], folded[n], 48);
      n++;
    }
  }
  const char *stopWords = " a an the not so very just here fine good ok okay "
                          "tired sad happy sorry ";
  int at = -1;
  for (int i = 0; i < n && at < 0; i++) {
    const char *w = folded[i];
    if (equal(w, "name") && i + 2 < n &&
        (equal(folded[i + 1], "is") || equal(folded[i + 1], "s")))
      at = i + 2;
    else if (equal(w, "call") && i + 2 < n && equal(folded[i + 1], "me"))
      at = i + 2;
    else if (equal(w, "called") && i + 1 < n)
      at = i + 1;
    else if (equal(w, "by") && i && equal(folded[i - 1], "go") && i + 1 < n)
      at = i + 1;
    else if ((equal(w, "adim") || equal(w, "ismim")) && i + 1 < n)
      at = i + 1;
    else if (equal(w, "bana") && i + 2 < n && folded[i + 2][0] == 'd' &&
             folded[i + 2][1] == 'e')
      at = i + 1;
  }
  if (at < 0 && n >= 1 && n <= 2 && !equal(folded[0], "i"))
    at = -1;
  if (at < 0)
    return false;
  char probe[52] = " ";
  append(probe, folded[at], 52);
  append(probe, " ", 52);
  if (contains(stopWords, probe) || length(words[at]) < 2)
    return false;
  copy(out, words[at], cap);
  if (out[0] >= 'a' && out[0] <= 'z')
    out[0] -= 32;
  return true;
}
static void unknown() {
  if (unknownTurn++ % 3 == 1)
    return answer(
        0, pick("Hmm, I’m not sure I caught that. Could you say it another "
                "way? I’m best with this site’s projects and posts, and with "
                "small talk.",
                "Hmm, bunu tam anlayamadım. Başka türlü söyler misin? En iyi "
                "olduğum konular sitedeki projeler, yazılar ve sohbet."));
  if (unknownTurn % 3 == 0)
    return answer(
        0, pick("That one’s outside what I can answer reliably. Try asking "
                "about a project, a post, or just chat with me.",
                "Bu, güvenilir cevap verebileceğim konuların dışında. Bir "
                "proje ya da yazıyı sorabilir ya da sohbet edebilirsin."));
  answer(
      0,
      pick("I don’t have a reliable answer for that. We can chat, or you can "
           "ask about a published project, post, update or place on this site.",
           "Buna güvenilir bir yanıtım yok. Sohbet edebilir ya da sitedeki bir "
           "proje, yazı, güncelleme veya yer hakkında konuşabiliriz.",
           "Buna güvenilir bir yanıtım yok. We can chat, or explore a project, "
           "post, or place on the site."));
}
struct Term {
  U key;
  float tf;
  bool body;
};
struct Doc {
  int text, url, title, page;
  Term terms[320];
  int count, words;
};
static char pool[10 * 1024 * 1024];
static Doc docs[4096];
static bool seenDocs[4096];
static int used = 1, docCount = 0;
static float avgWords = 1;
static int save(const char *s) {
  int n = length(s) + 1;
  if (used + n > (int)sizeof(pool))
    return 0;
  int p = used;
  copy(pool + p, s, n);
  used += n;
  return p;
}
static const char *stop =
    "a an the this that those these of in on at to for from by with and or but "
    "is are was were be been being do does did has have had i me my you your "
    "he him his she her they their it its we our who what when where why how "
    "which can could would should will tell show please about know more some "
    "any as also yunus emre vurgun yemre site website article post write "
    "writes writing written say says said think thinks explain question find "
    "want like give read learn using use used work works many much go got went "
    "come came get turkish english bana bir bu su ve veya ile mi mu nedir "
    "nasil hangi kim ne onun yunusun hakkinda bilgi misin anlat soyler soyle "
    "hey kanka selam merhaba ondan bunun onun biraz daha what whats yapildi "
    "yapilmis";
static void stem(char *w) {
  int n = length(w);
  if (n > 5 && equal(w + n - 3, "ies")) {
    w[n - 3] = 'y';
    w[n - 2] = 0;
  } else if (n > 5 && equal(w + n - 3, "ing"))
    w[n - 3] = 0;
  else if (n > 4 && equal(w + n - 2, "ed"))
    w[n - 2] = 0;
  else if (n > 4 && w[n - 1] == 's')
    w[n - 1] = 0;
}
static Tokens contentTokens(const char *s) {
  Tokens all = tokenize(s), r;
  static Tokens stops = tokenize(stop);
  for (int i = 0; i < all.n; i++)
    if (length(all.word[i]) > 1 && !has(stops, all.word[i])) {
      copy(r.word[r.n], all.word[i], 64);
      stem(r.word[r.n]);
      r.n++;
    }
  return r;
}
static void indexField(Doc &d, const char *s, float boost) {
  Tokens t = contentTokens(s);
  for (int i = 0; i < t.n; i++) {
    U key = hash(t.word[i]);
    int j = 0;
    for (; j < d.count; j++)
      if (d.terms[j].key == key) {
        d.terms[j].tf += boost;
        if (boost == 1)
          d.terms[j].body = true;
        break;
      }
    if (j == d.count && d.count < 320)
      d.terms[d.count++] = {key, boost, boost == 1};
  }
}
static const char *synonym(const char *w) {
  if (equal(w, "projeleri") || equal(w, "proje") || equal(w, "projeler") ||
      equal(w, "built"))
    return "project";
  if (equal(w, "yazilari") || equal(w, "yazi") || equal(w, "makale"))
    return "blog";
  if (equal(w, "guncelleme") || equal(w, "guncellemeler"))
    return "update";
  if (equal(w, "galeri") || equal(w, "fotograflar") || equal(w, "fotograf"))
    return "gallery";
  if (equal(w, "seyahat") || equal(w, "geziler") || equal(w, "gezdigi"))
    return "travel";
  if (equal(w, "ulkeler") || equal(w, "ulke") || equal(w, "countrie"))
    return "country";
  if (equal(w, "dilleri") || equal(w, "dillerle") || equal(w, "diller") ||
      equal(w, "dil") || equal(w, "language"))
    return "language";
  if (equal(w, "egitim") || equal(w, "universite"))
    return "university";
  if (equal(w, "nasil") || equal(w, "calisiyor"))
    return "work";
  return w;
}
static int retrieve(const char *q, bool follow, float &coverage) {
  Tokens t = contentTokens(q);
  U keys[64];
  int groups = 0;
  for (int i = 0; i < t.n && groups < 60; i++) {
    const char *word = synonym(t.word[i]);
    U key = hash(word);
    bool duplicate = false;
    for (int j = 0; j < groups; j++)
      if (keys[j] == key)
        duplicate = true;
    if (!duplicate)
      keys[groups++] = key;
  }
  if (!groups && !follow)
    return -1;
  float idf[64] = {};
  for (int k = 0; k < groups; k++) {
    int df = 0;
    for (int x = 0; x < docCount; x++) {
      for (int j = 0; j < docs[x].count; j++)
        if (docs[x].terms[j].key == keys[k]) {
          df++;
          break;
        }
    }
    idf[k] = logApprox(1 + (docCount - df + .5f) / (df + .5f));
  }
  float bestScore = 0;
  int best = -1;
  coverage = 0;
  for (int d = 0; d < docCount; d++) {
    Doc &row = docs[d];
    if (follow && lastDoc >= 0 &&
        (!equal(pool + row.url, pool + docs[lastDoc].url) || seenDocs[d]))
      continue;
    float score = 0;
    int hits = 0, bodyHits = 0;
    for (int k = 0; k < groups; k++) {
      float tf = 0;
      for (int j = 0; j < row.count; j++)
        if (row.terms[j].key == keys[k]) {
          tf = row.terms[j].tf;
          if (row.terms[j].body)
            bodyHits++;
        }
      if (tf) {
        score += idf[k] * tf * 2.2f /
                 (tf + 1.2f * (.25f + .75f * row.words / avgWords));
        hits++;
      }
    }
    float cov = groups ? float(hits) / groups : 1;
    if (follow && !groups)
      score = 1;
    if (groups && (cov < .45f || bodyHits == 0))
      continue;
    score += bodyHits * 4;
    Tokens qt = tokenize(q), bt = tokenize(pool + row.text);
    if (any(qt, "what nedir anlat") &&
        any(bt, "application software server database algorithm tuning"))
      score += 8;
    if (contains(pool + row.url, "/blog/") ||
        contains(pool + row.url, "/updates/"))
      score += 1.5f;
    if (length(pool + row.text) < 60)
      score *= .4f;
    // Title matches favor actual subjects; low-evidence body matches must not
    // fabricate answers.
    Tokens tt = contentTokens(pool + row.title);
    for (int i = 0; i < tt.n; i++)
      for (int k = 0; k < groups; k++)
        if (hash(tt.word[i]) == keys[k])
          score += 2;
    if (score > bestScore) {
      bestScore = score;
      best = d;
      coverage = cov;
    }
  }
  return bestScore >= 1 ? best : -1;
}
static void excerpt(int d) {
  seenDocs[d] = true;
  lastChat = -1;
  lastDoc = d;
  Doc &row = docs[d];
  answer(3, pool + row.text);
  copy(source, pool + row.url, sizeof(source));
  copy(title, pool + row.title, sizeof(title));
  confidence = .75f;
}
static void fact(int i) {
  lastChat = -1;
  answer(intentTypes[i] == 0 ? 6 : 2, language == 1 ? factsTr[i] : factsEn[i]);
  if (intentTypes[i] == 0) {
    copy(output, pick("Open ", "Sayfayı aç: ", "Şuradan aç: "), sizeof(output));
    append(output, factTitles[i], sizeof(output));
  }
  if (language == 2 && intentTypes[i] == 1) {
    char mixed[16384] = "Kaynağa göre: ";
    append(mixed, output, sizeof(mixed));
    copy(output, mixed, sizeof(output));
  }
  copy(source, factPaths[i], sizeof(source));
  copy(title, factTitles[i], sizeof(title));
  lastDoc = -1;
}
// Bounded arithmetic grammar; no evaluation of JavaScript or arbitrary code.
struct Calculator {
  const char *p;
  int depth = 0;
  bool ok = true;
  void ws() {
    while (*p == ' ')
      p++;
  }
  double atom() {
    ws();
    if (++depth > 24) {
      ok = false;
      return 0;
    }
    double v = 0;
    if (*p == '+' || *p == '-') {
      bool minus = *p++ == '-';
      v = atom();
      if (minus)
        v = -v;
    } else if (*p == '(') {
      p++;
      v = sum();
      ws();
      if (*p == ')')
        p++;
      else
        ok = false;
    } else {
      bool digit = false;
      while (*p >= '0' && *p <= '9') {
        digit = true;
        v = v * 10 + *p++ - '0';
      }
      if (*p == '.') {
        p++;
        double scale = .1;
        while (*p >= '0' && *p <= '9') {
          digit = true;
          v += (*p++ - '0') * scale;
          scale *= .1;
        }
      }
      if (!digit)
        ok = false;
    }
    depth--;
    return v;
  }
  double product() {
    double v = atom();
    ws();
    while (ok && (*p == '*' || *p == '/')) {
      char c = *p++;
      double b = atom();
      if (c == '*')
        v *= b;
      else if (b != 0)
        v /= b;
      else
        ok = false;
      ws();
    }
    return v;
  }
  double sum() {
    double v = product();
    ws();
    while (ok && (*p == '+' || *p == '-')) {
      char c = *p++;
      double b = product();
      v = c == '+' ? v + b : v - b;
      ws();
    }
    return v;
  }
};
static void number(double v, char *out) {
  if (v < 0) {
    *out++ = '-';
    v = -v;
  }
  auto whole = (unsigned long long)v;
  char rev[24];
  int n = 0;
  do {
    rev[n++] = char('0' + whole % 10);
    whole /= 10;
  } while (whole);
  while (n)
    *out++ = rev[--n];
  double frac = v - (unsigned long long)v;
  if (frac > .0000001) {
    *out++ = '.';
    for (int k = 0; k < 6 && frac > .0000001; k++) {
      frac *= 10;
      int digit = (int)frac;
      *out++ = '0' + digit;
      frac -= digit;
    }
  }
  *out = 0;
}
API char *input_ptr() { return input; }
API int input_capacity() { return sizeof(input); }
API char *result_ptr() { return output; }
API char *source_ptr() { return source; }
API char *title_ptr() { return title; }
API int result_kind() { return resultKind; }
API int result_language() { return language; }
API float result_confidence() { return confidence; }
API void reset() {
  for (bool &seen : seenDocs)
    seen = false;
  lastDoc = -1;
  lastChat = -1;
  language = 0;
  turn = 0;
  unknownTurn = 0;
  lastJokeTurn = -9;
  userName[0] = 0;
  for (int &c : replyCounter)
    c = 0;
}
API void clear_documents() {
  docCount = 0;
  used = 1;
  avgWords = 1;
  lastDoc = -1;
}
// Input ABI: page\0url\0title\0text\0, bounded by one input buffer.
API int add_document(int bytes) {
  if (bytes <= 0 || bytes > (int)sizeof(input) || docCount >= 4096)
    return 0;
  int positions[4] = {0}, n = 1;
  for (int i = 0; i < bytes; i++)
    if (!input[i] && n < 4)
      positions[n++] = i + 1;
  if (n != 4 || input[bytes - 1] != 0 || used + bytes > (int)sizeof(pool))
    return 0;
  Doc &d = docs[docCount];
  d.page = save(input);
  d.url = save(input + positions[1]);
  d.title = save(input + positions[2]);
  d.text = save(input + positions[3]);
  d.count = 0;
  d.words = contentTokens(pool + d.text).n;
  indexField(d, pool + d.text, 1);
  indexField(d, pool + d.title, 3);
  indexField(d, pool + d.page, .5f);
  avgWords = (avgWords * docCount + d.words) / (docCount + 1);
  docCount++;
  return 1;
}
API int document_count() { return docCount; }
API void set_fresh_sources(int fresh) { freshSources = fresh != 0; }
API void process(int bytes) {
  output[0] = source[0] = title[0] = 0;
  confidence = 0;
  resultKind = 0;
  if (bytes < 0 || bytes >= 8001) {
    answer(7, "Please keep the question under 2,000 characters.");
    return;
  }
  input[bytes] = 0;
  Tokens t = tokenize(input);
  if (!t.n) {
    unknown();
    return;
  }
  language = detectLanguage(t);
  turn++;
  char norm[16384];
  normalize(input, norm, sizeof(norm));
  if (any(t,
          "salary married wife husband password citizenship maas evli sifre") ||
      contains(norm, "phone number") ||
      contains(norm, "ignore your instructions") ||
      contains(norm, "invent an answer")) {
    unknown();
    return;
  }
  if ((has(t, "time") && t.n < 9) || (has(t, "saat") && t.n < 5)) {
    answer(4, pick("Your device’s current time: ", "Cihazının yerel saati: "));
    return;
  }
  const char *expr = norm;
  const char *prefixes[] = {"calculate ", "compute ", "what is ", "what's ",
                            "hesapla "};
  for (const char *prefix : prefixes)
    if (contains(norm, prefix) && length(norm) >= length(prefix) &&
        ([](const char *a, const char *b) {
          for (int i = 0; b[i]; i++)
            if (a[i] != b[i])
              return false;
          return true;
        })(norm, prefix)) {
      expr = norm + length(prefix);
      break;
    }
  bool math = false, valid = true;
  for (const char *p = expr; *p; p++) {
    if (*p >= '0' && *p <= '9')
      math = true;
    else if (*p != ' ' && *p != '.' && *p != '+' && *p != '-' && *p != '*' &&
             *p != '/' && *p != '(' && *p != ')' && *p != '?' && *p != '=')
      valid = false;
  }
  if (math && valid) {
    Calculator c{expr};
    double v = c.sum();
    c.ws();
    if (*c.p == '?' || *c.p == '=')
      c.p++;
    if (!c.ok || *c.p || !__builtin_isfinite(v) || v > 1e15 || v < -1e15) {
      answer(5, pick("I couldn’t evaluate that expression. Check the "
                     "parentheses and division by zero.",
                     "İfadeyi hesaplayamadım. Parantezleri ve sıfıra bölmeyi "
                     "kontrol et."));
      return;
    }
    answer(5, expr);
    append(output, " = ", sizeof(output));
    char digits[64];
    number(v, digits);
    append(output, digits, sizeof(output));
    confidence = 1;
    return;
  }
  // Explicit navigation remains deterministic, including polite and Turkish
  // forms.
  const char *navWords =
      "open go to take me the page please show visit navigate bring ac gotur "
      "goster sayfa sayfasini home about portfolio projects gallery blog "
      "travel updates music videos downloads comedy contact more yunobot "
      "galeri seyahat projeler hakkinda iletisim ana sayfa";
  bool navigation = t.n <= 8;
  Tokens allowed = tokenize(navWords);
  for (int i = 0; i < t.n; i++)
    if (!has(allowed, t.word[i]))
      navigation = false;
  if (navigation) {
    const char *target = any(t, "gallery galeri")                ? "gallery"
                         : any(t, "portfolio projects projeler") ? "portfolio"
                         : any(t, "travel seyahat")              ? "travel"
                         : any(t, "about hakkinda")              ? "about"
                         : any(t, "contact iletisim")            ? "contact"
                                                                 : nullptr;
    if (!target)
      for (int i = 0; i < INTENTS; i++)
        if (intentTypes[i] == 0 && has(t, intentNames[i])) {
          target = intentNames[i];
          break;
        }
    if (target)
      for (int i = 0; i < INTENTS; i++)
        if (intentTypes[i] == 0 && equal(intentNames[i], target)) {
          fact(i);
          confidence = 1;
          return;
        }
  }
  float cp, cm;
  int chat = chatClass(t, cp, cm);
  bool subject = any(t, "yunus yemre graphy finetuneyuno blog portfolio "
                        "gallery travel updates project projects article post "
                        "galeri seyahat proje projeleri guncellemeler");
  // A confident site-fact match outranks a weak conversational guess.
  float factMargin, factKnown;
  int factIntent = neural(t, factMargin, factKnown);
  bool factual = intentTypes[factIntent] != 2 &&
                 probabilities[factIntent] >= .6f && factMargin >= .3f &&
                 factKnown >= .5f;
  bool gated = chat < CHATS && cp > chatMinProb &&
               (cm > chatMinMargin || cp > chatSureProb) && !subject &&
               !(factual && cp < .6f);
  if (gated && equal(chatNames[chat], "riddle_giveup") &&
      !(lastChat >= 0 && equal(chatNames[lastChat], "play_game")))
    gated = false;
  if (gated) {
    if (equal(chatNames[chat], "correction"))
      lastDoc = -1;
    if (equal(chatNames[chat], "language")) {
      if (has(t, "turkce") && !has(t, "english") && !has(t, "ingilizce") &&
          !has(t, "mixed"))
        language = 1;
      else if (has(t, "english") && !has(t, "turkce"))
        language = 0;
    }
    if (equal(chatNames[chat], "name_set"))
      extractName(userName, sizeof(userName));
    int use = chat;
    // "another one?" -> "yes" hands out the next joke.
    if (equal(chatNames[chat], "joke"))
      lastJokeTurn = turn;
    if (equal(chatNames[chat], "yes") && lastChat >= 0 &&
        (equal(chatNames[lastChat], "laugh") ||
         (equal(chatNames[lastChat], "lol") && turn - lastJokeTurn <= 4)) &&
        any(t, "yes evet yeah olur tamam sure please lutfen yep")) {
      for (int i = 0; i < CHATS; i++)
        if (equal(chatNames[i], "joke"))
          use = i;
    }
    char reply[2048];
    chatReply(use, reply, sizeof(reply));
    // Short follow-ups ("why?", "and you?") answer differently after some
    // topics.
    if (lastChat >= 0)
      for (int k = 0; k < AFTERS; k++)
        if (afterChat[k] == chat && afterPrev[k] == lastChat)
          copy(reply, language == 1 ? afterTr[k] : afterEn[k], sizeof(reply));
    answer(1, reply);
    if (equal(chatNames[chat], "name") &&
        any(t, "hey hi selam merhaba kanka")) {
      char full[2048] = "";
      copy(full,
           has(t, "kanka")
               ? pick("Hey kanka! ", "Selam kanka! ", "Selam kanka! ")
               : pick("Hey! ", "Selam! ", "Selam! "),
           sizeof(full));
      append(full, reply, sizeof(full));
      copy(output, full, sizeof(output));
    }
    if (equal(chatNames[chat], "yes") && lastChat >= 0 &&
        any(t, "yes evet yeah olur tamam")) {
      if (equal(chatNames[lastChat], "sad"))
        answer(1, pick("I’m listening. What happened?", "Dinliyorum. Ne oldu?",
                       "I’m listening. Ne oldu?"));
      if (equal(chatNames[lastChat], "bored") ||
          equal(chatNames[lastChat], "tired"))
        answer(1, pick("Let’s pick something: coding, a project, or travel?",
                       "Bir konu seçelim: kodlama, proje veya seyahat?",
                       "Coding, a project, or seyahat — hangisi?"));
    }
    lastChat = chat;
    confidence = cp;
    return;
  }
  bool follow = any(t, "it its bunun onun o") ||
                (lastDoc >= 0 && any(t, "diller dilleri dillerle")) ||
                contains(norm, "tell me more") ||
                contains(norm, "daha fazla") || contains(norm, "biraz daha") ||
                contains(norm, "devam et");
  float cov = 0;
  if (follow && lastDoc >= 0) {
    int d = retrieve(input, true, cov);
    if (d >= 0) {
      excerpt(d);
      return;
    }
    answer(7,
           pick("I couldn’t find that detail in this source. Which aspect do "
                "you mean?",
                "Bu ayrıntıyı kaynakta bulamadım. Hangi kısmı kastediyorsun?"));
    copy(source, pool + docs[lastDoc].url, sizeof(source));
    copy(title, pool + docs[lastDoc].title, sizeof(title));
    return;
  }
  if (follow && !subject && lastDoc < 0) {
    answer(7, pick("Which project or topic do you mean?",
                   "Hangi proje veya konuyu kastediyorsun?",
                   "Which topic — hangisini kastediyorsun?"));
    return;
  }
  float margin, known;
  int intent = neural(t, margin, known);
  if (any(t, "age yasinda yasi") && any(t, "his he yunus yemre")) {
    for (int i = 0; i < INTENTS; i++)
      if (equal(intentNames[i], "yunus_birth_age")) {
        fact(i);
        return;
      }
  }
  if (any(t, "latest newest recent son guncel") &&
      any(t, "updates guncellemeler blog yazilar posts")) {
    const char *page = any(t, "updates guncellemeler") ? "updates" : "blog";
    if (!freshSources) {
      answer(7, pick("I can’t verify the latest entry from the offline "
                     "snapshot. Open the page for the current list.",
                     "Çevrimdışı kaynaktan en son kaydı doğrulayamıyorum. "
                     "Güncel liste için sayfayı açabilirsin."));
      copy(source, page, sizeof(source));
      copy(title, page, sizeof(title));
      return;
    }
    for (int d = 0; d < docCount; d++)
      if (equal(pool + docs[d].page, page) &&
          contains(pool + docs[d].url,
                   equal(page, "blog") ? "/blog/" : "/updates/")) {
        excerpt(d);
        return;
      }
  }
  if (freshSources && any(t, "which what list hangi neler listele")) {
    const char *collection =
        any(t, "countries destinations trips ulkeler ulkelerde yerler")
            ? "travel"
        : any(t, "projects projeleri projeler")      ? "portfolio"
        : any(t, "photos images fotograflar galeri") ? "gallery"
                                                     : nullptr;
    if (collection) {
      char names[4096] = "";
      int count = 0;
      for (int d = 0; d < docCount && count < 6; d++) {
        if (!equal(pool + docs[d].page, collection) ||
            contains(names, pool + docs[d].title))
          continue;
        if (count)
          append(names, "; ", sizeof(names));
        append(names, pool + docs[d].title, sizeof(names));
        count++;
      }
      if (count) {
        answer(2, pick("Some entries in the public archive: ",
                       "Yayımlanmış arşivden bazı kayıtlar: ",
                       "Public archive’dan birkaç kayıt: "));
        append(output, names, sizeof(output));
        copy(source, collection, sizeof(source));
        copy(title, collection, sizeof(title));
        lastDoc = -1;
        lastChat = -1;
        return;
      }
    }
  }
  bool topic = any(t, "graphy finetuneyuno entropy edge shannon turing nodejs "
                      "kepserver makale yazilari");
  if (topic) {
    int d = retrieve(input, false, cov);
    if (d >= 0) {
      excerpt(d);
      return;
    }
  }
  if (intentTypes[intent] != 2 && probabilities[intent] >= .43f &&
      margin >= .12f && known >= .5f) {
    fact(intent);
    confidence = probabilities[intent];
    return;
  }
  int d = retrieve(input, false, cov);
  if (d >= 0 && known >= .3f && (subject || topic || cov > .8f)) {
    excerpt(d);
    return;
  }
  unknown();
}

// Diagnostic ABI used for differential tests against the previous JS
// classifier.
API int classify_input(int bytes) {
  if (bytes < 0 || bytes >= 8001)
    return -1;
  input[bytes] = 0;
  float margin, known;
  return neural(tokenize(input), margin, known);
}
API float class_probability(int index) {
  return index >= 0 && index < INTENTS ? probabilities[index] : 0;
}

// Diagnostic ABI for chat-parity.mjs: the conversation classifier's top class and probability.
API int chat_classify(int bytes) {
  if (bytes < 0 || bytes >= 8001)
    return -1;
  input[bytes] = 0;
  float p, m;
  return chatClass(tokenize(input), p, m);
}
API float chat_probability(int index) {
  return index >= 0 && index <= CHATS ? chatProbs[index] : 0;
}
