/**
 * Inventory System service worker
 *
 * Online: network-first for HTML/PHP (fresh session + data).
 * Offline: serve cached app shell (index.php), NOT login.php.
 *          API/model requests get a clear offline JSON error so client JS can queue.
 *
 * Bump CACHE_NAME when shell assets change so old caches are purged.
 */
const CACHE_NAME = 'inventory-shell-v4';

const APP_SHELL = [
    './',
    'index.php',
    'login.php',
    'assets/css/shop-styles.css',
    'assets/js/scripts.js',
    'assets/js/db-sync.js',
    'assets/js/offline-catalog.js',
    'assets/js/pwa.js',
    'assets/js/login.js',
    'vendor/jquery/jquery.min.js',
    'vendor/bootstrap/js/bootstrap.bundle.min.js',
    'vendor/bootstrap/css/bootstrap.min.css',
    'vendor/bootstrap/css/cerulean.theme.min.css',
    'vendor/bootbox/bootbox.min.js',
    'icons/android-chrome-192x192.png',
    'icons/android-chrome-512x512.png'
];

function isNavigationRequest(request) {
    return request.mode === 'navigate' ||
        (request.method === 'GET' && request.headers.get('accept') &&
            request.headers.get('accept').indexOf('text/html') !== -1);
}

function isPhpOrHtmlPath(pathname) {
    return pathname.endsWith('.php') || pathname.endsWith('/') || pathname === '';
}

function isApiPath(pathname) {
    return pathname.indexOf('/model/') !== -1;
}

function offlineJsonResponse(message) {
    return new Response(JSON.stringify({
        success: false,
        offline: true,
        message: message || 'You are offline. This action needs a connection or will sync later.'
    }), {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
    });
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            // addAll fails entirely if one URL 404s — cache what we can
            return Promise.all(APP_SHELL.map(function (url) {
                return cache.add(url).catch(function () {
                    console.warn('[sw] skip missing shell asset:', url);
                });
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            return Promise.all(cacheNames.filter(function (name) {
                return name !== CACHE_NAME;
            }).map(function (name) {
                return caches.delete(name);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        // POST/PUT etc. — let the browser handle (offline sale uses localStorage, not SW)
        return;
    }

    var requestUrl;
    try {
        requestUrl = new URL(event.request.url);
    } catch (e) {
        return;
    }

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    var pathname = requestUrl.pathname;

    // API / model endpoints: network only; offline → JSON error (never login.html)
    if (isApiPath(pathname)) {
        event.respondWith(
            fetch(event.request).catch(function () {
                return offlineJsonResponse('Offline: server request failed. Pending work stays in the local queue.');
            })
        );
        return;
    }

    // Navigation + PHP pages: network-first, offline fallback to app shell (index), not login
    if (isNavigationRequest(event.request) || isPhpOrHtmlPath(pathname)) {
        event.respondWith(
            fetch(event.request).then(function (networkResponse) {
                // Cache successful app pages (especially index) for offline use
                if (networkResponse && networkResponse.ok) {
                    var copy = networkResponse.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        // Prefer storing under index.php for the main shell
                        if (pathname.endsWith('index.php') || pathname.endsWith('/') || isNavigationRequest(event.request)) {
                            cache.put(new Request('index.php'), copy.clone()).catch(function () {});
                            cache.put(event.request, copy).catch(function () {});
                        } else if (pathname.endsWith('login.php')) {
                            cache.put(event.request, copy).catch(function () {});
                        }
                    });
                }
                return networkResponse;
            }).catch(function () {
                // Explicit login request → cached login only
                if (pathname.endsWith('login.php')) {
                    return caches.match('login.php').then(function (r) {
                        return r || offlineJsonResponse('Offline and login page is not cached.');
                    });
                }
                // Everything else offline → cached app shell (logged-in index), never force login
                return caches.match('index.php').then(function (indexCached) {
                    if (indexCached) {
                        return indexCached;
                    }
                    return caches.match('./').then(function (rootCached) {
                        if (rootCached) {
                            return rootCached;
                        }
                        // Last resort only
                        return caches.match('login.php').then(function (loginCached) {
                            return loginCached || new Response(
                                '<!DOCTYPE html><html><body><h1>Offline</h1><p>Open the app once while online to cache it for offline use.</p></body></html>',
                                { headers: { 'Content-Type': 'text/html' } }
                            );
                        });
                    });
                });
            })
        );
        return;
    }

    // Static assets: cache-first, then network
    event.respondWith(
        caches.match(event.request).then(function (cachedResponse) {
            if (cachedResponse) {
                return cachedResponse;
            }
            return fetch(event.request).then(function (networkResponse) {
                if (networkResponse && networkResponse.ok) {
                    var responseCopy = networkResponse.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(event.request, responseCopy).catch(function () {});
                    });
                }
                return networkResponse;
            });
        })
    );
});
