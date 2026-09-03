/* global self, clients */

/**
 * Network-only fetch handler for PWA installability.
 * Intentionally does not use the Cache API so chat pages, send pages,
 * Livewire requests, and secret payloads are never stored offline.
 */
self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});

self.addEventListener('push', (event) => {
    const payload = parsePushPayload(event.data);

    event.waitUntil((async () => {
        const url = typeof payload.data?.url === 'string' ? payload.data.url : '/chat';

        if (await shouldSuppressNotification(url)) {
            return;
        }

        await self.registration.showNotification(payload.title, {
            body: payload.body,
            tag: payload.tag,
            data: {
                url,
            },
            renotify: false,
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = typeof event.notification.data?.url === 'string'
        ? event.notification.data.url
        : '/chat';

    event.waitUntil(focusOrOpenUrl(url));
});

/**
 * @param {PushMessageData | null} data
 * @returns {{ title: string, body: string, tag?: string, data?: { url?: string } }}
 */
function parsePushPayload(data) {
    const defaults = {
        title: 'Notification',
        body: 'You have a new message',
    };

    if (! data) {
        return defaults;
    }

    try {
        const payload = data.json();

        return {
            title: typeof payload.title === 'string' && payload.title !== ''
                ? payload.title
                : defaults.title,
            body: typeof payload.body === 'string' && payload.body !== ''
                ? payload.body
                : defaults.body,
            tag: typeof payload.tag === 'string' ? payload.tag : undefined,
            data: payload.data && typeof payload.data === 'object' ? payload.data : undefined,
        };
    } catch {
        return defaults;
    }
}

/**
 * @param {string} url
 * @returns {Promise<boolean>}
 */
async function shouldSuppressNotification(url) {
    const windowClients = await clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });

    let targetPath;

    try {
        targetPath = new URL(url, self.location.origin).pathname;
    } catch {
        return false;
    }

    return windowClients.some((client) => {
        if (! client.focused) {
            return false;
        }

        try {
            return new URL(client.url).pathname === targetPath;
        } catch {
            return false;
        }
    });
}

/**
 * @param {string} url
 * @returns {Promise<WindowClient | null | undefined>}
 */
async function focusOrOpenUrl(url) {
    const absoluteUrl = new URL(url, self.location.origin).href;
    const windowClients = await clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });

    for (const client of windowClients) {
        if (client.url === absoluteUrl || client.url.startsWith(absoluteUrl)) {
            return client.focus();
        }
    }

    if (clients.openWindow) {
        return clients.openWindow(absoluteUrl);
    }

    return undefined;
}
