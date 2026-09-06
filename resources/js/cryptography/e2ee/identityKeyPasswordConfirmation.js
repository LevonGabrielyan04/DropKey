import { appFetch } from '../../http.js';

/**
 * @param {string} statusUrl
 * @returns {Promise<boolean>}
 */
export async function isPasswordRecentlyConfirmed(statusUrl) {
    const response = await appFetch(statusUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        return false;
    }

    const data = await response.json();

    return Boolean(data.confirmed);
}

/**
 * @param {string} confirmUrl
 * @param {string} csrfToken
 * @param {string} password
 * @returns {Promise<{ confirmed: boolean, error?: string }>}
 */
export async function confirmAccountPassword(confirmUrl, csrfToken, password) {
    const response = await appFetch(confirmUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ password }),
    });

    if (response.status === 201) {
        return { confirmed: true };
    }

    if (response.status === 422) {
        const data = await response.json();
        const error = data.errors?.password?.[0] ?? data.message;

        return { confirmed: false, error };
    }

    return { confirmed: false, error: 'Unable to verify password.' };
}
