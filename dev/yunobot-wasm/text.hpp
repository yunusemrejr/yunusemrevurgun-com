#pragma once
using U = unsigned int;
static int length(const char *s) {
  int n = 0;
  while (s[n])
    n++;
  return n;
}
static bool equal(const char *a, const char *b) {
  while (*a && *a == *b) {
    a++;
    b++;
  }
  return *a == *b;
}
static void copy(char *d, const char *s, int cap) {
  int i = 0;
  for (; i < cap - 1 && s[i]; i++)
    d[i] = s[i];
  d[i] = 0;
}
static void append(char *d, const char *s, int cap) {
  int n = length(d);
  copy(d + n, s, cap - n);
}
static bool contains(const char *s, const char *q) {
  for (; *s; s++) {
    int i = 0;
    while (q[i] && s[i] == q[i])
      i++;
    if (!q[i])
      return true;
  }
  return false;
}
static U hash(const char *s) {
  U h = 2166136261u;
  while (*s)
    h = (h ^ (unsigned char)*s++) * 16777619u;
  return h;
}
// UTF-8 decoding with bounded reads; Latin/Turkish case folding, combining-mark
// removal.
static void normalize(const char *s, char *out, int cap) {
  int n = 0;
  while (*s && n < cap - 1) {
    U c = (unsigned char)*s++;
    if (c >= 192 && c < 224 && *s) {
      c = (c & 31) << 6;
      c |= (unsigned char)*s++ & 63;
    } else if (c >= 224 && c < 240 && s[0] && s[1]) {
      c = (c & 15) << 12;
      c |= ((unsigned char)s[0] & 63) << 6;
      c |= (unsigned char)s[1] & 63;
      s += 2;
    } else if (c >= 240) {
      while (((unsigned char)*s & 192) == 128)
        s++;
      c = ' ';
    }
    if (c >= 0x300 && c <= 0x36f)
      continue;
    if (c >= 'A' && c <= 'Z')
      c += 32;
    switch (c) {
    case 0x130:
    case 0x131:
      c = 'i';
      break;
    case 0x11e:
    case 0x11f:
      c = 'g';
      break;
    case 0x15e:
    case 0x15f:
      c = 's';
      break;
    case 0xc7:
    case 0xe7:
      c = 'c';
      break;
    case 0xd6:
    case 0xf6:
      c = 'o';
      break;
    case 0xdc:
    case 0xfc:
      c = 'u';
      break;
    case 0xe9:
    case 0xc9:
    case 0xe8:
      c = 'e';
      break;
    case 0xe2:
    case 0xe1:
      c = 'a';
      break;
    }
    if (c >= 0xff01 && c <= 0xff5e)
      c -= 0xfee0;
    if (c > 127)
      c = ' ';
    out[n++] = (char)c;
  }
  out[n] = 0;
}
struct Tokens {
  char word[256][64];
  int n = 0;
};
static Tokens tokenize(const char *s) {
  Tokens t;
  char norm[16384];
  normalize(s, norm, sizeof(norm));
  int k = 0;
  bool active = false;
  for (int i = 0;; i++) {
    char c = norm[i];
    bool keep = (c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') || c == '+' ||
                c == '#';
    if (keep && t.n < 256) {
      if (k < 63)
        t.word[t.n][k++] = c;
      active = true;
    } else if (active) {
      t.word[t.n][k] = 0;
      t.n++;
      k = 0;
      active = false;
    }
    if (!c || t.n == 256)
      break;
  }
  return t;
}
static bool has(const Tokens &t, const char *s) {
  for (int i = 0; i < t.n; i++)
    if (equal(t.word[i], s))
      return true;
  return false;
}
static bool any(const Tokens &t, const char *list) {
  Tokens q = tokenize(list);
  for (int i = 0; i < q.n; i++)
    if (has(t, q.word[i]))
      return true;
  return false;
}
static float expApprox(float x) {
  if (x < -24)
    return 0;
  if (x > 0)
    x = 0;
  float v = 1 + x / 4096;
  for (int i = 0; i < 12; i++)
    v *= v;
  return v;
}
static float logApprox(float x) {
  int n = 0;
  while (x > 2) {
    x *= .5f;
    n++;
  }
  float y = (x - 1) / (x + 1), y2 = y * y, v = y, sum = y;
  for (int k = 3; k < 16; k += 2) {
    v *= y2;
    sum += v / k;
  }
  return 2 * sum + n * .69314718f;
}
