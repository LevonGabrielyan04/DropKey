import { describe, expect, it } from 'vitest';

import {
    databaseNameForBrowserDbId,
    isIdentityEnvelope,
    isLegacyEncryptedIdentity,
} from './keyStore.js';

describe('keyStore', () => {
    it('builds a per-browser database name', () => {
        expect(databaseNameForBrowserDbId('a0a2a2d2-0b87-4a18-83f2-2529882be2de')).toBe('passshare-a0a2a2d2-0b87-4a18-83f2-2529882be2de');
    });

    it('detects legacy Argon2 identity blobs', () => {
        expect(isLegacyEncryptedIdentity({
            ciphertext: 'cipher',
            salt: 'salt',
            iv: 'iv',
        })).toBe(true);

        expect(isLegacyEncryptedIdentity({
            v: 2,
            ciphertext: 'cipher',
            salt: 'salt',
            iv: 'iv',
        })).toBe(false);
    });

    it('detects v2 identity envelopes', () => {
        expect(isIdentityEnvelope({
            v: 2,
            publicJwk: { kty: 'EC' },
            kekSalt: 'salt',
            wrappedDek: { ciphertext: 'a', iv: 'b' },
            wrappedIdentity: { ciphertext: 'c', iv: 'd' },
        })).toBe(true);

        expect(isIdentityEnvelope({
            ciphertext: 'cipher',
            salt: 'salt',
            iv: 'iv',
        })).toBe(false);
    });
});
