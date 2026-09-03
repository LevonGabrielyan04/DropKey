import { beforeEach, describe, expect, it, vi } from 'vitest';

const ensureServerIdentityKey = vi.hoisted(() => vi.fn(async () => ({ registered: false })));
const setSessionBrowserDbId = vi.hoisted(() => vi.fn());

vi.mock('./cryptography/e2ee/identity.js', () => ({
    ensureServerIdentityKey,
}));

vi.mock('./cryptography/e2ee/identitySession.js', () => ({
    setSessionBrowserDbId,
}));

describe('identityRegistration bootstrap', () => {
    beforeEach(() => {
        ensureServerIdentityKey.mockReset();
        setSessionBrowserDbId.mockReset();
    });

    it('registers a key when bootstrap data attributes are present', async () => {
        const doc = {
            body: {
                dataset: {
                    identityRegisterUrl: '/api/identity/public-key',
                    identityMineUrl: '/api/identity/public-key/mine',
                    csrfToken: 'csrf-token',
                },
            },
        };

        const { bootstrapIdentityRegistration } = await import('./identityRegistration.js');

        await bootstrapIdentityRegistration(doc);

        expect(setSessionBrowserDbId).not.toHaveBeenCalled();
        expect(ensureServerIdentityKey).toHaveBeenCalledWith({
            registerUrl: '/api/identity/public-key',
            mineUrl: '/api/identity/public-key/mine',
            csrfToken: 'csrf-token',
        });
    });

    it('stores the browser database id when bootstrap data includes it', async () => {
        const doc = {
            body: {
                dataset: {
                    browserDbId: 'a0a2a2d2-0b87-4a18-83f2-2529882be2de',
                    identityRegisterUrl: '/api/identity/public-key',
                    identityMineUrl: '/api/identity/public-key/mine',
                    csrfToken: 'csrf-token',
                },
            },
        };

        const { bootstrapIdentityRegistration } = await import('./identityRegistration.js');

        await bootstrapIdentityRegistration(doc);

        expect(setSessionBrowserDbId).toHaveBeenCalledWith('a0a2a2d2-0b87-4a18-83f2-2529882be2de');
        expect(ensureServerIdentityKey).toHaveBeenCalledOnce();
    });

    it('skips registration when bootstrap data attributes are missing', async () => {
        const doc = {
            body: {
                dataset: {},
            },
        };

        const { bootstrapIdentityRegistration } = await import('./identityRegistration.js');

        await bootstrapIdentityRegistration(doc);

        expect(ensureServerIdentityKey).not.toHaveBeenCalled();
    });
});
