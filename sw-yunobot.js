/* YunoBot: cache only its public page/assets. Never cache API or admin traffic. */
const CACHE = 'yunobot-v2-20260908';
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter(name => name.startsWith('yunobot-') && name !== CACHE).map(name => caches.delete(name)));
    await self.clients.claim();
})()));
self.addEventListener('fetch', event => {
    const request=event.request, url=new URL(request.url);
    if(request.method !== 'GET' || url.origin !== self.location.origin) return;
    const page=/\/yunobot\/?$/.test(url.pathname);
    const asset=/\/assets\//.test(url.pathname) && ['script','style','font','image'].includes(request.destination);
    if(!page && !asset) return;
    event.respondWith((async () => {
        const cache=await caches.open(CACHE);
        const stored=await cache.match(request);
        if(asset && stored)return stored;
        try {
            const response=await fetch(request);
            if(response.ok && !/no-store|private/i.test(response.headers.get('Cache-Control')||''))await cache.put(request,response.clone());
            return response;
        } catch(error) {
            if(stored)return stored;
            return new Response(page?'This page is unavailable offline. Reconnect and reload.':'', {status:503,headers:{'Content-Type':'text/plain'}});
        }
    })());
});
