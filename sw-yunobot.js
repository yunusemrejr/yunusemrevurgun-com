/**
 * YunoBot Service Worker - Offline Support & Caching
 * 
 * Features:
 * - Cache JS files for offline use
 * - Fallback responses when offline
 * - Stale-while-revalidate strategy for assets
 * (YunoBot runs on a custom neural network shipped with the page —
 *  no external model downloads to cache.)
 */

const CACHE_NAME = 'yunobot-v1';
const CACHE_VERSION = '20260717';

// Assets to cache immediately
const STATIC_ASSETS = [
    // Core chat page
    './',
    'yunobot',
    
    // JS files
    'assets/js/yunobot/nn-weights.js',
    'assets/js/yunobot/nn-engine.js',
    'assets/js/yunobot/ml-engine.js',
    'assets/js/yunobot.js',
    
    // CSS
    'assets/css/yunobot.css',
];

// ============================================================
// INSTALL - Pre-cache static assets
// ============================================================
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME + '-' + CACHE_VERSION)
            .then((cache) => {
                console.log('[YunoBot SW] Pre-caching static assets');
                return cache.addAll(STATIC_ASSETS.map(path => {
                    // Convert relative paths to absolute
                    return new URL(path, location.href).href;
                })).catch(err => {
                    console.log('[YunoBot SW] Some assets failed to cache:', err);
                    // Don't fail install - partial cache is still useful
                });
            })
            .then(() => {
                console.log('[YunoBot SW] Pre-caching complete');
                return self.skipWaiting();
            })
    );
});

// ============================================================
// ACTIVATE - Clean up old caches
// ============================================================
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name.startsWith(CACHE_NAME) && name !== CACHE_NAME + '-' + CACHE_VERSION)
                        .map((name) => {
                            console.log('[YunoBot SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[YunoBot SW] Activated');
                return self.clients.claim();
            })
    );
});

// ============================================================
// FETCH - Network first with cache fallback for API, 
//         Cache first with network fallback for static assets
// ============================================================
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') return;
    
    // Skip cross-origin requests
    const isSameOrigin = url.origin === location.origin;
    if (!isSameOrigin) return;
    
    // ============================================================
    // Strategy 1: Cache-first for static JS/CSS
    // ============================================================
    if (isSameOrigin && (
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.html')
    )) {
        event.respondWith(
            caches.match(request)
                .then((cachedResponse) => {
                    if (cachedResponse) {
                        // Return cached version immediately
                        // But also fetch fresh version in background
                        fetch(request).then((response) => {
                            if (response.ok) {
                                caches.open(CACHE_NAME + '-' + CACHE_VERSION)
                                    .then((cache) => cache.put(request, response));
                            }
                        }).catch(() => {
                            // Network failed - cached version already returned
                        });
                        return cachedResponse;
                    }
                    
                    // Not cached - fetch from network
                    return fetch(request)
                        .then((response) => {
                            if (!response.ok) throw new Error('Network error');
                            const clone = response.clone();
                            caches.open(CACHE_NAME + '-' + CACHE_VERSION)
                                .then((cache) => cache.put(request, clone));
                            return response;
                        })
                        .catch(() => {
                            // Offline - return fallback for HTML pages
                            if (request.destination === 'document') {
                                return caches.match('./') || caches.match('yunobot');
                            }
                            return null;
                        });
                })
        );
        return;
    }
    
    // ============================================================
    // Strategy 2: Default - network first
    // ============================================================
    event.respondWith(
        fetch(request)
            .catch(() => {
                // Completely offline - return null (let app handle gracefully)
                return null;
            })
    );
});

// ============================================================
// BACKGROUND SYNC - For when we need to sync data when back online
// ============================================================
self.addEventListener('sync', (event) => {
    if (event.tag === 'yunobot-sync') {
        event.waitUntil(
            // Process any pending sync tasks
            Promise.resolve().then(() => {
                console.log('[YunoBot SW] Background sync completed');
            })
        );
    }
});

// ============================================================
// PUSH NOTIFICATIONS (Future use)
// ============================================================
self.addEventListener('push', (event) => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body || 'New message from YunoBot',
        icon: '/assets/images/favicon-pfp.png',
        badge: '/assets/images/favicon-pfp.png',
        tag: 'yunobot-notification',
        requireInteraction: false,
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'YunoBot', options)
    );
});

// ============================================================
// MESSAGE HANDLER - For communication with main thread
// ============================================================
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys()
                .then((names) => Promise.all(names.map((n) => caches.delete(n))))
                .then(() => {
                    event.ports[0]?.postMessage({ success: true });
                })
        );
    }
    
    if (event.data && event.data.type === 'CACHE_STATUS') {
        event.waitUntil(
            caches.keys()
                .then((names) => {
                    return {
                        caches: names,
                        version: CACHE_VERSION,
                    };
                })
                .then((status) => {
                    event.ports[0]?.postMessage(status);
                })
        );
    }
});
