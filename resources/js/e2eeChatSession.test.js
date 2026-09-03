import { beforeEach, describe, expect, it, vi } from 'vitest';

const decryptChatMessage = vi.hoisted(() => vi.fn());

vi.mock('./cryptography/e2ee/session.js', () => ({
    decryptChatMessage,
    encryptChatMessage: vi.fn(),
    establishSession: vi.fn(),
    fetchPartnerConversationKey: vi.fn(),
}));

import {
    applyMessageViewedReceipts,
    applyUnreadCountUpdate,
    formatMessageTime,
    formatUnreadMessagesLabel,
    hasPartnerSessionChanged,
    normalizeConversationsPayload,
    redecryptStoredMessages,
    resolveChatMessageContent,
    resolveIncomingMessageContent,
    shouldRefreshInboxOnPageShow,
    syncUnreadCountsFromConversations,
} from './e2eeChatSession.js';

describe('formatMessageTime', () => {
    it('formats message timestamps for display', () => {
        expect(formatMessageTime('2026-07-02T18:30:00Z', 'America/New_York', 'en-US'))
            .toBe('Jul 2, 2026 2:30 PM');
        expect(formatMessageTime('')).toBe('');
    });
});

describe('applyMessageViewedReceipts', () => {
    it('marks matching messages as viewed', () => {
        const messages = [
            { publicId: 'msg-1', isViewed: false },
            { publicId: 'msg-2', isViewed: false },
            { publicId: 'msg-3', isViewed: false },
        ];

        applyMessageViewedReceipts(messages, ['msg-1', 'msg-3']);

        expect(messages).toEqual([
            { publicId: 'msg-1', isViewed: true },
            { publicId: 'msg-2', isViewed: false },
            { publicId: 'msg-3', isViewed: true },
        ]);
    });

    it('ignores empty or invalid public id payloads', () => {
        const messages = [{ publicId: 'msg-1', isViewed: false }];

        applyMessageViewedReceipts(messages, []);
        applyMessageViewedReceipts(messages, null);

        expect(messages[0].isViewed).toBe(false);
    });
});

describe('applyUnreadCountUpdate', () => {
    it('updates the unread count for a conversation', () => {
        const unreadCounts = { 'conv-1': 1 };

        applyUnreadCountUpdate(unreadCounts, {
            conversation_public_key: 'conv-1',
            unread_messages_count: 3,
        });

        expect(unreadCounts).toEqual({ 'conv-1': 3 });
    });

    it('adds a count for conversations that are not yet tracked', () => {
        const unreadCounts = {};

        applyUnreadCountUpdate(unreadCounts, {
            conversation_public_key: 'conv-2',
            unread_messages_count: 1,
        });

        expect(unreadCounts).toEqual({ 'conv-2': 1 });
    });

    it('ignores invalid payloads', () => {
        const unreadCounts = { 'conv-1': 2 };

        applyUnreadCountUpdate(unreadCounts, {
            conversation_public_key: '',
            unread_messages_count: 5,
        });
        applyUnreadCountUpdate(unreadCounts, {
            conversation_public_key: 'conv-1',
            unread_messages_count: -1,
        });
        applyUnreadCountUpdate(unreadCounts, {
            conversation_public_key: 'conv-1',
            unread_messages_count: '2',
        });
        applyUnreadCountUpdate(unreadCounts, null);

        expect(unreadCounts).toEqual({ 'conv-1': 2 });
    });
});

describe('formatUnreadMessagesLabel', () => {
    it('formats singular and plural unread labels', () => {
        expect(formatUnreadMessagesLabel(1, ':count unread message', ':count unread messages'))
            .toBe('1 unread message');
        expect(formatUnreadMessagesLabel(2, ':count unread message', ':count unread messages'))
            .toBe('2 unread messages');
    });
});

describe('normalizeConversationsPayload', () => {
    it('normalizes wrapped conversation payloads', () => {
        expect(normalizeConversationsPayload({
            conversations: [
                {
                    public_key: 'conv-1',
                    unread_messages_count: 2,
                    partner: { name: 'Bob', url: '/chat/bob' },
                    last_message_at: '2026-07-02T18:30:00Z',
                },
                {
                    public_key: '',
                    partner: { name: 'Skip', url: '/chat/skip' },
                },
            ],
        })).toEqual([
            {
                public_key: 'conv-1',
                unread_messages_count: 2,
                partner: { name: 'Bob', url: '/chat/bob' },
                last_message_at: '2026-07-02T18:30:00Z',
            },
        ]);
    });

    it('accepts a bare conversations array', () => {
        expect(normalizeConversationsPayload([
            {
                public_key: 'conv-2',
                unread_messages_count: '1',
                partner: { name: 'Carol', url: '/chat/carol' },
                last_message_at: null,
            },
        ])).toEqual([
            {
                public_key: 'conv-2',
                unread_messages_count: 1,
                partner: { name: 'Carol', url: '/chat/carol' },
                last_message_at: null,
            },
        ]);
    });
});

describe('syncUnreadCountsFromConversations', () => {
    it('replaces unread counts from the latest conversations payload', () => {
        const unreadCounts = { 'conv-old': 9 };

        syncUnreadCountsFromConversations(unreadCounts, [
            { public_key: 'conv-1', unread_messages_count: 3 },
            { public_key: 'conv-2', unread_messages_count: 0 },
        ]);

        expect(unreadCounts).toEqual({
            'conv-1': 3,
            'conv-2': 0,
        });
    });
});

describe('shouldRefreshInboxOnPageShow', () => {
    it('refreshes only when the page was restored from bfcache', () => {
        expect(shouldRefreshInboxOnPageShow({ persisted: true })).toBe(true);
        expect(shouldRefreshInboxOnPageShow({ persisted: false })).toBe(false);
        expect(shouldRefreshInboxOnPageShow(null)).toBe(false);
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
