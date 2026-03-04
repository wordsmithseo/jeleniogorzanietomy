/**
 * Tile Caching Service Worker
 * Intercepts map tile requests and caches them for instant replay on pan/zoom.
 */

var CACHE_NAME = 'jg-tiles-v3';
var TILE_HOSTS = ['api.maptiler.com', 'server.arcgisonline.com'];

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(names) {
            return Promise.all(
                names
                    .filter(function(n) { return n !== CACHE_NAME; })
                    .map(function(n) { return caches.delete(n); })
            );
        }).then(function() {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function(event) {
    var url = event.request.url;
    var isTile = TILE_HOSTS.some(function(host) { return url.indexOf(host) !== -1; });
    if (!isTile) return;

    event.respondWith(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.match(event.request).then(function(cached) {
                if (cached) {
                    return cached;
                }
                var corsRequest = new Request(event.request.url, {
                    mode: 'cors',
                    credentials: 'omit'
                });
                return fetch(corsRequest).then(function(response) {
                    if (response.ok) {
                        cache.put(event.request, response.clone());
                    }
                    return response;
                });
            });
        })
    );
});
