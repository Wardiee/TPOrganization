// Register the service worker so the app can be installed on phones and shows an offline page
// when there's no connection. It caches only static files, never member data (see public/sw.js).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Installing is optional; the app works the same without it.
        });
    });
}
