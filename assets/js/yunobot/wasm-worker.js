/* Transport only. All inference, retrieval, language choice and state live in
 * C++. */
let core;
const encoder = new TextEncoder(), decoder = new TextDecoder();
function read(ptr) {
  const bytes = new Uint8Array(core.memory.buffer);
  let end = ptr;
  while (end < bytes.length && bytes[end])
    end++;
  return decoder.decode(bytes.subarray(ptr, end));
}
function write(text) {
  const bytes = encoder.encode(text);
  if (bytes.length >= core.input_capacity())
    throw Error('Input exceeds transport capacity');
  new Uint8Array(core.memory.buffer, core.input_ptr(), bytes.length + 1).set([
    ...bytes, 0
  ]);
  return bytes.length;
}
function add(row) {
  if (!row || !['page', 'url', 'title', 'text'].every(
                  k => typeof row[k] === 'string' && !row[k].includes('\0')))
    return;
  const packed =
      [ row.page, row.url, row.heading || row.title, row.text ].join('\0') +
      '\0';
  if (encoder.encode(packed).length >= core.input_capacity())
    return;
  core.add_document(write(packed));
}
async function init(config) {
  const response = await fetch(config.wasm, {credentials : 'omit'});
  if (!response.ok)
    throw Error('Wasm download failed');
  const module =
      await WebAssembly.instantiate(await response.arrayBuffer(), {});
  core = module.instance.exports;
  self.window =
      self; // The data-only knowledge bundle uses the browser global name.
  importScripts(config.corpus);
  let rows = self.YUNOBOT_KB.passages, fresh = false;
  if (config.live) {
    try {
      const url = new URL(config.live, self.location.href);
      if (url.origin !== self.location.origin)
        throw Error('Source origin mismatch');
      const response = await fetch(
          url, {credentials : 'omit', signal : AbortSignal.timeout(3500)});
      if (!response.ok)
        throw Error('Source unavailable');
      const data = await response.json();
      if (data.version !== 2 || !Array.isArray(data.passages) ||
          !Array.isArray(data.publishedUrls))
        throw Error('Invalid source snapshot');
      const scopes = new Set(data.scopes || [ 'blog' ]),
            published = new Set(data.publishedUrls),
            replacement = new Set(data.passages.map(p => p.url));
      rows = rows.filter(p => !scopes.has(p.page) ||
                              published.has(p.url) && !replacement.has(p.url));
      rows.push(...data.passages.filter(
          p => published.has(p.url) && typeof p.url === 'string' &&
               p.url.startsWith(url.origin + '/')));
      fresh = true;
    } catch { /* Offline bundled sources remain available. */
    }
  }
  core.clear_documents();
  for (const row of rows)
    add(row);
  core.reset();
  core.set_fresh_sources(fresh ? 1 : 0);
  postMessage({type : 'ready', documents : core.document_count()});
}
self.onmessage = async ({data}) => {
  try {
    if (data.type === 'init') {
      await init(data);
      return;
    }
    if (!core)
      throw Error('Engine not ready');
    if (data.type === 'reset') {
      core.reset();
      return;
    }
    if (data.type === 'process') {
      const bytes = write(data.text);
      core.process(bytes);
      postMessage({
        id : data.id,
        type : 'answer',
        result : {
          response : read(core.result_ptr()),
          sourceUrl : read(core.source_ptr()),
          sourceTitle : read(core.title_ptr()),
          code : core.result_kind(),
          language : core.result_language(),
          confidence : core.result_confidence()
        }
      });
    }
  } catch (error) {
    postMessage({
      type : 'error',
      id : data.id,
      message :
          'YunoBot could not load or process this message. Please reload to try again.'
    });
  }
};
