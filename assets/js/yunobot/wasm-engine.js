/* UI adapter: Worker lifecycle, asset URLs and platform clock. No NLP in
 * JavaScript. */
((g) => {
  const config = {...document.currentScript.dataset};
  const examples = [
    'hey kanka whats your name?', 'What is Mr. Graphy?',
    'Find writing about edge AI', 'Yunus nerede okudu?'
  ];
  class YunoBotMLEngine {
    constructor() {
      this.workerReady = false;
      this.exampleQuestions = examples;
      this.pending = new Map();
      this.serial = 0;
      this.ready = new Promise((resolve, reject) => {
        this.resolveReady = resolve;
        this.rejectReady = reject;
      });
      this.ready.catch(() => {});
      this.loadTimer = setTimeout(() => {
        if (!this.workerReady) {
          this.worker?.terminate();
          this.fail();
        }
      }, 15000);
      try {
        this.worker = new Worker(config.worker);
        this.worker.onmessage = ({data}) => {
          if (data.type === 'ready') {
            clearTimeout(this.loadTimer);
            this.workerReady = true;
            this.resolveReady();
            this.onReady?.();
            return;
          }
          if (data.type === 'error') {
            if (data.id && this.pending.has(data.id)) {
              this.pending.get(data.id).reject(Error(data.message));
              this.pending.delete(data.id);
            } else
              this.fail();
            return;
          }
          const job = this.pending.get(data.id);
          if (!job)
            return;
          this.pending.delete(data.id);
          const result = data.result;
          const kinds = [
            'unknown', 'chat', 'answer', 'excerpt', 'tool_time',
            'tool_calculator', 'navigation', 'clarify'
          ];
          result.kind = kinds[result.code];
          result.intent = result.code === 3   ? 'knowledge'
                          : result.code === 6 ? 'navigate'
                                              : kinds[result.code];
          if (result.sourceUrl) {
            result.sourceUrl =
                new URL(result.sourceUrl,
                        g.FULL_BASE_PATH || location.origin + '/')
                    .href;
            result.url = result.sourceUrl;
          } else
            delete result.sourceUrl;
          if (result.code === 4)
            result.response += new Date().toLocaleTimeString(
                result.language === 1 ? 'tr-TR' : undefined);
          if (result.code === 3)
            result.sourceLabel = result.language === 1   ? 'Kaynak alıntısı'
                                 : result.language === 2 ? 'Kaynak / source'
                                                         : 'Source excerpt';
          job.resolve(result);
        };
        this.worker.onerror = () => this.fail();
        this.worker.postMessage({type : 'init', ...config});
      } catch {
        this.fail();
      }
    }
    fail() {
      clearTimeout(this.loadTimer);
      this.workerReady = false;
      const error = Error('YunoBot is unavailable. Reload to try again.');
      this.rejectReady(error);
      for (const job of this.pending.values())
        job.reject(error);
      this.pending.clear();
      this.onError?.();
    }
    get isReady() { return this.workerReady; }
    async process(text) {
      if (typeof text !== 'string' || !text.trim())
        return {
          intent : 'unknown',
          response : 'Ask a question to begin.',
          confidence : 0
        };
      if (text.length > 2000)
        return {
          intent : 'clarify',
          response : 'Please keep the question under 2,000 characters.',
          confidence : 0
        };
      await this.ready;
      const id = ++this.serial;
      return new Promise((resolve, reject) => {
        this.pending.set(id, {resolve, reject});
        this.worker.postMessage({type : 'process', id, text});
      });
    }
    reset() { this.worker?.postMessage({type : 'reset'}); }
    dispose() {
      this.worker?.terminate();
      this.fail();
    }
  }
  g.YunoBotMLEngine = YunoBotMLEngine;
})(window);
