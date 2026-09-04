import { beforeEach, describe, expect, it, vi } from 'vitest';

const decryptChatMessage = vi.hoisted(() => vi.fn());

vi.mock('../cryptography/e2ee/session.js', () => ({
    decryptChatMessage,
    encryptChatMessage: vi.fn(),
    establishSession: vi.fn(),
    fetchPartnerConversationKey: vi.fn(),
}));

import {
    formatMessageTime,
    hasPartnerSessionChanged,
    redecryptStoredMessages,
    resolveChatMessageContent,
    resolveIncomingMessageContent,
} from './messageContent.js';

describe('formatMessageTime', () => {
    it('formats message timestamps for display', () => {
        expect(formatMessageTime('2026-07-02T18:30:00Z', 'America/New_York', 'en-US'))
            .toBe('Jul 2, 2026 2:30 PM');
        expect(formatMessageTime('')).toBe('');
    });
});

describe('hasPartnerSessionChanged', () => {
    it('returns false when the partner fingerprint is unchanged', () => {
        expect(hasPartnerSessionChanged('abc', 'abc')).toBe(false);
    });

    it('returns false before the initial partner fingerprint is known', () => {
        expect(hasPartnerSessionChanged('', 'abc')).toBe(false);
    });

    it('returns true when the partner fingerprint changes', () => {
        expect(hasPartnerSessionChanged('abc', 'def')).toBe(true);
    });
});

describe('resolveChatMessageContent', () => {
    beforeEach(() => {
        decryptChatMessage.mockReset();
    });

    it('returns plaintext when decryption succeeds', async () => {
        decryptChatMessage.mockResolvedValue('Hello');

        const result = await resolveChatMessageContent('payload', {}, 'Unable to decrypt this message.');

        expect(result).toEqual({
            plaintext: 'Hello',
            decryptionError: '',
        });
    });

    it('returns a decryption error when decryption fails', async () => {
        decryptChatMessage.mockRejectedValue(new Error('OperationError'));

        const result = await resolveChatMessageContent('payload', {}, 'Unable to decrypt this message.');

        expect(result).toEqual({
            plaintext: null,
            decryptionError: 'Unable to decrypt this message.',
        });
    });
});

describe('redecryptStoredMessages', () => {
    beforeEach(() => {
        decryptChatMessage.mockReset();
    });

    it('clears decryption errors when a stored payload can be decrypted', async () => {
        decryptChatMessage.mockResolvedValue('Recovered message');

        const messages = [{
            payload: 'encrypted-payload',
            plaintext: null,
            decryptionError: 'Unable to decrypt this message.',
        }];

        await redecryptStoredMessages(messages, {}, 'Unable to decrypt this message.');

        expect(messages[0]).toEqual({
            payload: 'encrypted-payload',
            plaintext: 'Recovered message',
            attachment: null,
            decryptionError: '',
        });
    });

    it('hydrates attachment metadata from recovered envelopes', async () => {
        decryptChatMessage.mockResolvedValue(JSON.stringify({
            v: 1,
            text: 'see file',
            attachment: {
                path: 'uploads/1/file.txt',
                name: 'file.txt',
                content_type: 'text/plain',
                size: 4,
                v: 1,
                iv: 'AAAAAAAAAAAAAAAA',
            },
        }));

        const messages = [{
            payload: 'encrypted-payload',
            plaintext: null,
            attachment: null,
            decryptionError: 'Unable to decrypt this message.',
        }];

        await redecryptStoredMessages(messages, {}, 'Unable to decrypt this message.');

        expect(messages[0]).toEqual({
            payload: 'encrypted-payload',
            plaintext: 'see file',
            attachment: {
                path: 'uploads/1/file.txt',
                name: 'file.txt',
                content_type: 'text/plain',
                size: 4,
                v: 1,
                iv: 'AAAAAAAAAAAAAAAA',
            },
            decryptionError: '',
        });
    });

    it('leaves failed messages unchanged when decryption still fails', async () => {
        decryptChatMessage.mockRejectedValue(new Error('OperationError'));

        const messages = [{
            payload: 'encrypted-payload',
            plaintext: null,
            decryptionError: 'Unable to decrypt this message.',
        }];

        await redecryptStoredMessages(messages, {}, 'Unable to decrypt this message.');

        expect(messages[0].decryptionError).toBe('Unable to decrypt this message.');
    });
});

describe('resolveIncomingMessageContent', () => {
    beforeEach(() => {
        decryptChatMessage.mockReset();
    });

    it('retries decryption after refreshing the partner session', async () => {
        const staleKey = { id: 'stale' };
        const freshKey = { id: 'fresh' };

        decryptChatMessage
            .mockRejectedValueOnce(new Error('OperationError'))
            .mockResolvedValueOnce('Hello after rotation');

        const result = await resolveIncomingMessageContent(
            'payload',
            () => staleKey,
            'Unable to decrypt this message.',
            async () => ({ conversationKey: freshKey, partnerFingerprint: 'new-fingerprint' }),
        );

        expect(result).toEqual({
            plaintext: 'Hello after rotation',
            decryptionError: '',
        });
        expect(decryptChatMessage).toHaveBeenNthCalledWith(1, 'payload', staleKey);
        expect(decryptChatMessage).toHaveBeenNthCalledWith(2, 'payload', freshKey);
    });

    it('returns a decryption error when refresh does not recover the message', async () => {
        decryptChatMessage.mockRejectedValue(new Error('OperationError'));

        const result = await resolveIncomingMessageContent(
            'payload',
            () => ({ id: 'stale' }),
            'Unable to decrypt this message.',
            async () => null,
        );

        expect(result).toEqual({
            plaintext: null,
            decryptionError: 'Unable to decrypt this message.',
        });
    });
});
