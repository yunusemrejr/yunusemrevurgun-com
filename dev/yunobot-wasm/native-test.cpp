// Native sanitizer exercises share exactly the browser core implementation.
#include "core.cpp"
#include <cassert>
int main() {
  unsigned seed = 42;
  for (int i = 0; i < 2000; i++) {
    int n = i % 200;
    for (int j = 0; j < n; j++) {
      seed = 1664525 * seed + 1013904223;
      input_ptr()[j] = (char)(seed >> 24);
    }
    input_ptr()[n] = 0;
    process(n);
    assert(result_ptr()[0]);
  }
  process(-1);
  process(16384);
  assert(!add_document(-1));
  assert(!add_document(16385));
  return 0;
}
