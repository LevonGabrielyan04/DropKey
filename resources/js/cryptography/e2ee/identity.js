import { appFetch } from '../../http.js';
import { fingerprintFromPublicJwk } from './bufferUtils.js';
import {
    ensureIdentityOverwriteAllowed,
    IdentityKeyOverwriteCancelledError,
} from './identityOverwrite.js';
import { confirmIdentityKeyOverwrite } from './identityOverwriteConfirmation.js';
import {
    loadIdentity,
    persistUnlockedIdentity,
    resolveBrowserDbId,
    resolveStoredIdentity,
    setSessionBrowserDbId,
} from './identitySession.js';

/**
 * @param {string} registerUrl
 * @param {string} csrfToken
 * @param {JsonWebKey} publicJwk
 * @param {string} fingerprint
 * @returns {Promise<Response>}
 */
async function submitPublicKeyRegistration(registerUrl, csrfToken, publicJwk, fingerprint) {
    return appFetch(registerUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            public_key_jwk: publicJwk,
            fingerprint,
        }),
    });
}

/**
 * @returns {Promise<{ privateKey: CryptoKey, publicJwk: JsonWebKey, fingerprint: string }>}
 */
export async function ensureIdentityKeyPair() {
    const existing = await loadIdentity();

    if (existing) {
        const fingerprint = await fingerprintFromPublicJwk(existing.publicJwk);

        return {
            privateKey: existing.privateKey,
            publicJwk: existing.publicJwk,
            fingerprint,
        };
    }

    const browserDbId = resolveBrowserDbId();

    await ensureIdentityOverwriteAllowed({
        browserDbId,
        checkLocal: true,
        checkServer: false,
    });

    const keyPair = await globalThis.crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveBits', 'deriveKey'],
    );

    const publicJwk = await globalThis.crypto.subtle.exportKey('jwk', keyPair.publicKey);
    const fingerprint = await fingerprintFromPublicJwk(publicJwk);

    if (browserDbId) {
        await persistUnlockedIdentity(browserDbId, {
            privateKey: keyPair.privateKey,
            publicJwk,
        });
    }

    return {
        privateKey: keyPair.privateKey,
        publicJwk,
        fingerprint,
    };
}

/**
 * Ensure the server has a registered public key for the current user.
 * Creates a local key pair and registers it only when the server has none.
 * When the server already has a key, local material must match its fingerprint
 * or be re-registered so peers derive the same conversation key.
 *
 * @param {object} options
 * @param {string} options.registerUrl
 * @param {string} options.mineUrl
 * @param {string} options.csrfToken
 */
export async function ensureServerIdentityKey({ registerUrl, mineUrl, csrfToken }) {
    const mineResponse = await appFetch(mineUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (! mineResponse.ok) {
        throw new Error('Failed to check identity key registration status.');
    }

    const mine = await mineResponse.json();

    if (! mine.registered) {
        await registerPublicKey(registerUrl, csrfToken, { mineUrl });

        return { registered: false };
    }

    setSessionBrowserDbId(mine.browser_db_id);

    const localIdentity = await resolveStoredIdentity(mine.browser_db_id);

    if (localIdentity) {
        const localFingerprint = await fingerprintFromPublicJwk(localIdentity.publicJwk);

        if (localFingerprint === mine.fingerprint) {
            return { registered: true };
        }
    }

    await registerPublicKey(registerUrl, csrfToken, { mineUrl });

    return { registered: false };
}

/**
 * Register the public key with the server.
 *
 * @param {string} registerUrl
 * @param {string} csrfToken
 * @param {object} [options]
 * @param {string} [options.mineUrl]
 */
export async function registerPublicKey(registerUrl, csrfToken, { mineUrl } = {}) {
    const { publicJwk, fingerprint, privateKey } = await ensureIdentityKeyPair();

    if (mineUrl) {
        await ensureIdentityOverwriteAllowed({
            checkLocal: false,
            checkServer: true,
            mineUrl,
            fingerprint,
        });
    }

    let response = await submitPublicKeyRegistration(registerUrl, csrfToken, publicJwk, fingerprint);

    if (response.status === 423) {
        const confirmed = await confirmIdentityKeyOverwrite();

        if (! confirmed) {
            throw new IdentityKeyOverwriteCancelledError();
        }

        response = await submitPublicKeyRegistration(registerUrl, csrfToken, publicJwk, fingerprint);
    }

    if (! response.ok) {
        throw new Error('Failed to register public key.');
    }

    const result = await response.json();

    setSessionBrowserDbId(result.browser_db_id);

    await persistUnlockedIdentity(result.browser_db_id, {
        privateKey,
        publicJwk,
    });

    return { publicJwk, fingerprint };
}

/**
 * @param {JsonWebKey} jwk
 */
export async function importPublicKey(jwk) {
    return globalThis.crypto.subtle.importKey(
        'jwk',
        jwk,
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        [],
    );
}
