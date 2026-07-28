import { afterEach, describe, expect, it, vi } from 'vitest';

import {
    confirmAccountPassword,
    isPasswordRecentlyConfirmed,
} from './identityKeyPasswordConfirmation.js';

describe('identityKeyPasswordConfirmation', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('returns true when Fortify reports a recent password confirmation', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => ({ confirmed: true }),
        })));

        await expect(isPasswordRecentlyConfirmed('/user/confirmed-password-status'))
            .resolves.toBe(true);
    });

    it('returns false when Fortify reports no recent password confirmation', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => ({ confirmed: false }),
        })));

        await expect(isPasswordRecentlyConfirmed('/user/confirmed-password-status'))
            .resolves.toBe(false);
    });

    it('confirms the account password via Fortify', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({
            status: 201,
        })));

        await expect(confirmAccountPassword('/user/confirm-password', 'csrf-token', 'secret'))
            .resolves.toEqual({ confirmed: true });

        expect(fetch).toHaveBeenCalledWith('/user/confirm-password', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ password: 'secret' }),
        }));
    });

    it('returns validation errors when password confirmation fails', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({
            status: 422,
            json: async () => ({
                errors: { password: ['The provided password was incorrect.'] },
            }),
        })));

        await expect(confirmAccountPassword('/user/confirm-password', 'csrf-token', 'wrong'))
            .resolves.toEqual({
                confirmed: false,
                error: 'The provided password was incorrect.',
            });
    });
});
