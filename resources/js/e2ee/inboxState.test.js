import { describe, expect, it } from 'vitest';
import {
    applyMessageViewedReceipts,
    applyUnreadCountUpdate,
    formatUnreadMessagesLabel,
    normalizeConversationsPayload,
    syncUnreadCountsFromConversations,
    totalUnreadFromCounts,
} from './inboxState.js';

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

describe('totalUnreadFromCounts', () => {
    it('sums positive unread counts across conversations', () => {
        expect(totalUnreadFromCounts({
            'conv-1': 2,
            'conv-2': 3,
            'conv-3': 0,
        })).toBe(5);
    });

    it('ignores invalid unread values', () => {
        expect(totalUnreadFromCounts({
            'conv-1': 1,
            'conv-2': -2,
            'conv-3': Number.NaN,
        })).toBe(1);
    });
});
