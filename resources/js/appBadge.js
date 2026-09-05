/**
 * Update the installed PWA home-screen icon badge when the Badging API is available.
 *
 * @param {unknown} count
 * @returns {Promise<void>}
 */
export async function syncAppBadge(count) {
    if (! globalThis.navigator
        || ! ('setAppBadge' in globalThis.navigator)
        || ! ('clearAppBadge' in globalThis.navigator)) {
        return;
    }

    const normalized = typeof count === 'number' && Number.isFinite(count)
        ? Math.max(0, Math.floor(count))
        : 0;

    try {
        if (normalized > 0) {
            await globalThis.navigator.setAppBadge(normalized);

            return;
        }

        await globalThis.navigator.clearAppBadge();
    } catch {
        // Badging can fail outside an installed PWA context; ignore silently.
    }
}
