import { afterEach, describe, expect, it, vi } from 'vitest';

const confirmAccountPassword = vi.hoisted(() => vi.fn());
const isPasswordRecentlyConfirmed = vi.hoisted(() => vi.fn());

vi.mock('./cryptography/e2ee/identityKeyPasswordConfirmation.js', () => ({
    confirmAccountPassword,
    isPasswordRecentlyConfirmed,
}));

import {
    resolvePasswordRequiredForIdentityKeyOverwrite,
    verifyPasswordForIdentityKeyOverwrite,
} from './identityKeyOverwriteModal.js';

describe('identityKeyOverwriteModal', () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it('requires a password when no confirmation status url is configured', async () => {
        await expect(resolvePasswordRequiredForIdentityKeyOverwrite(''))
            .resolves
            .toBe(true);

        expect(isPasswordRecentlyConfirmed).not.toHaveBeenCalled();
    });

    it('requires a password when Fortify reports no recent confirmation', async () => {
        isPasswordRecentlyConfirmed.mockResolvedValue(false);

        await expect(resolvePasswordRequiredForIdentityKeyOverwrite('/user/confirmed-password-status'))
            .resolves
            .toBe(true);
    });

    it('skips password entry when Fortify reports a recent confirmation', async () => {
        isPasswordRecentlyConfirmed.mockResolvedValue(true);

        await expect(resolvePasswordRequiredForIdentityKeyOverwrite('/user/confirmed-password-status'))
            .resolves
            .toBe(false);
    });

    it('rejects blank passwords before calling Fortify', async () => {
        await expect(verifyPasswordForIdentityKeyOverwrite({
            password: '   ',
            passwordConfirmUrl: '/user/confirm-password',
            csrfToken: 'csrf-token',
        })).resolves.toEqual({
            ok: false,
            error: 'Password is required to replace your encryption key.',
        });

        expect(confirmAccountPassword).not.toHaveBeenCalled();
    });

    it('returns a Fortify validation error when password confirmation fails', async () => {
        confirmAccountPassword.mockResolvedValue({
            confirmed: false,
            error: 'The provided password was incorrect.',
        });

        await expect(verifyPasswordForIdentityKeyOverwrite({
            password: 'wrong-password',
            passwordConfirmUrl: '/user/confirm-password',
            csrfToken: 'csrf-token',
        })).resolves.toEqual({
            ok: false,
            error: 'The provided password was incorrect.',
        });
    });

    it('accepts a confirmed account password', async () => {
        confirmAccountPassword.mockResolvedValue({ confirmed: true });

        await expect(verifyPasswordForIdentityKeyOverwrite({
            password: 'secret-password',
            passwordConfirmUrl: '/user/confirm-password',
            csrfToken: 'csrf-token',
        })).resolves.toEqual({ ok: true });
    });
});
