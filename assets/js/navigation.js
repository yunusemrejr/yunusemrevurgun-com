/* Shared keyboard-accessible drawer for the public site and admin. */
(() => {
  const menu = document.querySelector('#uiMobileMenu, #adminUiMobileMenu');
  if (!menu) return;
  const admin = menu.id === 'adminUiMobileMenu';
  const openButton = document.querySelector(admin ? '[data-admin-menu-open]' : '[data-mobile-menu-open]');
  const closeSelector = admin ? '[data-admin-menu-close]' : '[data-mobile-menu-close]';
  const panel = menu.querySelector(admin ? '.admin-ui-mobile-panel' : '.ui-mobile-panel');
  let previousOverflow = '', previousFocus, background = [];
  menu.inert = true;
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  panel.setAttribute('aria-label', admin ? 'Admin navigation' : 'Site navigation');
  function close() {
    if (!menu.classList.contains('is-open')) return;
    menu.classList.remove('is-open');
    menu.setAttribute('aria-hidden', 'true');
    menu.inert = true;
    openButton?.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = previousOverflow;
    background.forEach(([element, wasInert]) => { element.inert = wasInert; });
    previousFocus?.focus();
  }
  openButton?.addEventListener('click', () => {
    if (menu.classList.contains('is-open')) return close();
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    background = [...document.querySelectorAll('main, header, footer')]
      .filter(element => !element.contains(menu) && !menu.contains(element))
      .map(element => [element, element.inert]);
    background.forEach(([element]) => { element.inert = true; });
    menu.inert = false;
    menu.classList.add('is-open');
    menu.setAttribute('aria-hidden', 'false');
    openButton.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => panel.querySelector('button, a[href]')?.focus());
  });
  menu.querySelectorAll(closeSelector).forEach(element => element.addEventListener('click', close));
  menu.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key !== 'Tab') return;
    const focusable = [...panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')].filter(el => el.getClientRects().length);
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  });
  matchMedia('(min-width: 981px)').addEventListener('change', event => { if (event.matches) close(); });
})();
