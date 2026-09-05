import { afterEach, describe, expect, it, vi } from 'vitest';
import { syncAppBadge } from './appBadge.js';

describe('syncAppBadge', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('sets the app badge for a positive count', async () => {
        const setAppBadge = vi.fn().mockResolvedValue(undefined);
        const clearAppBadge = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', { setAppBadge, clearAppBadge });

        await syncAppBadge(3);

        expect(setAppBadge).toHaveBeenCalledWith(3);
        expect(clearAppBadge).not.toHaveBeenCalled();
    });

    it('clears the app badge when the count is zero', async () => {
        const setAppBadge = vi.fn().mockResolvedValue(undefined);
        const clearAppBadge = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', { setAppBadge, clearAppBadge });

        await syncAppBadge(0);

        expect(clearAppBadge).toHaveBeenCalledOnce();
        expect(setAppBadge).not.toHaveBeenCalled();
    });

    it('does nothing when the Badging API is unavailable', async () => {
        vi.stubGlobal('navigator', {});

        await expect(syncAppBadge(2)).resolves.toBeUndefined();
    });
});
