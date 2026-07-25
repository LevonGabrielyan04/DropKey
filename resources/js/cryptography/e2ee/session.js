import { deriveConversationKey } from './conversationKey.js';
import { ensureIdentityKeyPair, ensureServerIdentityKey, importPublicKey } from './identity.js';
import { decryptBytes, decryptMessage, encryptBytes, encryptMessage } from './messageCrypto.js';

/**
 * @param {object} options
 * @param {number} options.localUserId
 * @param {number} options.recipientId
 * @param {string} options.publicKeyUrl
 * @returns {Promise<{ conversationKey: CryptoKey, partnerFingerprint: string }|null>}
 */
export async function fetchPartnerConversationKey({
    localUserId,
    recipientId,
    publicKeyUrl,
}) {
    const { privateKey } = await ensureIdentityKeyPair();

    const response = await fetch(publicKeyUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        return null;
    }

    const { public_key_jwk: publicJwk, fingerprint } = await response.json();
    const remotePublicKey = await importPublicKey(publicJwk);
    const conversationKey = await deriveConversationKey(
        privateKey,
        remotePublicKey,
        localUserId,
        recipientId,
    );

    return { conversationKey, partnerFingerprint: fingerprint };
}

/**
 * @param {object} options
 * @param {number} options.localUserId
 * @param {number} options.recipientId
 * @param {string} options.publicKeyUrl
 * @param {string} options.registerUrl
 * @param {string} [options.mineUrl]
 * @param {string} options.csrfToken
 */
export async function establishSession({
    localUserId,
    recipientId,
    publicKeyUrl,
    registerUrl,
    mineUrl,
    csrfToken,
}) {
    await ensureServerIdentityKey({ registerUrl, mineUrl, csrfToken });

    const partnerSession = await fetchPartnerConversationKey({
        localUserId,
        recipientId,
        publicKeyUrl,
    });

    if (! partnerSession) {
        throw new Error('Recipient has not registered an encryption key yet.');
    }

    return partnerSession;
}

export {
    decryptBytes as decryptChatFile,
    decryptMessage as decryptChatMessage,
    encryptBytes as encryptChatFile,
    encryptMessage as encryptChatMessage,
};
