// Bump on every deploy that changes cache behaviour: activate() deletes older caches.
const CACHE_NAME = 'absen-static-v4';
const STATIC_ASSETS = [
    '/assets/js/face-api-models/tiny_face_detector_model-weights_manifest.json',
    '/assets/js/face-api-models/tiny_face_detector_model-shard1',
    '/assets/js/face-api-models/face_landmark_68_model-weights_manifest.json',
    '/assets/js/face-api-models/face_landmark_68_model-shard1',
    '/assets/js/face-api-models/face_recognition_model-weights_manifest.json',
    '/assets/js/face-api-models/face_recognition_model-shard1',
    '/assets/js/face-api-models/face_recognition_model-shard2'
];

// Only public, versioned assets are cacheable. Queries may carry private actions.
function isPublicAsset(request) {
    const url = new URL(request.url);
    return request.method === 'GET'
        && url.origin === self.location.origin
        && request.mode !== 'navigate'
        && !url.searchParams.has('ajax')
        && !url.searchParams.has('action')
        && !request.headers.has('Authorization')
        && (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/build/'));
}

// Model weights never change in place -> cache-first is safe and saves ~7MB per visit.
function isImmutable(request) {
    const path = new URL(request.url).pathname;
    return path.includes('/face-api-models/') || path.includes('/face-models/');
}

function cacheable(response) {
    return response.ok && !response.redirected && response.type === 'basic'
        && !/private|no-store/i.test(response.headers.get('Cache-Control') || '')
        && !/text\/html/i.test(response.headers.get('Content-Type') || '');
}

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(caches.open(CACHE_NAME).then(cache =>
        Promise.allSettled(STATIC_ASSETS.map(async path => {
            const response = await fetch(path, { cache: 'reload' });
            if (response.ok && !response.redirected) await cache.put(path, response);
        }))
    ));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(
        keys.filter(key => key.startsWith('absen-') && key !== CACHE_NAME)
            .map(key => caches.delete(key))
    )).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
    if (!isPublicAsset(event.request)) return; // Private data always goes to network.
    event.respondWith(caches.open(CACHE_NAME).then(async cache => {
        if (isImmutable(event.request)) {
            const cached = await cache.match(event.request);
            if (cached) return cached;
            const response = await fetch(event.request);
            if (cacheable(response)) event.waitUntil(cache.put(event.request, response.clone()));
            return response;
        }
        // Scripts/styles: network-first so a deploy reaches phones immediately.
        // Cache-first here kept serving old attendance.js/utilities.js after updates.
        try {
            const response = await fetch(event.request);
            if (cacheable(response)) event.waitUntil(cache.put(event.request, response.clone()));
            return response;
        } catch (e) {
            const cached = await cache.match(event.request);
            if (cached) return cached;
            throw e;
        }
    }));
});
