/**
 * Headline numbers ([data-count-up]) count up from 0 when a screen opens. Only the leading number
 * in the element's first text is animated ("41%" → 0%…41%); the rest is left alone. Skipped when
 * the device asks for reduced motion, and each element only counts once.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function countUp() {
    if (reduceMotion.matches) {
        return;
    }

    document.querySelectorAll('[data-count-up]:not([data-counted])').forEach((element) => {
        element.dataset.counted = '1';

        const textNode = [...element.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() !== '');
        const match = textNode?.nodeValue.match(/^(\s*)(\d+)(.*)$/s);

        if (!match || Number(match[2]) === 0) {
            return;
        }

        const [, before, digits, after] = match;
        const target = Number(digits);
        const duration = 800;
        const start = performance.now();

        const step = (now) => {
            // Stop if Livewire has replaced the text in the meantime.
            if (!textNode.isConnected) {
                return;
            }

            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            textNode.nodeValue = before + Math.round(target * eased) + after;

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        textNode.nodeValue = before + '0' + after;
        requestAnimationFrame(step);
    });
}

document.addEventListener('livewire:navigated', countUp);

// Register the service worker so the app can be installed on phones and shows an offline page
// when there's no connection. It caches only static files, never member data (see public/sw.js).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Installing is optional; the app works the same without it.
        });
    });
}
