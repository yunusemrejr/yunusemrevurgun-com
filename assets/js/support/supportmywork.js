(function () {
  'use strict';

  var WIDGET_ID = 'yunus-support-widget';
  var DISMISS_KEY = 'yunus_support_widget_dismissed_v2';
  var DISMISS_MS = 1000 * 60 * 60 * 24 * 3;

  if (document.getElementById(WIDGET_ID)) return;

  try {
    var dismissedAt = Number(window.localStorage.getItem(DISMISS_KEY) || '0');
    if (dismissedAt && (Date.now() - dismissedAt) < DISMISS_MS) return;
  } catch (e) {
    // Ignore storage errors in locked-down browser modes.
  }

  var host = document.createElement('div');
  host.id = WIDGET_ID;
  document.body.appendChild(host);

  var shadow = host.attachShadow({ mode: 'closed' });

  shadow.innerHTML = '' +
    '<style>' +
    ':host{' +
    '  position:fixed;' +
    '  right:22px;' +
    '  bottom:22px;' +
    '  z-index:2147483647;' +
    '  font-family:"General Sans", system-ui, -apple-system, "Segoe UI", sans-serif;' +
    '  --s-bg:rgba(0,0,0,0.72);' +
    '  --s-bg-2:rgba(255,255,255,0.06);' +
    '  --s-text:rgba(255,255,255,0.96);' +
    '  --s-text-soft:rgba(255,255,255,0.72);' +
    '  --s-border:rgba(255,255,255,0.44);' +
    '  --s-border-soft:rgba(255,255,255,0.22);' +
    '  --s-glow:rgba(255,255,255,0.22);' +
    '  --s-radius:999px;' +
    '}' +
    '.widget{' +
    '  position:relative;' +
    '  display:flex;' +
    '  align-items:center;' +
    '  gap:8px;' +
    '  animation:widgetIn .7s cubic-bezier(.16,.88,.23,1) both;' +
    '}' +
    '.btn{' +
    '  position:relative;' +
    '  display:inline-flex;' +
    '  align-items:center;' +
    '  gap:10px;' +
    '  padding:11px 16px 11px 12px;' +
    '  border-radius:var(--s-radius);' +
    '  border:0.6px solid var(--s-border);' +
    '  color:var(--s-text);' +
    '  background:linear-gradient(135deg,var(--s-bg),var(--s-bg-2));' +
    '  text-decoration:none;' +
    '  white-space:nowrap;' +
    '  backdrop-filter:blur(12px);' +
    '  -webkit-backdrop-filter:blur(12px);' +
    '  box-shadow:0 10px 26px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.18);' +
    '  transition:transform .22s ease, border-color .22s ease, box-shadow .22s ease;' +
    '}' +
    '.btn::before{' +
    '  content:"";' +
    '  position:absolute;' +
    '  left:18%;' +
    '  right:18%;' +
    '  top:1px;' +
    '  height:1px;' +
    '  background:linear-gradient(90deg,rgba(255,255,255,.85),rgba(255,255,255,0));' +
    '  pointer-events:none;' +
    '}' +
    '.btn:hover{' +
    '  transform:translateY(-2px);' +
    '  border-color:rgba(255,255,255,0.78);' +
    '  box-shadow:0 14px 30px rgba(0,0,0,0.5), 0 0 20px var(--s-glow);' +
    '}' +
    '.icon-wrap{' +
    '  width:38px;' +
    '  height:38px;' +
    '  min-width:38px;' +
    '  border-radius:999px;' +
    '  border:0.6px solid var(--s-border-soft);' +
    '  display:grid;' +
    '  place-items:center;' +
    '  background:rgba(255,255,255,0.1);' +
    '}' +
    '.icon{' +
    '  width:24px;' +
    '  height:24px;' +
    '  stroke:#ffffff;' +
    '  stroke-width:1.7;' +
    '  fill:rgba(255,255,255,0.12);' +
    '  stroke-linecap:round;' +
    '  stroke-linejoin:round;' +
    '  filter:drop-shadow(0 0 4px rgba(255,255,255,0.22));' +
    '}' +
    '.label{' +
    '  font-size:12px;' +
    '  letter-spacing:.04em;' +
    '  font-weight:600;' +
    '  text-transform:uppercase;' +
    '  color:var(--s-text-soft);' +
    '}' +
    '.close{' +
    '  width:22px;' +
    '  height:22px;' +
    '  border-radius:999px;' +
    '  border:0.6px solid var(--s-border-soft);' +
    '  background:rgba(0,0,0,0.74);' +
    '  color:rgba(255,255,255,0.8);' +
    '  cursor:pointer;' +
    '  font-size:12px;' +
    '  line-height:1;' +
    '  display:grid;' +
    '  place-items:center;' +
    '  transition:all .2s ease;' +
    '}' +
    '.close:hover{' +
    '  color:#fff;' +
    '  border-color:rgba(255,255,255,.7);' +
    '  background:rgba(255,255,255,0.08);' +
    '}' +
    '.out{' +
    '  opacity:0;' +
    '  transform:translateY(10px) scale(.92);' +
    '  transition:all .28s ease;' +
    '  pointer-events:none;' +
    '}' +
    '@keyframes widgetIn{' +
    '  from{opacity:0;transform:translateY(14px) scale(.9)}' +
    '  to{opacity:1;transform:translateY(0) scale(1)}' +
    '}' +
    '@media (max-width:680px){' +
    '  :host{right:14px;bottom:14px;}' +
    '  .btn{padding:10px;border-radius:999px;}' +
    '  .label{display:none;}' +
    '}' +
    '@media (prefers-reduced-motion:reduce){' +
    '  .widget,.btn{animation:none !important;transition:none !important;}' +
    '}' +
    '</style>' +
    '<div class="widget" id="widgetRoot">' +
    '  <a class="btn" href="https://buymeacoffee.com/yunusemrevrgn" target="_blank" rel="noopener noreferrer" aria-label="Support Yunus">' +
    '    <span class="icon-wrap" aria-hidden="true">' +
    '      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' +
    '        <path d="M4.8 10h10.7v4.4a3.6 3.6 0 0 1-3.6 3.6H8.4a3.6 3.6 0 0 1-3.6-3.6V10Z"></path>' +
    '        <path d="M15.5 10.8h1.7a2.2 2.2 0 0 1 0 4.4h-1.7"></path>' +
    '        <path d="M3.9 19.3h14.6"></path>' +
    '        <path d="M7.6 6.3c-.7.8-.7 1.7 0 2.5"></path>' +
    '        <path d="M10.9 5.4c-.9 1-.9 2.2 0 3.2"></path>' +
    '      </svg>' +
    '    </span>' +
    '    <span class="label">Support My Work</span>' +
    '  </a>' +
    '  <button class="close" id="dismissBtn" type="button" aria-label="Dismiss">x</button>' +
    '</div>';

  var root = shadow.getElementById('widgetRoot');
  var dismissBtn = shadow.getElementById('dismissBtn');

  dismissBtn.addEventListener('click', function (event) {
    event.preventDefault();
    root.classList.add('out');
    try {
      window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch (e) {
      // Ignore storage errors.
    }
    window.setTimeout(function () {
      host.remove();
    }, 280);
  });
})();
