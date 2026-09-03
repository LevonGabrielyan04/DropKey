export const PWA_VISIT_COUNT_KEY = 'dropkey.pwa.visit-count';
export const PWA_DISMISSED_KEY = 'dropkey.pwa.install-dismissed';
export const PWA_SESSION_COUNTED_KEY = 'dropkey.pwa.visit-counted-session';
export const PWA_MAX_OFFER_VISITS = 2;

/**
 * @param {Storage | null | undefined} localStorageLike
 * @param {Storage | null | undefined} sessionStorageLike
 * @returns {number}
 */
export function recordWebsiteVisit(localStorageLike = globalThis.localStorage, sessionStorageLike = globalThis.sessionStorage) {
    if (! localStorageLike || ! sessionStorageLike) {
        return 0;
    }

    if (sessionStorageLike.getItem(PWA_SESSION_COUNTED_KEY) === '1') {
        return readVisitCount(localStorageLike);
    }

    const nextCount = readVisitCount(localStorageLike) + 1;

    localStorageLike.setItem(PWA_VISIT_COUNT_KEY, String(nextCount));
    sessionStorageLike.setItem(PWA_SESSION_COUNTED_KEY, '1');

    return nextCount;
}

/**
 * @param {Storage | null | undefined} localStorageLike
 * @returns {number}
 */
export function readVisitCount(localStorageLike = globalThis.localStorage) {
    if (! localStorageLike) {
        return 0;
    }

    const raw = localStorageLike.getItem(PWA_VISIT_COUNT_KEY);
    const parsed = Number.parseInt(raw ?? '0', 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

/**
 * @param {object} options
 * @param {number} options.visitCount
 * @param {boolean} options.dismissed
 * @param {boolean} options.installed
 * @param {number} [options.maxVisits]
 * @returns {boolean}
 */
export function shouldOfferInstall({
    visitCount,
    dismissed,
    installed,
    maxVisits = PWA_MAX_OFFER_VISITS,
}) {
    if (installed || dismissed) {
        return false;
    }

    return visitCount > 0 && visitCount <= maxVisits;
}

/**
 * @param {Window | undefined} windowLike
 * @param {Navigator | undefined} navigatorLike
 * @returns {boolean}
 */
export function isRunningAsInstalledApp(
    windowLike = globalThis.window,
    navigatorLike = globalThis.navigator,
) {
    if (! windowLike) {
        return false;
    }

    if (windowLike.matchMedia?.('(display-mode: standalone)')?.matches) {
        return true;
    }

    if (windowLike.matchMedia?.('(display-mode: minimal-ui)')?.matches) {
        return true;
    }

    return Boolean(navigatorLike && 'standalone' in navigatorLike && navigatorLike.standalone);
}

/**
 * @param {Storage | null | undefined} localStorageLike
 * @returns {boolean}
 */
export function isInstallOfferDismissed(localStorageLike = globalThis.localStorage) {
    return localStorageLike?.getItem(PWA_DISMISSED_KEY) === '1';
}

/**
 * @param {Storage | null | undefined} localStorageLike
 */
export function dismissInstallOffer(localStorageLike = globalThis.localStorage) {
    localStorageLike?.setItem(PWA_DISMISSED_KEY, '1');
}

/**
 * @param {Navigator | undefined} navigatorLike
 * @returns {boolean}
 */
export function isIosDevice(navigatorLike = globalThis.navigator) {
    const userAgent = navigatorLike?.userAgent ?? '';

    return /iPad|iPhone|iPod/.test(userAgent)
        || (navigatorLike?.platform === 'MacIntel' && (navigatorLike?.maxTouchPoints ?? 0) > 1);
}

/**
 * Wire the install offer UI when the shared prompt element is present.
 *
 * @param {Document} [documentLike]
 * @param {Window} [windowLike]
 * @returns {{ destroy: () => void } | null}
 */
export function initPwaInstallPrompt(
    documentLike = globalThis.document,
    windowLike = globalThis.window,
) {
    const root = documentLike?.getElementById('pwa-install-prompt');

    if (! root || ! windowLike) {
        return null;
    }

    const installButton = root.querySelector('[data-pwa-install]');
    const dismissButton = root.querySelector('[data-pwa-dismiss]');
    const chromiumCopy = root.querySelector('[data-pwa-copy="chromium"]');
    const iosCopy = root.querySelector('[data-pwa-copy="ios"]');
    const manualCopy = root.querySelector('[data-pwa-copy="manual"]');

    /** @type {BeforeInstallPromptEvent | null} */
    let deferredPrompt = null;
    let destroyed = false;

    const visitCount = recordWebsiteVisit();
    const installed = isRunningAsInstalledApp(windowLike, windowLike.navigator);
    const dismissed = isInstallOfferDismissed();

    const hide = () => {
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
    };

    const show = () => {
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
    };

    const setCopyMode = (mode) => {
        for (const node of [chromiumCopy, iosCopy, manualCopy]) {
            if (! node) {
                continue;
            }

            node.hidden = node.getAttribute('data-pwa-copy') !== mode;
        }

        if (installButton instanceof HTMLElement) {
            installButton.hidden = mode !== 'chromium';
        }
    };

    const refreshVisibility = () => {
        if (destroyed || ! shouldOfferInstall({ visitCount, dismissed: isInstallOfferDismissed(), installed })) {
            hide();

            return;
        }

        if (deferredPrompt) {
            setCopyMode('chromium');
        } else if (isIosDevice(windowLike.navigator)) {
            setCopyMode('ios');
        } else {
            setCopyMode('manual');
        }

        show();
    };

    /**
     * @param {Event} event
     */
    const onBeforeInstallPrompt = (event) => {
        event.preventDefault();
        deferredPrompt = /** @type {BeforeInstallPromptEvent} */ (event);
        refreshVisibility();
    };

    const onAppInstalled = () => {
        deferredPrompt = null;
        dismissInstallOffer();
        hide();
    };

    const onInstallClick = async () => {
        if (! deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();
        await deferredPrompt.userChoice.catch(() => null);
        deferredPrompt = null;
        dismissInstallOffer();
        hide();
    };

    const onDismissClick = () => {
        dismissInstallOffer();
        hide();
    };

    windowLike.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    windowLike.addEventListener('appinstalled', onAppInstalled);
    installButton?.addEventListener('click', onInstallClick);
    dismissButton?.addEventListener('click', onDismissClick);

    /** @type {ReturnType<typeof setTimeout> | undefined} */
    let fallbackTimer;

    if (! shouldOfferInstall({ visitCount, dismissed, installed })) {
        hide();
    } else {
        // Give Chromium a moment to fire beforeinstallprompt before falling back
        // to iOS / manual instructions, so the Install button is preferred.
        fallbackTimer = windowLike.setTimeout(() => {
            if (! destroyed && ! deferredPrompt) {
                refreshVisibility();
            }
        }, 1500);
    }

    return {
        destroy() {
            destroyed = true;

            if (fallbackTimer !== undefined) {
                windowLike.clearTimeout(fallbackTimer);
            }

            windowLike.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
            windowLike.removeEventListener('appinstalled', onAppInstalled);
            installButton?.removeEventListener('click', onInstallClick);
            dismissButton?.removeEventListener('click', onDismissClick);
            hide();
        },
    };
}

if (typeof document !== 'undefined') {
    const boot = () => {
        initPwaInstallPrompt();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
}
