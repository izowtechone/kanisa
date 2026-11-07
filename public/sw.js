/*
 * KKKT CMS service worker.
 *
 * Caches ONLY versioned static assets (/build/*, /icons/*) and the offline page.
 * It never caches HTML documents, JSON, uploads or any response that could contain
 * member, financial or user data, and it never touches non-GET requests.
 * Bump CACHE_VERSION to invalidate every static cache on the next activation.
 */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `kkkt-static-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/icon-512.png'];
const STATIC_PREFIXES = ['/build/', '/icons/'];
const STATIC_DESTINATIONS = ['style', 'script', 'font', 'image'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('kkkt-') && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

function isCacheableStatic(request, url) {
    return request.method === 'GET'
        && url.origin === self.location.origin
        && STATIC_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
        && STATIC_DESTINATIONS.includes(request.destination);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Page loads always go to the network; only a failed connection falls back to the offline notice.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );

        return;
    }

    if (!isCacheableStatic(request, url)) {
        return;
    }

    event.respondWith(
        caches.open(STATIC_CACHE).then((cache) => cache.match(request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(request).then((response) => {
                if (response.ok && response.type === 'basic') {
                    cache.put(request, response.clone());
                }

                return response;
            });
        })),
    );
});
