import {
    confirmAccountPassword,
    isPasswordRecentlyConfirmed,
} from './cryptography/e2ee/identityKeyPasswordConfirmation.js';

/**
 * @param {string|null|undefined} passwordConfirmationStatusUrl
 * @returns {Promise<boolean>}
 */
export async function resolvePasswordRequiredForIdentityKeyOverwrite(passwordConfirmationStatusUrl) {
    if (! passwordConfirmationStatusUrl) {
        return true;
    }

    return ! await isPasswordRecentlyConfirmed(passwordConfirmationStatusUrl);
}

/**
 * @param {object} options
 * @param {string} options.password
 * @param {string} options.passwordConfirmUrl
 * @param {string} options.csrfToken
 * @returns {Promise<{ ok: true } | { ok: false, error: string }>}
 */
export async function verifyPasswordForIdentityKeyOverwrite({
    password,
    passwordConfirmUrl,
    csrfToken,
}) {
    const trimmedPassword = password.trim();

    if (trimmedPassword === '') {
        return {
            ok: false,
            error: 'Password is required to replace your encryption key.',
        };
    }

    const result = await confirmAccountPassword(passwordConfirmUrl, csrfToken, trimmedPassword);

    if (! result.confirmed) {
        return {
            ok: false,
            error: result.error ?? 'Unable to verify password.',
        };
    }

    return { ok: true };
}
