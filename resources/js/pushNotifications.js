/**
 * Convert a URL-safe base64 VAPID public key into a Uint8Array.
 *
 * @param {string} base64String
 * @returns {Uint8Array}
 */
export function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = globalThis.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i += 1) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
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
 * @param {Navigator | undefined} navigatorLike
 * @returns {boolean}
 */
export function isIosDevice(navigatorLike = globalThis.navigator) {
    const userAgent = navigatorLike?.userAgent ?? '';

    return /iPad|iPhone|iPod/.test(userAgent)
        || (navigatorLike?.platform === 'MacIntel' && (navigatorLike?.maxTouchPoints ?? 0) > 1);
}

/**
 * @returns {boolean}
 */
export function isPushNotificationSupported() {
    return Boolean(
        globalThis.window
        && 'Notification' in globalThis
        && 'serviceWorker' in globalThis.navigator
        && 'PushManager' in globalThis,
    );
}

/**
 * @param {string} serviceWorkerUrl
 * @returns {Promise<ServiceWorkerRegistration>}
 */
export async function ensureServiceWorkerRegistration(serviceWorkerUrl = '/sw.js') {
    return globalThis.navigator.serviceWorker.register(serviceWorkerUrl);
}

/**
 * @param {string} vapidPublicKey
 * @param {string} [serviceWorkerUrl]
 * @returns {Promise<PushSubscription>}
 */
export async function subscribeToPush(vapidPublicKey, serviceWorkerUrl = '/sw.js') {
    const registration = await ensureServiceWorkerRegistration(serviceWorkerUrl);
    await globalThis.navigator.serviceWorker.ready;

    const existing = await registration.pushManager.getSubscription();

    if (existing) {
        return existing;
    }

    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });
}

/**
 * @param {string} [serviceWorkerUrl]
 * @returns {Promise<PushSubscription | null>}
 */
export async function getCurrentPushSubscription(serviceWorkerUrl = '/sw.js') {
    if (! isPushNotificationSupported()) {
        return null;
    }

    const registration = await ensureServiceWorkerRegistration(serviceWorkerUrl);
    await globalThis.navigator.serviceWorker.ready;

    return registration.pushManager.getSubscription();
}

/**
 * @param {string} [serviceWorkerUrl]
 * @returns {Promise<PushSubscription | null>}
 */
export async function unsubscribeFromPush(serviceWorkerUrl = '/sw.js') {
    const subscription = await getCurrentPushSubscription(serviceWorkerUrl);

    if (! subscription) {
        return null;
    }

    await subscription.unsubscribe();

    return subscription;
}

/**
 * @param {PushSubscription} subscription
 * @returns {{ endpoint: string, keys: { p256dh: string, auth: string }, content_encoding: string }}
 */
export function serializePushSubscription(subscription) {
    const json = subscription.toJSON();

    if (! json.endpoint || ! json.keys?.p256dh || ! json.keys?.auth) {
        throw new Error('Push subscription is missing required keys.');
    }

    return {
        endpoint: json.endpoint,
        keys: {
            p256dh: json.keys.p256dh,
            auth: json.keys.auth,
        },
        content_encoding: 'aes128gcm',
    };
}

/**
 * @param {object} options
 * @param {string} options.vapidPublicKeyUrl
 * @param {string} options.storeUrl
 * @param {string} options.destroyUrl
 * @param {string} options.csrfToken
 * @param {string} [options.serviceWorkerUrl]
 * @returns {Promise<PushSubscription>}
 */
export async function enablePushNotifications({
    vapidPublicKeyUrl,
    storeUrl,
    destroyUrl: _destroyUrl,
    csrfToken,
    serviceWorkerUrl = '/sw.js',
}) {
    if (! isPushNotificationSupported()) {
        throw new Error('Push notifications are not supported in this browser.');
    }

    const permission = await globalThis.Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error('Notification permission was not granted.');
    }

    const vapidResponse = await fetch(vapidPublicKeyUrl, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (! vapidResponse.ok) {
        throw new Error('Unable to load the VAPID public key.');
    }

    const { public_key: vapidPublicKey } = await vapidResponse.json();

    if (typeof vapidPublicKey !== 'string' || vapidPublicKey === '') {
        throw new Error('VAPID public key is missing.');
    }

    const subscription = await subscribeToPush(vapidPublicKey, serviceWorkerUrl);

    const response = await fetch(storeUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(serializePushSubscription(subscription)),
    });

    if (! response.ok) {
        await subscription.unsubscribe().catch(() => {});
        throw new Error('Unable to save the push subscription.');
    }

    return subscription;
}

/**
 * @param {object} options
 * @param {string} options.destroyUrl
 * @param {string} options.csrfToken
 * @param {string} [options.serviceWorkerUrl]
 * @returns {Promise<void>}
 */
export async function disablePushNotifications({
    destroyUrl,
    csrfToken,
    serviceWorkerUrl = '/sw.js',
}) {
    const subscription = await unsubscribeFromPush(serviceWorkerUrl);

    if (! subscription) {
        return;
    }

    const response = await fetch(destroyUrl, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            endpoint: subscription.endpoint,
        }),
    });

    if (! response.ok) {
        throw new Error('Unable to remove the push subscription.');
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        if (typeof Alpine === 'undefined') {
            return;
        }

        Alpine.data('pushNotificationSettings', () => ({
            supported: false,
            ios: false,
            installed: false,
            enabled: false,
            permission: 'default',
            busy: false,
            error: '',
            vapidPublicKeyUrl: '',
            storeUrl: '',
            destroyUrl: '',
            csrfToken: '',

            async init() {
                this.vapidPublicKeyUrl = this.$el.dataset.vapidPublicKeyUrl ?? '';
                this.storeUrl = this.$el.dataset.storeUrl ?? '';
                this.destroyUrl = this.$el.dataset.destroyUrl ?? '';
                this.csrfToken = this.$el.dataset.csrfToken
                    ?? document.body?.dataset?.csrfToken
                    ?? '';

                this.ios = isIosDevice();
                this.installed = isRunningAsInstalledApp();
                this.supported = isPushNotificationSupported();

                if (! this.supported) {
                    return;
                }

                this.permission = globalThis.Notification.permission;
                await this.refreshStatus();
            },

            async refreshStatus() {
                this.permission = globalThis.Notification.permission;

                if (this.permission !== 'granted') {
                    this.enabled = false;

                    return;
                }

                try {
                    const subscription = await getCurrentPushSubscription();
                    this.enabled = subscription !== null;
                } catch {
                    this.enabled = false;
                }
            },

            async enable() {
                this.busy = true;
                this.error = '';

                try {
                    await enablePushNotifications({
                        vapidPublicKeyUrl: this.vapidPublicKeyUrl,
                        storeUrl: this.storeUrl,
                        destroyUrl: this.destroyUrl,
                        csrfToken: this.csrfToken,
                    });

                    this.enabled = true;
                    this.permission = globalThis.Notification.permission;
                } catch (error) {
                    this.error = error instanceof Error
                        ? error.message
                        : 'Unable to enable push notifications.';
                    await this.refreshStatus();
                } finally {
                    this.busy = false;
                }
            },

            async disable() {
                this.busy = true;
                this.error = '';

                try {
                    await disablePushNotifications({
                        destroyUrl: this.destroyUrl,
                        csrfToken: this.csrfToken,
                    });

                    this.enabled = false;
                } catch (error) {
                    this.error = error instanceof Error
                        ? error.message
                        : 'Unable to disable push notifications.';
                    await this.refreshStatus();
                } finally {
                    this.busy = false;
                }
            },
        }));
    });
}
