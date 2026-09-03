import { beforeEach, describe, expect, it, vi } from 'vitest';

const identityStore = vi.hoisted(() => ({
    value: null,
}));

vi.mock('./identitySession.js', () => ({
    getSessionBrowserDbId: vi.fn(() => 'a0a2a2d2-0b87-4a18-83f2-2529882be2de'),
    loadIdentity: vi.fn(async () => identityStore.value),
    resolveStoredIdentity: vi.fn(async () => identityStore.value),
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

const confirmIdentityKeyOverwrite = vi.hoisted(() => vi.fn(async () => true));

vi.mock('./identityOverwriteConfirmation.js', () => ({
    confirmIdentityKeyOverwrite,
}));

import { clearCachedIdentity, loadIdentity, persistUnlockedIdentity } from './identitySession.js';
import { ensureIdentityOverwriteAllowed, IdentityKeyOverwriteCancelledError } from './identityOverwrite.js';
import { ensureIdentityKeyPair, ensureServerIdentityKey, registerPublicKey } from './identity.js';

describe('identitySession integration via identity', () => {
    beforeEach(async () => {
        identityStore.value = null;
        vi.clearAllMocks();
    });

    it('generates and persists an identity key pair', async () => {
        const first = await ensureIdentityKeyPair();
        const second = await ensureIdentityKeyPair();

        expect(first.fingerprint).toMatch(/^[a-f0-9]{64}$/);
        expect(first.publicJwk.kty).toBe('EC');
        expect(first.publicJwk.crv).toBe('P-256');
        expect(second.fingerprint).toBe(first.fingerprint);
        expect(ensureIdentityOverwriteAllowed).toHaveBeenCalledOnce();
        expect(persistUnlockedIdentity).toHaveBeenCalledTimes(1);
        expect(loadIdentity).toHaveBeenCalled();
    });

    it('registers the public key with the relay', async () => {
        const fetchMock = vi.fn(async () => ({
            ok: true,
            json: async () => ({
                status: 'ok',
                browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
            }),
        }));
        vi.stubGlobal('fetch', fetchMock);

        await registerPublicKey('/api/identity/public-key', 'csrf-token');

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0][0]).toBe('/api/identity/public-key');

        const requestInit = fetchMock.mock.calls[0][1];
        const body = JSON.parse(requestInit.body);

        expect(requestInit.method).toBe('POST');
        expect(requestInit.headers['X-CSRF-TOKEN']).toBe('csrf-token');
        expect(body.public_key_jwk.kty).toBe('EC');
        expect(body.fingerprint).toMatch(/^[a-f0-9]{64}$/);
        expect(body).not.toHaveProperty('private_key');

        vi.unstubAllGlobals();
    });

    it('throws when public key registration fails', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false })));

        await expect(registerPublicKey('/api/identity/public-key', 'csrf-token'))
            .rejects
            .toThrow('Failed to register public key.');

        vi.unstubAllGlobals();
    });

    it('prompts for overwrite confirmation and retries registration after a 423 response', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ status: 423 })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    status: 'ok',
                    browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                }),
            });
        vi.stubGlobal('fetch', fetchMock);

        await registerPublicKey('/api/identity/public-key', 'csrf-token');

        expect(confirmIdentityKeyOverwrite).toHaveBeenCalledOnce();
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/identity/public-key');
        expect(fetchMock.mock.calls[1][0]).toBe('/api/identity/public-key');

        vi.unstubAllGlobals();
    });

    it('aborts registration when overwrite confirmation is declined after a 423 response', async () => {
        confirmIdentityKeyOverwrite.mockResolvedValueOnce(false);

        vi.stubGlobal('fetch', vi.fn(async () => ({ status: 423 })));

        await expect(registerPublicKey('/api/identity/public-key', 'csrf-token'))
            .rejects
            .toBeInstanceOf(IdentityKeyOverwriteCancelledError);

        expect(confirmIdentityKeyOverwrite).toHaveBeenCalledOnce();

        vi.unstubAllGlobals();
    });

    it('reloads a stored identity without regenerating keys', async () => {
        const generated = await ensureIdentityKeyPair();

        await clearCachedIdentity();
        identityStore.value = {
            privateKey: generated.privateKey,
            publicJwk: generated.publicJwk,
        };

        const loaded = await ensureIdentityKeyPair();

        expect(loaded.fingerprint).toBe(generated.fingerprint);
        expect(persistUnlockedIdentity).toHaveBeenCalledTimes(1);
    });

    it('registers a public key when the server has none', async () => {
        const fetchMock = vi.fn(async (url, init) => {
            if (! init?.method || init.method === 'GET') {
                return { ok: true, json: async () => ({ registered: false }) };
            }

            return {
                ok: true,
                json: async () => ({
                    status: 'ok',
                    browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                }),
            };
        });
        vi.stubGlobal('fetch', fetchMock);

        const result = await ensureServerIdentityKey({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });

        expect(result).toEqual({ registered: false });
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/identity/public-key/mine');
        expect(fetchMock.mock.calls[1][0]).toBe('/api/identity/public-key');
        expect(fetchMock.mock.calls[1][1].method).toBe('POST');
        expect(ensureIdentityOverwriteAllowed).toHaveBeenCalledWith(expect.objectContaining({
            checkServer: true,
            mineUrl: '/api/identity/public-key/mine',
        }));

        vi.unstubAllGlobals();
    });

    it('does not register when the server already has a matching public key', async () => {
        const generated = await ensureIdentityKeyPair();

        const fetchMock = vi.fn(async () => ({
            ok: true,
            json: async () => ({
                registered: true,
                browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                fingerprint: generated.fingerprint,
            }),
        }));
        vi.stubGlobal('fetch', fetchMock);

        const result = await ensureServerIdentityKey({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });

        expect(result).toEqual({ registered: true });
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0][0]).toBe('/api/identity/public-key/mine');

        vi.unstubAllGlobals();
    });

    it('re-registers when the server has a key but local material is missing', async () => {
        identityStore.value = null;

        const fetchMock = vi.fn(async (url, init) => {
            if (! init?.method || init.method === 'GET') {
                return {
                    ok: true,
                    json: async () => ({
                        registered: true,
                        browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                        fingerprint: 'existing-fingerprint',
                    }),
                };
            }

            return {
                ok: true,
                json: async () => ({
                    status: 'ok',
                    browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                }),
            };
        });
        vi.stubGlobal('fetch', fetchMock);

        const result = await ensureServerIdentityKey({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });

        expect(result).toEqual({ registered: false });
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[1][0]).toBe('/api/identity/public-key');

        vi.unstubAllGlobals();
    });

    it('re-registers when the server key does not match local material', async () => {
        const generated = await ensureIdentityKeyPair();

        const fetchMock = vi.fn(async (url, init) => {
            if (! init?.method || init.method === 'GET') {
                return {
                    ok: true,
                    json: async () => ({
                        registered: true,
                        browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                        fingerprint: 'different-fingerprint',
                    }),
                };
            }

            return {
                ok: true,
                json: async () => ({
                    status: 'ok',
                    browser_db_id: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                }),
            };
        });
        vi.stubGlobal('fetch', fetchMock);

        const result = await ensureServerIdentityKey({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });

        expect(result).toEqual({ registered: false });
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/identity/public-key/mine');
        expect(fetchMock.mock.calls[1][0]).toBe('/api/identity/public-key');
        expect(fetchMock.mock.calls[1][1].method).toBe('POST');
        expect(ensureIdentityOverwriteAllowed).toHaveBeenCalledWith(expect.objectContaining({
            checkServer: true,
            mineUrl: '/api/identity/public-key/mine',
            fingerprint: generated.fingerprint,
        }));

        vi.unstubAllGlobals();
    });

    it('throws when the server registration status check fails', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false })));

        await expect(ensureServerIdentityKey({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        })).rejects.toThrow('Failed to check identity key registration status.');

        vi.unstubAllGlobals();
    });
});
