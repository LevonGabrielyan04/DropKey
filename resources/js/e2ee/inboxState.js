/**
 * @param {Array<{ publicId: string, isViewed?: boolean }>} messages
 * @param {unknown} publicIds
 */
export function applyMessageViewedReceipts(messages, publicIds) {
    if (! Array.isArray(publicIds) || publicIds.length === 0) {
        return;
    }

    const viewedIds = new Set(publicIds);

    for (const message of messages) {
        if (viewedIds.has(message.publicId)) {
            message.isViewed = true;
        }
    }
}

/**
 * @param {Record<string, number>} unreadCounts
 * @param {{ conversation_public_key?: unknown, unread_messages_count?: unknown }} event
 */
export function applyUnreadCountUpdate(unreadCounts, event) {
    const conversationPublicKey = event?.conversation_public_key;
    const unreadMessagesCount = event?.unread_messages_count;

    if (typeof conversationPublicKey !== 'string' || conversationPublicKey === '') {
        return;
    }

    if (typeof unreadMessagesCount !== 'number' || ! Number.isFinite(unreadMessagesCount) || unreadMessagesCount < 0) {
        return;
    }

    unreadCounts[conversationPublicKey] = Math.floor(unreadMessagesCount);
}

/**
 * @param {unknown} payload
 * @returns {Array<{
 *   public_key: string,
 *   unread_messages_count: number,
 *   partner: { name: string, url: string },
 *   last_message_at: string|null
 * }>}
 */
export function normalizeConversationsPayload(payload) {
    const conversations = Array.isArray(payload?.conversations)
        ? payload.conversations
        : Array.isArray(payload)
            ? payload
            : [];

    return conversations.filter((conversation) => (
        typeof conversation?.public_key === 'string'
        && conversation.public_key !== ''
        && typeof conversation?.partner?.name === 'string'
        && typeof conversation?.partner?.url === 'string'
    )).map((conversation) => ({
        public_key: conversation.public_key,
        unread_messages_count: Number.isFinite(Number(conversation.unread_messages_count))
            ? Math.max(0, Math.floor(Number(conversation.unread_messages_count)))
            : 0,
        partner: {
            name: conversation.partner.name,
            url: conversation.partner.url,
        },
        last_message_at: typeof conversation.last_message_at === 'string'
            ? conversation.last_message_at
            : null,
    }));
}

/**
 * @param {Record<string, number>} unreadCounts
 * @param {Array<{ public_key: string, unread_messages_count: number }>} conversations
 */
export function syncUnreadCountsFromConversations(unreadCounts, conversations) {
    for (const key of Object.keys(unreadCounts)) {
        delete unreadCounts[key];
    }

    for (const conversation of conversations) {
        unreadCounts[conversation.public_key] = conversation.unread_messages_count;
    }
}

/**
 * @param {number} count
 * @param {string} one
 * @param {string} other
 */
export function formatUnreadMessagesLabel(count, one, other) {
    const template = count === 1 ? one : other;

    return template.replaceAll(':count', String(count));
}
