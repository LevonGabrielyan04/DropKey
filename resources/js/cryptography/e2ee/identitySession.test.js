import { beforeEach, describe, expect, it, vi } from 'vitest';

/** @type {{ envelope: object|null, unlocked: object|null }} */
const store = vi.hoisted(() => ({
    envelope: null,
    unlocked: null,
}));

const decryptViaWorker = vi.hoisted(() => vi.fn());

vi.mock('../decrypt/decryptViaWorker.js', () => ({
    decryptViaWorker,
}));

vi.mock('./keyStore.js', () => ({
    databaseNameForBrowserDbId: vi.fn((browserDbId) => `passshare-${browserDbId}`),
    isIdentityEnvelope: vi.fn((value) => value?.v === 2),
    isLegacyEncryptedIdentity: vi.fn((value) => Boolean(
        value
        && ! value.v
        && typeof value?.ciphertext === 'string'
        && typeof value?.salt === 'string'
        && typeof value?.iv === 'string',
    )),
    loadEncryptedIdentity: vi.fn(async () => store.envelope),
    saveEncryptedIdentity: vi.fn(async (_browserDbId, payload) => {
        store.envelope = payload;
    }),
    loadUnlockedIdentity: vi.fn(async () => store.unlocked),
    saveUnlockedIdentity: vi.fn(async (_browserDbId, identity) => {
        if (identity.privateKey.extractable) {
            throw new Error('Unlocked identity private keys must be non-extractable.');
        }

        store.unlocked = identity;
    }),
    clearUnlockedIdentity: vi.fn(async () => {
        store.unlocked = null;
    }),
}));

import {
    clearSessionCredentials,
    consumeTransientAccountPassword,
    getCachedIdentity,
    getSessionBrowserDbId,
    loadIdentity,
    lockIdentity,
    persistIdentity,
    persistUnlockedIdentity,
    resolveBrowserDbId,
    resolveStoredIdentity,
    saveIdentity,
    setSessionBrowserDbId,
    setTransientAccountPassword,
    unlockIdentity,
} from './identitySession.js';

const BROWSER_DB_ID = '01JABCDEF1234567890ABCDEFG';

/**
 * @returns {Promise<{ privateKey: CryptoKey, publicJwk: JsonWebKey }>}
 */
async function generateIdentity() {
    const keyPair = await globalThis.crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveBits', 'deriveKey'],
    );
    const publicJwk = await globalThis.crypto.subtle.exportKey('jwk', keyPair.publicKey);

    return {
        privateKey: keyPair.privateKey,
        publicJwk,
    };
}

describe('identitySession', () => {
    beforeEach(() => {
        store.envelope = null;
        store.unlocked = null;
        clearSessionCredentials();
        vi.clearAllMocks();
    });

    it('clears cached identity and session credentials', async () => {
        setSessionBrowserDbId(BROWSER_DB_ID);
        setTransientAccountPassword('secret-password');

        const identity = await generateIdentity();

        await persistUnlockedIdentity(BROWSER_DB_ID, identity);

        clearSessionCredentials();

        expect(getCachedIdentity()).toBeNull();
        expect(consumeTransientAccountPassword()).toBeNull();
        expect(store.unlocked?.publicJwk).toEqual(identity.publicJwk);
    });

    it('unlocks a stored envelope when a transient password is available', async () => {
        const identity = await generateIdentity();

        await persistIdentity(BROWSER_DB_ID, 'secret-password', identity);

        store.unlocked = null;
        clearSessionCredentials();
        setTransientAccountPassword('secret-password');

        const resolved = await resolveStoredIdentity(BROWSER_DB_ID);

        expect(resolved?.publicJwk).toEqual(identity.publicJwk);
        expect(consumeTransientAccountPassword()).toBeNull();
    });

    it('persists a non-extractable unlocked CryptoKey without an envelope', async () => {
        const identity = await generateIdentity();
        const { saveEncryptedIdentity, saveUnlockedIdentity } = await import('./keyStore.js');

        const persisted = await persistUnlockedIdentity(BROWSER_DB_ID, identity);

        expect(persisted.privateKey.extractable).toBe(false);
        expect(persisted.publicJwk).toEqual(identity.publicJwk);
        expect(saveEncryptedIdentity).not.toHaveBeenCalled();
        expect(saveUnlockedIdentity).toHaveBeenCalledWith(
            BROWSER_DB_ID,
            expect.objectContaining({
                privateKey: expect.any(CryptoKey),
                publicJwk: identity.publicJwk,
            }),
        );
        expect(getCachedIdentity()?.publicJwk).toEqual(identity.publicJwk);
    });

    it('persists a v2 envelope and non-extractable unlocked CryptoKey', async () => {
        const identity = await generateIdentity();
        const { saveEncryptedIdentity, saveUnlockedIdentity } = await import('./keyStore.js');

        const persisted = await persistIdentity(BROWSER_DB_ID, 'secret-password', identity);

        expect(persisted.privateKey.extractable).toBe(false);
        expect(persisted.publicJwk).toEqual(identity.publicJwk);
        expect(saveEncryptedIdentity).toHaveBeenCalledWith(
            BROWSER_DB_ID,
            expect.objectContaining({
                v: 2,
                publicJwk: identity.publicJwk,
                kekSalt: expect.any(String),
                wrappedDek: expect.objectContaining({
                    ciphertext: expect.any(String),
                    iv: expect.any(String),
                }),
                wrappedIdentity: expect.objectContaining({
                    ciphertext: expect.any(String),
                    iv: expect.any(String),
                }),
            }),
        );
        expect(saveUnlockedIdentity).toHaveBeenCalledWith(
            BROWSER_DB_ID,
            expect.objectContaining({
                privateKey: expect.any(CryptoKey),
                publicJwk: identity.publicJwk,
            }),
        );
        expect(getCachedIdentity()?.publicJwk).toEqual(identity.publicJwk);
    });

    it('unlocks a v2 envelope with the password and re-persists the unlocked key', async () => {
        const identity = await generateIdentity();

        await persistIdentity(BROWSER_DB_ID, 'secret-password', identity);

        store.unlocked = null;
        clearSessionCredentials();

        const unlocked = await unlockIdentity(BROWSER_DB_ID, 'secret-password');

        expect(unlocked?.publicJwk).toEqual(identity.publicJwk);
        expect(unlocked?.privateKey.extractable).toBe(false);
        expect(getCachedIdentity()?.publicJwk).toEqual(identity.publicJwk);
        expect(store.unlocked?.publicJwk).toEqual(identity.publicJwk);
    });

    it('loads identity from the unlocked CryptoKey without a session password', async () => {
        const identity = await generateIdentity();

        await persistUnlockedIdentity(BROWSER_DB_ID, identity);

        clearSessionCredentials();
        setSessionBrowserDbId(BROWSER_DB_ID);

        const { loadEncryptedIdentity } = await import('./keyStore.js');
        vi.mocked(loadEncryptedIdentity).mockClear();

        // Simulate a page reload: memory cache empty, unlocked key still in IndexedDB.
        const { clearCachedIdentity } = await import('./identitySession.js');
        clearCachedIdentity();

        const loaded = await loadIdentity();

        expect(loaded?.publicJwk).toEqual(identity.publicJwk);
        expect(loadEncryptedIdentity).not.toHaveBeenCalled();
    });

    it('returns null when no encrypted identity exists', async () => {
        const unlocked = await unlockIdentity(BROWSER_DB_ID, 'secret-password');

        expect(unlocked).toBeNull();
        expect(getCachedIdentity()).toBeNull();
    });

    it('loads identity from memory cache without hitting storage again', async () => {
        const identity = await generateIdentity();

        await persistUnlockedIdentity(BROWSER_DB_ID, identity);

        const { loadEncryptedIdentity, loadUnlockedIdentity } = await import('./keyStore.js');
        vi.mocked(loadEncryptedIdentity).mockClear();
        vi.mocked(loadUnlockedIdentity).mockClear();

        const loaded = await loadIdentity();

        expect(loaded?.publicJwk).toEqual(identity.publicJwk);
        expect(loadEncryptedIdentity).not.toHaveBeenCalled();
        expect(loadUnlockedIdentity).not.toHaveBeenCalled();
    });

    it('migrates a legacy identity blob to a v2 envelope on unlock', async () => {
        const identity = await generateIdentity();
        const privateJwk = await globalThis.crypto.subtle.exportKey('jwk', identity.privateKey);

        store.envelope = {
            ciphertext: 'legacy-cipher',
            salt: 'legacy-salt',
            iv: 'legacy-iv',
        };
        decryptViaWorker.mockResolvedValueOnce(JSON.stringify({
            publicJwk: identity.publicJwk,
            privateJwk,
        }));

        const unlocked = await unlockIdentity(BROWSER_DB_ID, 'secret-password');

        expect(decryptViaWorker).toHaveBeenCalledOnce();
        expect(unlocked?.publicJwk).toEqual(identity.publicJwk);
        expect(store.envelope).toEqual(expect.objectContaining({ v: 2 }));
        expect(store.unlocked?.privateKey.extractable).toBe(false);
    });

    it('throws when saving without a browser database id', async () => {
        const identity = await generateIdentity();

        vi.stubGlobal('document', {
            body: {
                dataset: {},
            },
        });

        await expect(saveIdentity(identity)).rejects.toThrow(
            'Cannot save identity without a browser database id.',
        );

        vi.unstubAllGlobals();
    });

    it('resolves the browser database id from the document dataset', async () => {
        const identity = await generateIdentity();

        vi.stubGlobal('document', {
            body: {
                dataset: {
                    browserDbId: BROWSER_DB_ID,
                },
            },
        });

        await saveIdentity(identity);

        const { saveUnlockedIdentity } = await import('./keyStore.js');

        expect(saveUnlockedIdentity).toHaveBeenCalledWith(
            BROWSER_DB_ID,
            expect.objectContaining({
                privateKey: expect.any(CryptoKey),
                publicJwk: identity.publicJwk,
            }),
        );

        vi.unstubAllGlobals();
    });

    it('uses the session browser database id when the document dataset is empty', async () => {
        setSessionBrowserDbId(BROWSER_DB_ID);

        vi.stubGlobal('document', {
            body: {
                dataset: {},
            },
        });

        await expect(loadIdentity()).resolves.toBeNull();

        vi.unstubAllGlobals();
    });

    it('clears the unlocked IndexedDB key when locking identity', async () => {
        const identity = await generateIdentity();

        await persistUnlockedIdentity(BROWSER_DB_ID, identity);
        setSessionBrowserDbId(BROWSER_DB_ID);

        await lockIdentity();

        const { clearUnlockedIdentity } = await import('./keyStore.js');

        expect(clearUnlockedIdentity).toHaveBeenCalledWith(BROWSER_DB_ID);
        expect(getCachedIdentity()).toBeNull();
        expect(store.unlocked).toBeNull();
    });

    it('rejects invalid browser database ids before writing to session storage', () => {
        setSessionBrowserDbId('<img src=x onerror=alert(1)>');
        setSessionBrowserDbId('not-a-ulid');
        setSessionBrowserDbId('');

        expect(getSessionBrowserDbId()).toBeNull();
    });

    it('normalizes valid browser database ids to uppercase in session storage', () => {
        setSessionBrowserDbId('01jabcdef1234567890abcdefg');

        expect(getSessionBrowserDbId()).toBe(BROWSER_DB_ID);
    });

    it('ignores invalid browser database ids from the document dataset', () => {
        setSessionBrowserDbId(BROWSER_DB_ID);

        vi.stubGlobal('document', {
            body: {
                dataset: {
                    browserDbId: 'javascript:alert(1)',
                },
            },
        });

        expect(resolveBrowserDbId()).toBe(BROWSER_DB_ID);

        vi.unstubAllGlobals();
    });
});
