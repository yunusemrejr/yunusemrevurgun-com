const CACHE = 'yev-cache-v1';
const PRECACHE_URLS = [
  '/',
  '/assets/css/ui-rebuild.css',
  '/assets/images/favicon-pfp-32.png',
  '/assets/images/favicon-pfp.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE_URLS))
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  // Skip yunobot paths (has its own SW), workers, and non-GET requests
  if (url.pathname.startsWith('/yunobot') ||
      url.pathname.includes('worker') ||
      url.pathname.includes('embedding') ||
      url.pathname.includes('transformers') ||
      event.request.method !== 'GET') {
    return;
  }
  event.respondWith(
    caches.match(event.request).then((response) => {
      if (response) return response;
      return fetch(event.request).then((fetchResponse) => {
        if (fetchResponse && fetchResponse.status === 200) {
          const clone = fetchResponse.clone();
          caches.open(CACHE).then((cache) => cache.put(event.request, clone));
        }
        return fetchResponse;
      });
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))
    ))
  );
});
