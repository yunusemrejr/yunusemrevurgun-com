/* Decoration never delays content, or downloads on touch/low-data devices. */
(() => {
  const current = document.currentScript;
  const canvas = document.querySelector(current.dataset.effectKind === 'page' ? '.ui-page-fx' : '.ui-hero-canvas');
  if (!canvas || !navigator.gpu || matchMedia('(prefers-reduced-motion: reduce), (pointer: coarse)').matches || navigator.connection?.saveData) return;
  const load = () => {
    const script = document.createElement('script');
    script.src = current.dataset.effectSrc;
    script.onload = async () => {
      try {
        if (current.dataset.effectKind === 'page') await window.VgpuPages?.mountPageFx(canvas);
        else {
          canvas.closest('.ui-hero')?.classList.remove('ui-hero--fallback');
          await window.VgpuHero?.mountVgpuHero(canvas);
        }
      } catch (_) { canvas.closest('.ui-hero')?.classList.add('ui-hero--fallback'); }
    };
    document.head.append(script);
  };
  if ('requestIdleCallback' in window) requestIdleCallback(load, {timeout: 2500});
  else setTimeout(load, 1000);
})();
