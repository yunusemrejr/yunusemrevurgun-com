/* Accessibility for the existing custom upload/edit dialogs; close handlers retain ownership of cancellation. */
(() => {
  let trigger = null;
  const active = [];
  document.addEventListener('click', event => {
    if (!event.target.closest('.admin-upload-modal')) trigger = event.target.closest('button, a[href]') || document.activeElement;
  }, true);
  document.querySelectorAll('.admin-upload-modal').forEach((modal, index) => {
    const title = modal.querySelector('h2, h3, .admin-modal-title');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    if (title) {
      if (!title.id) title.id = 'admin-dialog-title-' + index;
      modal.setAttribute('aria-labelledby', title.id);
    }
    const close = modal.querySelector('.admin-btn-close');
    if (close && !close.getAttribute('aria-label')) close.setAttribute('aria-label', 'Close dialog');
    let visible = false, previousFocus;
    function sync() {
      const isVisible = getComputedStyle(modal).display !== 'none' && getComputedStyle(modal).visibility !== 'hidden';
      if (isVisible === visible) return;
      visible = isVisible;
      modal.setAttribute('aria-hidden', String(!visible));
      if (visible) {
        previousFocus = trigger || document.activeElement;
        active.push(modal);
        if (!modal.contains(document.activeElement)) (modal.querySelector('input:not([type=hidden]), textarea, select, button') || modal).focus();
      } else {
        const i = active.indexOf(modal);
        if (i !== -1) active.splice(i, 1);
        if (previousFocus?.isConnected) previousFocus.focus();
      }
    }
    modal.setAttribute('aria-hidden', 'true');
    new MutationObserver(sync).observe(modal, {attributes: true, attributeFilter: ['style', 'class']});
    sync();
  });
  document.addEventListener('keydown', event => {
    const modal = active[active.length - 1];
    if (!modal) return;
    if (event.key === 'Escape') {
      const close = modal.querySelector('.admin-btn-close');
      if (close && !close.disabled) { event.preventDefault(); event.stopImmediatePropagation(); close.click(); }
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex="0"]')].filter(el => el.getClientRects().length);
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) { event.preventDefault(); first?.focus(); }
  }, true);
})();
