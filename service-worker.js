const CACHE_NAME = 'antobell-shell-v1';
const APP_SHELL = [
    './',
    'login.php',
    'assets/css/shop-styles.css',
    'vendor/bootstrap/css/cerulean.theme.min.css',
    'icons/android-chrome-192x192.png',
    'icons/android-chrome-512x512.png'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(APP_SHELL);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            return Promise.all(cacheNames.filter(function (cacheName) {
                return cacheName !== CACHE_NAME;
            }).map(function (cacheName) {
                return caches.delete(cacheName);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    var requestUrl = new URL(event.request.url);
    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (requestUrl.pathname.endsWith('.php') || event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(function () {
            return caches.match('login.php');
        }));
        return;
    }

    event.respondWith(
        caches.match(event.request).then(function (cachedResponse) {
            if (cachedResponse) {
                return cachedResponse;
            }
            return fetch(event.request).then(function (networkResponse) {
                var responseCopy = networkResponse.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(event.request, responseCopy);
                });
                return networkResponse;
            });
        })
    );
});
