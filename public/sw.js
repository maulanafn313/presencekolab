const CACHE_NAME = 'absen-static-v3';
const STATIC_ASSETS = [
    '/assets/js/attendance.js',
    '/assets/js/performance-optimizer.js',
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
        const cached = await cache.match(event.request);
        if (cached) return cached;
        const response = await fetch(event.request);
        if (response.ok && !response.redirected && response.type === 'basic'
            && !/private|no-store/i.test(response.headers.get('Cache-Control') || '')
            && !/text\/html/i.test(response.headers.get('Content-Type') || '')) {
            event.waitUntil(cache.put(event.request, response.clone()));
        }
        return response;
    }));
});
