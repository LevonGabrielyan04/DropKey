import './pwaInstallPrompt.js';

/**
 * Register the shared service worker used for PWA installability and push.
 *
 * @param {string} [serviceWorkerUrl]
 * @returns {Promise<ServiceWorkerRegistration | null>}
 */
export async function registerServiceWorker(serviceWorkerUrl = '/sw.js') {
    if (! globalThis.navigator?.serviceWorker) {
        return null;
    }

    try {
        return await globalThis.navigator.serviceWorker.register(serviceWorkerUrl);
    } catch {
        return null;
    }
}

if (typeof document !== 'undefined') {
    const register = () => {
        void registerServiceWorker();
    };

    if (document.readyState === 'complete') {
        register();
    } else {
        globalThis.addEventListener('load', register, { once: true });
    }
}
