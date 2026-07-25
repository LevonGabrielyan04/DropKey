import { base64ToBuffer, bufferToBase64 } from './bufferUtils.js';

export const PAYLOAD_VERSION = 1;
export const AES_GCM_IV_BYTES = 12;
export const AES_GCM_TAG_BYTES = 16;

/**
 * Encrypt arbitrary bytes with the conversation AES-GCM key.
 *
 * @param {BufferSource} plaintextBytes
 * @param {CryptoKey} conversationKey
 * @returns {Promise<{ v: number, ciphertext: ArrayBuffer, iv: string }>}
 */
export async function encryptBytes(plaintextBytes, conversationKey) {
    const iv = globalThis.crypto.getRandomValues(new Uint8Array(AES_GCM_IV_BYTES));
    const ciphertext = await globalThis.crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        conversationKey,
        plaintextBytes,
    );

    return {
        v: PAYLOAD_VERSION,
        ciphertext,
        iv: bufferToBase64(iv),
    };
}

/**
 * Decrypt AES-GCM bytes produced by encryptBytes / encryptMessage.
 *
 * @param {BufferSource} ciphertext
 * @param {string} ivBase64
 * @param {CryptoKey} conversationKey
 * @param {number} [version]
 * @returns {Promise<ArrayBuffer>}
 */
export async function decryptBytes(ciphertext, ivBase64, conversationKey, version = PAYLOAD_VERSION) {
    if (version !== PAYLOAD_VERSION) {
        throw new Error('Unsupported message payload version.');
    }

    const iv = base64ToBuffer(ivBase64);

    return globalThis.crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        conversationKey,
        ciphertext,
    );
}

/**
 * @param {string} plaintext
 * @param {CryptoKey} conversationKey
 */
export async function encryptMessage(plaintext, conversationKey) {
    const encrypted = await encryptBytes(new TextEncoder().encode(plaintext), conversationKey);

    return JSON.stringify({
        v: encrypted.v,
        ciphertext: bufferToBase64(encrypted.ciphertext),
        iv: encrypted.iv,
    });
}

/**
 * @param {string} payloadJson
 * @param {CryptoKey} conversationKey
 */
export async function decryptMessage(payloadJson, conversationKey) {
    const payload = JSON.parse(payloadJson);
    const plaintextBuffer = await decryptBytes(
        base64ToBuffer(payload.ciphertext),
        payload.iv,
        conversationKey,
        payload.v,
    );

    return new TextDecoder().decode(plaintextBuffer);
}

/**
 * @param {string} payloadJson
 */
export function parseChatPayload(payloadJson) {
    let payload;

    try {
        payload = JSON.parse(payloadJson);
    } catch {
        return null;
    }

    if (
        payload?.v !== PAYLOAD_VERSION
        || typeof payload.ciphertext !== 'string'
        || typeof payload.iv !== 'string'
    ) {
        return null;
    }

    return payload;
}
