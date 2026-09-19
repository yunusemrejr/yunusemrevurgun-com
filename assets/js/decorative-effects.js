/* Idle-load decoration. The hero supports touch; page effects require WebGPU. */
(() => {
  const current = document.currentScript;
  const canvas = document.querySelector(current.dataset.effectKind === 'page' ? '.ui-page-fx' : '.ui-hero-canvas');
  if (!canvas || navigator.connection?.saveData) return;
  if (current.dataset.effectKind === 'page' && (!navigator.gpu || matchMedia('(prefers-reduced-motion: reduce), (pointer: coarse)').matches)) return;
  const load = () => {
    const script = document.createElement('script');
    script.src = current.dataset.effectSrc;
    script.onload = async () => {
      try {
        if (current.dataset.effectKind === 'page') await window.VgpuPages?.mountPageFx(canvas);
        else {
          await window.VgpuHero?.mountVgpuHero(canvas);
        }
      } catch (_) { canvas.closest('.ui-hero')?.classList.add('ui-hero--fallback'); }
    };
    document.head.append(script);
  };
  if ('requestIdleCallback' in window) requestIdleCallback(load, {timeout: 2500});
  else setTimeout(load, 1000);
})();
