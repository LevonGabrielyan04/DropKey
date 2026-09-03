import { beforeEach, describe, expect, it, vi } from 'vitest';

const identityStore = vi.hoisted(() => ({
    value: null,
}));

vi.mock('./identitySession.js', () => ({
    getSessionBrowserDbId: vi.fn(() => 'a0a2a2d2-0b87-4a18-83f2-2529882be2de'),
    loadIdentity: vi.fn(async () => identityStore.value),
    persistUnlockedIdentity: vi.fn(async (_browserDbId, identity) => {
        identityStore.value = identity;
    }),
    saveIdentity: vi.fn(async (identity) => {
        identityStore.value = identity;
    }),
    resolveBrowserDbId: vi.fn(() => 'a0a2a2d2-0b87-4a18-83f2-2529882be2de'),
    setSessionBrowserDbId: vi.fn(),
    clearCachedIdentity: vi.fn(async () => {
        identityStore.value = null;
    }),
}));

vi.mock('./identityOverwrite.js', () => ({
    ensureIdentityOverwriteAllowed: vi.fn(async () => {}),
    IdentityKeyOverwriteCancelledError: class IdentityKeyOverwriteCancelledError extends Error {},
}));

vi.mock('./identity.js', async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        ensureServerIdentityKey: vi.fn(async () => ({ registered: true })),
        registerPublicKey: vi.fn(async () => ({
            publicJwk: { kty: 'EC', crv: 'P-256', x: 'local-x', y: 'local-y' },
            fingerprint: 'a'.repeat(64),
        })),
    };
});

import { establishSession, fetchPartnerConversationKey } from './session.js';
import { ensureServerIdentityKey, registerPublicKey } from './identity.js';

describe('session', () => {
    beforeEach(() => {
        identityStore.value = null;
        vi.clearAllMocks();
    });

    it('establishes an encrypted session with a remote public key', async () => {
        const local = await globalThis.crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveBits', 'deriveKey'],
        );
        const localJwk = await globalThis.crypto.subtle.exportKey('jwk', local.publicKey);
        identityStore.value = { privateKey: local.privateKey, publicJwk: localJwk };

        const remote = await globalThis.crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveBits', 'deriveKey'],
        );
        const remoteJwk = await globalThis.crypto.subtle.exportKey('jwk', remote.publicKey);

        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => ({
                public_key_jwk: remoteJwk,
                fingerprint: 'b'.repeat(64),
            }),
        })));

        const session = await establishSession({
            localUserId: 10,
            recipientId: 20,
            publicKeyUrl: '/api/users/20/public-key',
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });

        expect(ensureServerIdentityKey).toHaveBeenCalledWith({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });
        expect(registerPublicKey).not.toHaveBeenCalled();
        expect(session.partnerFingerprint).toBe('b'.repeat(64));
        expect(session.conversationKey.type).toBe('secret');
        expect(session.conversationKey.algorithm.name).toBe('AES-GCM');

        vi.unstubAllGlobals();
    });

    it('does not register the public key when the server already has one', async () => {
        const local = await globalThis.crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveBits', 'deriveKey'],
        );
        const localJwk = await globalThis.crypto.subtle.exportKey('jwk', local.publicKey);
        identityStore.value = { privateKey: local.privateKey, publicJwk: localJwk };

        const remote = await globalThis.crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveBits', 'deriveKey'],
        );
        const remoteJwk = await globalThis.crypto.subtle.exportKey('jwk', remote.publicKey);

        vi.mocked(ensureServerIdentityKey).mockResolvedValue({ registered: true });

        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => ({
                public_key_jwk: remoteJwk,
                fingerprint: 'b'.repeat(64),
            }),
        })));

        await establishSession({
            localUserId: 10,
            recipientId: 20,
            publicKeyUrl: '/api/users/20/public-key',
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });

        expect(ensureServerIdentityKey).toHaveBeenCalledOnce();
        expect(registerPublicKey).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });

    it('fetches a partner conversation key without registering local identity', async () => {
        const local = await globalThis.crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveBits', 'deriveKey'],
        );
        const localJwk = await globalThis.crypto.subtle.exportKey('jwk', local.publicKey);
        identityStore.value = { privateKey: local.privateKey, publicJwk: localJwk };

        const remote = await globalThis.crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' },
            true,
            ['deriveBits', 'deriveKey'],
        );
        const remoteJwk = await globalThis.crypto.subtle.exportKey('jwk', remote.publicKey);

        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => ({
                public_key_jwk: remoteJwk,
                fingerprint: 'b'.repeat(64),
            }),
        })));

        const session = await fetchPartnerConversationKey({
            localUserId: 10,
            recipientId: 20,
            publicKeyUrl: '/api/users/20/public-key',
        });

        expect(ensureServerIdentityKey).not.toHaveBeenCalled();
        expect(registerPublicKey).not.toHaveBeenCalled();
        expect(session?.partnerFingerprint).toBe('b'.repeat(64));
        expect(session?.conversationKey.algorithm.name).toBe('AES-GCM');

        vi.unstubAllGlobals();
    });

    it('returns null when the partner public key is unavailable', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false })));

        const session = await fetchPartnerConversationKey({
            localUserId: 1,
            recipientId: 2,
            publicKeyUrl: '/api/users/2/public-key',
        });

        expect(session).toBeNull();

        vi.unstubAllGlobals();
    });

    it('throws when the recipient public key is unavailable', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false })));

        await expect(establishSession({
            localUserId: 1,
            recipientId: 2,
            publicKeyUrl: '/api/users/2/public-key',
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        })).rejects.toThrow('Recipient has not registered an encryption key yet.');

        expect(ensureServerIdentityKey).toHaveBeenCalledOnce();
        expect(registerPublicKey).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});
