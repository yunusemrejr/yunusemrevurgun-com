// Where this copy of chessko lives. The standalone python server serves everything from the site
// root, so the defaults are "" and "/api". A host page (for example yunusemrevurgun.com/chessko)
// sets window.CHESSKO = { assets: "/assets/chessko", api: "/api/chessko" } before loading app.js.
const host = (typeof globalThis !== "undefined" && globalThis.CHESSKO) || {};

/** URL prefix of js/, css/, data/ and vendor/ (no trailing slash). */
export const ASSETS = String(host.assets ?? "").replace(/\/$/, "");

/** URL prefix of the JSON backend (no trailing slash). */
export const API = String(host.api ?? "/api").replace(/\/$/, "");
