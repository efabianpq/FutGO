/**
 * FutGO Service Worker
 * Estrategias: cache-first para assets estáticos,
 * network-first para navegación HTML.
 */

const CACHE_NAME = 'futgo-v1';

/** Recursos pre-cacheados en el install */
const PRECACHE_URLS = [
    '/FutGO/pwa/icon-192.png',
    '/FutGO/pwa/icon-512.png',
    '/FutGO/pwa/icon-192-maskable.png',
    '/FutGO/pwa/icon-512-maskable.png',
    '/FutGO/pwa/manifest.webmanifest',
];

// ── install: pre-cachear assets críticos ──────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    // Activar inmediatamente sin esperar a que las pestañas viejas se cierren
    self.skipWaiting();
});

// ── activate: limpiar caches viejos y tomar control ───────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

// ── fetch: estrategia por tipo de recurso ────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorar requests POST / no-GET
    if (request.method !== 'GET') return;

    // Ignorar rutas admin y api (siempre desde la red)
    if (
        url.pathname.startsWith('/admin') ||
        url.pathname.startsWith('/api')
    ) return;

    // Solo manejar requests del mismo origen
    if (url.origin !== location.origin) return;

    // ── Cache-first: assets Vite (/build/*), imágenes, fuentes, iconos PWA ──
    const isStaticAsset =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/FutGO/pwa/') ||
        /\.(png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot)$/i.test(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // ── Network-first: páginas HTML (navegación) ──────────────────────────
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    // Fallback: versión cacheada de esta URL → raíz → sin respuesta
                    caches.match(request)
                        .then((cached) => cached || caches.match('/'))
                )
        );
    }
});
