import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    PWA_DISMISSED_KEY,
    PWA_MAX_OFFER_VISITS,
    PWA_SESSION_COUNTED_KEY,
    PWA_VISIT_COUNT_KEY,
    dismissInstallOffer,
    isInstallOfferDismissed,
    isIosDevice,
    isRunningAsInstalledApp,
    readVisitCount,
    recordWebsiteVisit,
    shouldOfferInstall,
} from './pwaInstallPrompt.js';

function createStorage() {
    /** @type {Record<string, string>} */
    const data = {};

    return {
        getItem(key) {
            return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
        },
        setItem(key, value) {
            data[key] = String(value);
        },
        removeItem(key) {
            delete data[key];
        },
        clear() {
            for (const key of Object.keys(data)) {
                delete data[key];
            }
        },
    };
}

describe('recordWebsiteVisit', () => {
    it('increments once per browser session', () => {
        const localStorageLike = createStorage();
        const sessionStorageLike = createStorage();

        expect(recordWebsiteVisit(localStorageLike, sessionStorageLike)).toBe(1);
        expect(recordWebsiteVisit(localStorageLike, sessionStorageLike)).toBe(1);
        expect(localStorageLike.getItem(PWA_VISIT_COUNT_KEY)).toBe('1');
        expect(sessionStorageLike.getItem(PWA_SESSION_COUNTED_KEY)).toBe('1');
    });

    it('increments again in a new session', () => {
        const localStorageLike = createStorage();
        const firstSession = createStorage();
        const secondSession = createStorage();

        expect(recordWebsiteVisit(localStorageLike, firstSession)).toBe(1);
        expect(recordWebsiteVisit(localStorageLike, secondSession)).toBe(2);
    });
});

describe('shouldOfferInstall', () => {
    it('offers only on the first two visits while not dismissed or installed', () => {
        expect(shouldOfferInstall({
            visitCount: 1,
            dismissed: false,
            installed: false,
        })).toBe(true);

        expect(shouldOfferInstall({
            visitCount: 2,
            dismissed: false,
            installed: false,
        })).toBe(true);

        expect(shouldOfferInstall({
            visitCount: 3,
            dismissed: false,
            installed: false,
        })).toBe(false);

        expect(shouldOfferInstall({
            visitCount: 1,
            dismissed: true,
            installed: false,
        })).toBe(false);

        expect(shouldOfferInstall({
            visitCount: 1,
            dismissed: false,
            installed: true,
        })).toBe(false);

        expect(PWA_MAX_OFFER_VISITS).toBe(2);
    });
});

describe('install offer dismissal helpers', () => {
    it('persists dismissal in local storage', () => {
        const localStorageLike = createStorage();

        expect(isInstallOfferDismissed(localStorageLike)).toBe(false);
        dismissInstallOffer(localStorageLike);
        expect(isInstallOfferDismissed(localStorageLike)).toBe(true);
        expect(localStorageLike.getItem(PWA_DISMISSED_KEY)).toBe('1');
    });
});

describe('readVisitCount', () => {
    it('returns zero for missing or invalid values', () => {
        const localStorageLike = createStorage();

        expect(readVisitCount(localStorageLike)).toBe(0);

        localStorageLike.setItem(PWA_VISIT_COUNT_KEY, 'abc');
        expect(readVisitCount(localStorageLike)).toBe(0);
    });
});

describe('isRunningAsInstalledApp', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('detects standalone display mode', () => {
        const windowLike = {
            matchMedia: vi.fn((query) => ({
                matches: query === '(display-mode: standalone)',
            })),
        };

        expect(isRunningAsInstalledApp(windowLike, {})).toBe(true);
    });

    it('detects iOS navigator.standalone', () => {
        const windowLike = {
            matchMedia: vi.fn(() => ({ matches: false })),
        };

        expect(isRunningAsInstalledApp(windowLike, { standalone: true })).toBe(true);
    });
});

describe('isIosDevice', () => {
    it('detects iPhone user agents', () => {
        expect(isIosDevice({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)' })).toBe(true);
    });

    it('detects iPadOS desktop UA with touch points', () => {
        expect(isIosDevice({
            userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            platform: 'MacIntel',
            maxTouchPoints: 5,
        })).toBe(true);
    });
});
