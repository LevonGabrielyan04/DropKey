import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    disablePushNotifications,
    enablePushNotifications,
    isIosDevice,
    isPushNotificationSupported,
    isRunningAsInstalledApp,
    serializePushSubscription,
    urlBase64ToUint8Array,
} from './pushNotifications.js';

describe('urlBase64ToUint8Array', () => {
    it('decodes a URL-safe base64 VAPID key', () => {
        const bytes = urlBase64ToUint8Array('AQID');

        expect(Array.from(bytes)).toEqual([1, 2, 3]);
    });
});

describe('serializePushSubscription', () => {
    it('serializes a browser push subscription for the API', () => {
        const subscription = {
            toJSON: () => ({
                endpoint: 'https://fcm.googleapis.com/fcm/send/test',
                keys: {
                    p256dh: 'p256dh-key',
                    auth: 'auth-token',
                },
            }),
        };

        expect(serializePushSubscription(subscription)).toEqual({
            endpoint: 'https://fcm.googleapis.com/fcm/send/test',
            keys: {
                p256dh: 'p256dh-key',
                auth: 'auth-token',
            },
            content_encoding: 'aes128gcm',
        });
    });

    it('rejects incomplete subscriptions', () => {
        const subscription = {
            toJSON: () => ({
                endpoint: 'https://fcm.googleapis.com/fcm/send/test',
                keys: {},
            }),
        };

        expect(() => serializePushSubscription(subscription)).toThrow(/missing required keys/i);
    });
});

describe('isPushNotificationSupported', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
    });

    it('returns false when PushManager is unavailable', () => {
        vi.stubGlobal('window', {});
        vi.stubGlobal('Notification', {});
        vi.stubGlobal('navigator', { serviceWorker: {} });

        expect(isPushNotificationSupported()).toBe(false);
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

describe('enablePushNotifications', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('requests permission, subscribes, and posts the subscription', async () => {
        const unsubscribe = vi.fn();
        const subscription = {
            endpoint: 'https://fcm.googleapis.com/fcm/send/test',
            unsubscribe,
            toJSON: () => ({
                endpoint: 'https://fcm.googleapis.com/fcm/send/test',
                keys: {
                    p256dh: 'p256dh-key',
                    auth: 'auth-token',
                },
            }),
        };

        const subscribe = vi.fn().mockResolvedValue(subscription);
        const getSubscription = vi.fn().mockResolvedValue(null);
        const register = vi.fn().mockResolvedValue({
            pushManager: { subscribe, getSubscription },
        });

        vi.stubGlobal('window', {});
        vi.stubGlobal('Notification', {
            requestPermission: vi.fn().mockResolvedValue('granted'),
        });
        vi.stubGlobal('navigator', {
            serviceWorker: {
                register,
                ready: Promise.resolve(),
            },
        });
        vi.stubGlobal('PushManager', function PushManager() {});
        vi.stubGlobal('atob', (value) => Buffer.from(value, 'base64').toString('binary'));
        vi.stubGlobal('fetch', vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ public_key: 'AQID' }),
            })
            .mockResolvedValueOnce({
                ok: true,
            }));

        const result = await enablePushNotifications({
            vapidPublicKeyUrl: '/api/push/vapid-public-key',
            storeUrl: '/api/push-subscriptions',
            destroyUrl: '/api/push-subscriptions',
            csrfToken: 'csrf-token',
        });

        expect(result).toBe(subscription);
        expect(subscribe).toHaveBeenCalledOnce();
        expect(fetch).toHaveBeenNthCalledWith(2, '/api/push-subscriptions', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                'X-CSRF-TOKEN': 'csrf-token',
            }),
            body: JSON.stringify({
                endpoint: 'https://fcm.googleapis.com/fcm/send/test',
                keys: {
                    p256dh: 'p256dh-key',
                    auth: 'auth-token',
                },
                content_encoding: 'aes128gcm',
            }),
        }));
    });
});

describe('disablePushNotifications', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('unsubscribes locally and deletes the server subscription', async () => {
        const unsubscribe = vi.fn().mockResolvedValue(true);
        const subscription = {
            endpoint: 'https://fcm.googleapis.com/fcm/send/test',
            unsubscribe,
            toJSON: () => ({
                endpoint: 'https://fcm.googleapis.com/fcm/send/test',
                keys: {
                    p256dh: 'p256dh-key',
                    auth: 'auth-token',
                },
            }),
        };

        const getSubscription = vi.fn().mockResolvedValue(subscription);
        const register = vi.fn().mockResolvedValue({
            pushManager: { getSubscription },
        });

        vi.stubGlobal('window', {});
        vi.stubGlobal('Notification', {});
        vi.stubGlobal('navigator', {
            serviceWorker: {
                register,
                ready: Promise.resolve(),
            },
        });
        vi.stubGlobal('PushManager', function PushManager() {});
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        await disablePushNotifications({
            destroyUrl: '/api/push-subscriptions',
            csrfToken: 'csrf-token',
        });

        expect(unsubscribe).toHaveBeenCalledOnce();
        expect(fetch).toHaveBeenCalledWith('/api/push-subscriptions', expect.objectContaining({
            method: 'DELETE',
            body: JSON.stringify({
                endpoint: 'https://fcm.googleapis.com/fcm/send/test',
            }),
        }));
    });
});
