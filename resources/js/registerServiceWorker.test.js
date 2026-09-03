import { afterEach, describe, expect, it, vi } from 'vitest';

describe('registerServiceWorker', () => {
    afterEach(() => {
        vi.resetModules();
        vi.unstubAllGlobals();
    });

    it('registers the shared service worker when supported', async () => {
        const registration = { scope: '/' };
        const register = vi.fn().mockResolvedValue(registration);

        vi.stubGlobal('navigator', { serviceWorker: { register } });

        const { registerServiceWorker } = await import('./registerServiceWorker.js');
        const result = await registerServiceWorker('/sw.js');

        expect(register).toHaveBeenCalledWith('/sw.js');
        expect(result).toBe(registration);
    });

    it('returns null when service workers are unavailable', async () => {
        vi.stubGlobal('navigator', {});

        const { registerServiceWorker } = await import('./registerServiceWorker.js');

        await expect(registerServiceWorker()).resolves.toBeNull();
    });

    it('returns null when registration fails', async () => {
        const register = vi.fn().mockRejectedValue(new Error('blocked'));

        vi.stubGlobal('navigator', { serviceWorker: { register } });

        const { registerServiceWorker } = await import('./registerServiceWorker.js');

        await expect(registerServiceWorker()).resolves.toBeNull();
    });
});
