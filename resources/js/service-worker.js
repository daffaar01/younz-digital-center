const CACHE_PREFIX = 'younz-static-';
const CACHE_NAME = `${CACHE_PREFIX}v2`;
const CACHEABLE_DESTINATIONS = new Set(['font', 'image', 'script', 'style']);
const CACHEABLE_PATH_PREFIXES = ['/build/', '/images/'];
const CACHEABLE_FILES = new Set(['/favicon.webp']);

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const {request} = event;
    const url = new URL(request.url);
    const staticPath = CACHEABLE_FILES.has(url.pathname)
        || CACHEABLE_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));

    if (request.method !== 'GET'
        || url.origin !== self.location.origin
        || !staticPath
        || !CACHEABLE_DESTINATIONS.has(request.destination)
        || request.headers.has('range')
    ) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(request);
            if (cached) return cached;

            const response = await fetch(request);
            if (response.ok && response.type === 'basic') {
                await cache.put(request, response.clone());
            }

            return response;
        }),
    );
});
