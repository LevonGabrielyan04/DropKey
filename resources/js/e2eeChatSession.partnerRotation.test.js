import { describe, expect, it } from 'vitest';
import { deriveConversationKey } from './cryptography/e2ee/conversationKey.js';
import { importPublicKey } from './cryptography/e2ee/identity.js';
import { decryptMessage, encryptMessage } from './cryptography/e2ee/messageCrypto.js';
import {
    hasPartnerSessionChanged,
    redecryptStoredMessages,
    resolveIncomingMessageContent,
} from './e2ee/messageContent.js';

/**
 * @returns {Promise<CryptoKeyPair>}
 */
async function generateEcdhKeyPair() {
    return globalThis.crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveBits', 'deriveKey'],
    );
}

describe('partner key rotation', () => {
    it('detects when a partner fingerprint changes', () => {
        expect(hasPartnerSessionChanged('old-fingerprint', 'new-fingerprint')).toBe(true);
    });

    it('decrypts a rotated partner message after refreshing the conversation key', async () => {
        const alice = await generateEcdhKeyPair();
        const bobOriginal = await generateEcdhKeyPair();
        const bobRotated = await generateEcdhKeyPair();

        const bobRotatedJwk = await globalThis.crypto.subtle.exportKey('jwk', bobRotated.publicKey);
        const bobRotatedPublic = await importPublicKey(bobRotatedJwk);

        const staleKey = await deriveConversationKey(
            alice.privateKey,
            bobOriginal.publicKey,
            1,
            2,
        );
        const freshKey = await deriveConversationKey(
            alice.privateKey,
            bobRotatedPublic,
            1,
            2,
        );

        const payload = await encryptMessage('Rotated hello', freshKey);

        const result = await resolveIncomingMessageContent(
            payload,
            () => staleKey,
            'Unable to decrypt this message.',
            async () => ({ conversationKey: freshKey, partnerFingerprint: 'rotated-fingerprint' }),
        );

        expect(result).toEqual({
            plaintext: 'Rotated hello',
            decryptionError: '',
        });
    });

    it('redecrypts stored messages after the partner key rotates', async () => {
        const alice = await generateEcdhKeyPair();
        const bobOriginal = await generateEcdhKeyPair();
        const bobRotated = await generateEcdhKeyPair();

        const bobRotatedJwk = await globalThis.crypto.subtle.exportKey('jwk', bobRotated.publicKey);
        const bobRotatedPublic = await importPublicKey(bobRotatedJwk);

        const staleKey = await deriveConversationKey(
            alice.privateKey,
            bobOriginal.publicKey,
            1,
            2,
        );
        const freshKey = await deriveConversationKey(
            alice.privateKey,
            bobRotatedPublic,
            1,
            2,
        );

        const payload = await encryptMessage('Previously failed message', freshKey);

        const messages = [{
            payload,
            plaintext: null,
            decryptionError: 'Unable to decrypt this message.',
        }];

        await redecryptStoredMessages(messages, staleKey, 'Unable to decrypt this message.');
        expect(messages[0].decryptionError).toBe('Unable to decrypt this message.');

        await redecryptStoredMessages(messages, freshKey, 'Unable to decrypt this message.');

        expect(messages[0]).toEqual({
            payload,
            plaintext: 'Previously failed message',
            attachment: null,
            decryptionError: '',
        });

        await expect(decryptMessage(payload, staleKey)).rejects.toThrow();
    });
});
