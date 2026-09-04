import { hydrateMessageContent } from '../chatFileUpload.js';
import { decryptChatMessage } from '../cryptography/e2ee/session.js';
import { formatLocalDatetime } from '../formatLocalDatetime.js';

/**
 * @param {string} createdAt
 * @param {string | undefined} timeZone
 * @param {string | undefined} locale
 */
export function formatMessageTime(createdAt, timeZone = undefined, locale = undefined) {
    return formatLocalDatetime(createdAt, timeZone, locale);
}

/**
 * @param {string} currentFingerprint
 * @param {string} incomingFingerprint
 */
export function hasPartnerSessionChanged(currentFingerprint, incomingFingerprint) {
    return currentFingerprint !== '' && incomingFingerprint !== currentFingerprint;
}

/**
 * @param {string} payload
 * @param {CryptoKey} conversationKey
 * @param {string} decryptionFailedMessage
 * @returns {Promise<{ plaintext: string|null, decryptionError: string }>}
 */
export async function resolveChatMessageContent(payload, conversationKey, decryptionFailedMessage) {
    try {
        const plaintext = await decryptChatMessage(payload, conversationKey);

        return { plaintext, decryptionError: '' };
    } catch {
        return { plaintext: null, decryptionError: decryptionFailedMessage };
    }
}

/**
 * @param {Array<{ payload?: string, plaintext: string|null, decryptionError: string }>} messages
 * @param {CryptoKey} conversationKey
 * @param {string} decryptionFailedMessage
 */
export async function redecryptStoredMessages(messages, conversationKey, decryptionFailedMessage) {
    for (const message of messages) {
        if (! message.decryptionError || ! message.payload) {
            continue;
        }

        const resolved = await resolveChatMessageContent(
            message.payload,
            conversationKey,
            decryptionFailedMessage,
        );

        if (! resolved.decryptionError) {
            const hydrated = hydrateMessageContent(resolved.plaintext, '');

            message.plaintext = hydrated.plaintext;
            message.attachment = hydrated.attachment;
            message.decryptionError = '';
        }
    }
}

/**
 * @param {string} payload
 * @param {() => CryptoKey|null} getConversationKey
 * @param {string} decryptionFailedMessage
 * @param {() => Promise<{ conversationKey: CryptoKey, partnerFingerprint: string }|null>} refreshPartnerSession
 * @returns {Promise<{ plaintext: string|null, decryptionError: string }>}
 */
export async function resolveIncomingMessageContent(
    payload,
    getConversationKey,
    decryptionFailedMessage,
    refreshPartnerSession,
) {
    const conversationKey = getConversationKey();

    if (! conversationKey) {
        return { plaintext: null, decryptionError: decryptionFailedMessage };
    }

    let resolved = await resolveChatMessageContent(payload, conversationKey, decryptionFailedMessage);

    if (resolved.decryptionError) {
        const refreshed = await refreshPartnerSession();

        if (refreshed) {
            resolved = await resolveChatMessageContent(
                payload,
                refreshed.conversationKey,
                decryptionFailedMessage,
            );
        }
    }

    return resolved;
}
