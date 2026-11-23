/*
 * Tropang Potchi service worker.
 *
 * Caches ONLY static files: the built CSS/JS and fonts, the logo, icons, manifest and the offline page.
 * Pages, member data, Livewire requests and uploaded files (previews/downloads) are never cached,
 * because phones may be shared. With no connection, page loads show /offline.html instead.
 *
 * Bump VERSION to drop everything cached by an older version.
 */
const VERSION = 'tp-static-v1';

const PRECACHE = [
    '/offline.html',
    '/images/logo.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/maskable-512.png',
    '/icons/favicon-32.png',
    '/apple-touch-icon.png',
    '/favicon.ico',
    '/manifest.webmanifest',
];

/** Same-origin paths that are safe to cache: static, identical for everyone, no member data. */
const STATIC_PREFIXES = ['/build/', '/images/', '/icons/'];
const STATIC_FILES = ['/favicon.ico', '/apple-touch-icon.png', '/manifest.webmanifest', '/offline.html'];

function isStatic(url) {
    return STATIC_FILES.includes(url.pathname) || STATIC_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
}

/** The built CSS/JS listed in Vite's manifest, so the app shell loads fast after install. */
async function builtAssets() {
    try {
        const response = await fetch('/build/manifest.json', { cache: 'no-store' });

        if (!response.ok) {
            return [];
        }

        const manifest = await response.json();
        const files = new Set();

        Object.values(manifest).forEach((entry) => {
            if (entry.file) files.add('/build/' + entry.file);
            (entry.css || []).forEach((css) => files.add('/build/' + css));
        });

        return [...files];
    } catch {
        return [];
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(VERSION);
            await cache.addAll(PRECACHE);
            await Promise.all((await builtAssets()).map((url) => cache.add(url).catch(() => undefined)));
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();
            await Promise.all(names.filter((name) => name !== VERSION).map((name) => caches.delete(name)));
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Pages: always from the network (never cached). Offline: show the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));

        return;
    }

    // Static files: cache first, then network (and remember it for next time).
    if (isStatic(url)) {
        event.respondWith(
            (async () => {
                const cached = await caches.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok && response.type === 'basic') {
                    const cache = await caches.open(VERSION);
                    cache.put(request, response.clone());
                }

                return response;
            })(),
        );
    }

    // Everything else (Livewire, document previews/downloads, data) goes straight to the network.
});
