import {
    buildAttachmentMetadata,
    hydrateMessageContent,
    prepareEncryptedUpload,
    requestUploadLink,
    serializeChatMessageContent,
    uploadFileToLink,
    validateSelectedFile,
} from './chatFileUpload.js';
import {
    decryptChatMessage,
    encryptChatMessage,
    establishSession,
    fetchPartnerConversationKey,
} from './cryptography/e2ee/session.js';
import { formatLocalDatetime } from './formatLocalDatetime.js';

export const DEFAULT_AUTO_DELETE = '7 days';

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
 * @param {{ persisted?: boolean } | null | undefined} event
 */
export function shouldRefreshInboxOnPageShow(event) {
    return Boolean(event?.persisted);
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

/**
 * Alpine component for a 1v1 E2EE chat session.
 * All crypto runs in the browser via Web Crypto; the server relays ciphertext only.
 */
if (typeof document !== 'undefined') {
document.addEventListener('alpine:init', () => {
    Alpine.data('e2eeChatSession', () => ({
        loading: true,
        ready: false,
        error: '',
        messages: [],
        messageText: '',
        selectedFile: null,
        selectedFileName: '',
        sending: false,
        sendError: '',
        partnerFingerprint: '',
        conversationKey: null,
        lastMessagePublicId: '',
        conversationPublicKey: '',
        conversationChannel: null,
        receiptsChannel: null,
        localUserId: 0,
        localUserPublicId: '',
        recipientId: 0,
        csrfToken: '',
        messagesUrl: '',
        sendUrl: '',
        uploadsUrl: '',
        uploadMaxFileBytes: 10 * 1024 * 1024,
        messageViewedUrlTemplate: '',
        registerUrl: '',
        mineUrl: '',
        publicKeyUrl: '',
        decryptionFailedMessage: 'Unable to decrypt this message.',
        autoDelete: DEFAULT_AUTO_DELETE,
        autoDeleteUrl: '',
        autoDeleteSaving: false,
        autoDeleteError: '',

        get canSendMessage() {
            return this.ready
                && ! this.sending
                && (this.messageText.trim() !== '' || this.selectedFile !== null);
        },

        formatMessageTime,

        init() {
            this.localUserId = Number(this.$el.dataset.localUserId);
            this.localUserPublicId = this.$el.dataset.localUserPublicId ?? '';
            this.recipientId = Number(this.$el.dataset.recipientId);
            this.csrfToken = this.$el.dataset.csrfToken ?? '';
            this.messagesUrl = this.$el.dataset.messagesUrl ?? '';
            this.sendUrl = this.$el.dataset.sendUrl ?? '';
            this.uploadsUrl = this.$el.dataset.uploadsUrl ?? '';
            this.uploadMaxFileBytes = Number(this.$el.dataset.uploadMaxFileBytes)
                || (10 * 1024 * 1024);
            this.messageViewedUrlTemplate = this.$el.dataset.messageViewedUrlTemplate ?? '';
            this.registerUrl = this.$el.dataset.registerUrl ?? '';
            this.mineUrl = this.$el.dataset.mineUrl ?? '';
            this.publicKeyUrl = this.$el.dataset.publicKeyUrl ?? '';
            this.conversationPublicKey = this.$el.dataset.conversationPublicKey ?? '';
            this.decryptionFailedMessage = this.$el.dataset.decryptionFailedMessage
                ?? 'Unable to decrypt this message.';
            this.autoDelete = this.$el.dataset.autoDelete || DEFAULT_AUTO_DELETE;
            this.autoDeleteUrl = this.$el.dataset.autoDeleteUrl ?? '';

            this.bootstrap();
        },

        openFilePicker() {
            this.$refs.fileInput?.click();
        },

        /**
         * @param {Event} event
         */
        onFileSelected(event) {
            const input = event.target;

            if (! (input instanceof HTMLInputElement)) {
                return;
            }

            const file = input.files?.[0] ?? null;
            input.value = '';

            if (! file) {
                return;
            }

            const validationError = validateSelectedFile(file, this.uploadMaxFileBytes);

            if (validationError !== '') {
                this.sendError = validationError;
                return;
            }

            this.selectedFile = file;
            this.selectedFileName = file.name;
            this.sendError = '';
        },

        clearSelectedFile() {
            this.selectedFile = null;
            this.selectedFileName = '';
        },

        destroy() {
            this.leaveConversationChannel();
            this.leaveReceiptsChannel();
        },

        async bootstrap() {
            this.loading = true;
            this.error = '';
            this.ready = false;

            try {
                const session = await establishSession({
                    localUserId: this.localUserId,
                    recipientId: this.recipientId,
                    publicKeyUrl: this.publicKeyUrl,
                    registerUrl: this.registerUrl,
                    mineUrl: this.mineUrl,
                    csrfToken: this.csrfToken,
                });

                this.conversationKey = session.conversationKey;
                this.partnerFingerprint = session.partnerFingerprint;

                await this.fetchMessages();
                this.subscribeToConversation();
                this.subscribeToReceipts();
                this.ready = true;
            } catch {
                this.error = 'Unable to establish an encrypted session. Ensure your partner has opened Messages at least once.';
            } finally {
                this.loading = false;
            }
        },

        async syncPartnerSession() {
            const partnerSession = await fetchPartnerConversationKey({
                localUserId: this.localUserId,
                recipientId: this.recipientId,
                publicKeyUrl: this.publicKeyUrl,
            });

            if (! partnerSession) {
                return null;
            }

            if (! hasPartnerSessionChanged(this.partnerFingerprint, partnerSession.partnerFingerprint)) {
                return null;
            }

            this.conversationKey = partnerSession.conversationKey;
            this.partnerFingerprint = partnerSession.partnerFingerprint;

            await redecryptStoredMessages(
                this.messages,
                this.conversationKey,
                this.decryptionFailedMessage,
            );

            return partnerSession;
        },

        async fetchMessages() {
            if (! this.conversationKey || ! this.messagesUrl) {
                return;
            }

            await this.syncPartnerSession();

            const url = this.lastMessagePublicId !== ''
                ? `${this.messagesUrl}?after_public_id=${encodeURIComponent(this.lastMessagePublicId)}`
                : this.messagesUrl;

            let response;

            try {
                response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
            } catch {
                return;
            }

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            const incoming = Array.isArray(data.messages) ? data.messages : [];

            for (const message of incoming) {
                await this.ingestMessage(message);
            }

            if (incoming.length > 0) {
                this.lastMessagePublicId = incoming[incoming.length - 1].public_id;
            }
        },

        async ingestMessage(message) {
            const existing = this.messages.find((item) => item.publicId === message.public_id);

            if (existing) {
                existing.isViewed = Boolean(message.is_viewed);

                if (existing.decryptionError) {
                    const resolved = await resolveChatMessageContent(
                        message.payload,
                        this.conversationKey,
                        this.decryptionFailedMessage,
                    );

                    if (! resolved.decryptionError) {
                        const hydrated = hydrateMessageContent(resolved.plaintext, '');

                        existing.plaintext = hydrated.plaintext;
                        existing.attachment = hydrated.attachment;
                        existing.decryptionError = '';
                    }

                    existing.payload = message.payload;
                }

                this.acknowledgeMessageViewed(message);

                return;
            }

            const { plaintext, decryptionError } = await resolveIncomingMessageContent(
                message.payload,
                () => this.conversationKey,
                this.decryptionFailedMessage,
                () => this.syncPartnerSession(),
            );

            const hydrated = hydrateMessageContent(plaintext, decryptionError);

            this.messages.push({
                publicId: message.public_id,
                senderPublicId: message.sender.public_id,
                payload: message.payload,
                plaintext: hydrated.plaintext,
                attachment: hydrated.attachment,
                decryptionError: hydrated.decryptionError,
                createdAt: message.created_at,
                isMine: message.sender.public_id === this.localUserPublicId,
                isViewed: Boolean(message.is_viewed),
            });

            this.sortMessages();
            this.scrollToBottom();
            this.acknowledgeMessageViewed(message);
        },

        /**
         * @param {{ public_id?: unknown, sender?: { public_id?: unknown }, is_viewed?: unknown }} message
         */
        acknowledgeMessageViewed(message) {
            if (
                ! this.messageViewedUrlTemplate
                || ! message?.public_id
                || message.sender?.public_id === this.localUserPublicId
                || Boolean(message.is_viewed)
            ) {
                return;
            }

            const url = this.messageViewedUrlTemplate.replace(
                '__PUBLIC_ID__',
                encodeURIComponent(String(message.public_id)),
            );

            fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                credentials: 'same-origin',
            }).catch(() => {});
        },

        sortMessages() {
            this.messages.sort((left, right) => new Date(left.createdAt) - new Date(right.createdAt));
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.messageList;

                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        subscribeToConversation() {
            if (! this.conversationPublicKey || ! window.Echo) {
                return;
            }

            this.leaveConversationChannel();

            this.conversationChannel = window.Echo
                .private(`conversation.${this.conversationPublicKey}`)
                .listen('.ChatMessageSent', async (event) => {
                    if (event.sender.public_id !== this.localUserPublicId) {
                        await this.ingestMessage(event);
                    }
                });
        },

        leaveConversationChannel() {
            if (! this.conversationPublicKey || ! window.Echo) {
                return;
            }

            window.Echo.leave(`conversation.${this.conversationPublicKey}`);
            this.conversationChannel = null;
        },

        subscribeToReceipts() {
            if (! this.conversationPublicKey || ! window.Echo) {
                return;
            }

            this.leaveReceiptsChannel();

            this.receiptsChannel = window.Echo
                .private(`conversation.${this.conversationPublicKey}.receipts`)
                .listen('.ChatMessagesViewed', (event) => {
                    this.markMessagesAsViewed(event.public_ids);
                });
        },

        leaveReceiptsChannel() {
            if (! this.conversationPublicKey || ! window.Echo) {
                return;
            }

            window.Echo.leave(`conversation.${this.conversationPublicKey}.receipts`);
            this.receiptsChannel = null;
        },

        /**
         * @param {unknown} publicIds
         */
        markMessagesAsViewed(publicIds) {
            applyMessageViewedReceipts(this.messages, publicIds);
        },

        async updateAutoDelete() {
            if (! this.autoDeleteUrl || this.autoDeleteSaving) {
                return;
            }

            const previousAutoDelete = this.$el.dataset.autoDelete || DEFAULT_AUTO_DELETE;
            this.autoDeleteSaving = true;
            this.autoDeleteError = '';

            try {
                const response = await fetch(this.autoDeleteUrl, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        auto_delete: this.autoDelete,
                    }),
                });

                if (! response.ok) {
                    this.autoDelete = previousAutoDelete;
                    this.autoDeleteError = 'Failed to update auto-delete setting.';
                    return;
                }

                const data = await response.json();
                this.autoDelete = data.auto_delete ?? this.autoDelete;
                this.$el.dataset.autoDelete = this.autoDelete;
            } catch {
                this.autoDelete = previousAutoDelete;
                this.autoDeleteError = 'Failed to update auto-delete setting.';
            } finally {
                this.autoDeleteSaving = false;
            }
        },

        async sendMessage() {
            const text = this.messageText.trim();
            const file = this.selectedFile;

            if (! this.ready || this.sending || ! this.conversationKey) {
                return;
            }

            if (text === '' && ! file) {
                return;
            }

            this.sending = true;
            this.sendError = '';

            try {
                let attachment = null;

                if (file) {
                    if (! this.uploadsUrl) {
                        this.sendError = 'File uploads are unavailable.';
                        return;
                    }

                    const encrypted = await prepareEncryptedUpload(file, this.conversationKey);
                    const link = await requestUploadLink({
                        uploadsUrl: this.uploadsUrl,
                        csrfToken: this.csrfToken,
                        filename: file.name,
                        contentType: encrypted.uploadContentType,
                        size: encrypted.uploadSize,
                    });

                    await uploadFileToLink(encrypted.body, link);
                    attachment = buildAttachmentMetadata(file, link.path, {
                        iv: encrypted.iv,
                        v: encrypted.v,
                    });
                }

                const content = serializeChatMessageContent({ text, attachment });
                const payload = await encryptChatMessage(content, this.conversationKey);

                const response = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        recipient_id: this.recipientId,
                        payload,
                    }),
                });

                if (! response.ok) {
                    this.sendError = 'Failed to send encrypted message.';
                    return;
                }

                const created = await response.json();

                if (created.conversation_public_key && ! this.conversationPublicKey) {
                    this.conversationPublicKey = created.conversation_public_key;
                    this.$el.dataset.conversationPublicKey = this.conversationPublicKey;
                    this.subscribeToConversation();
                    this.subscribeToReceipts();
                }

                this.messages.push({
                    publicId: created.public_id,
                    senderPublicId: this.localUserPublicId,
                    plaintext: text,
                    attachment,
                    decryptionError: '',
                    createdAt: created.created_at,
                    isMine: true,
                    isViewed: Boolean(created.is_viewed),
                });

                this.lastMessagePublicId = created.public_id;
                this.messageText = '';
                this.clearSelectedFile();
                this.sortMessages();
                this.scrollToBottom();
            } catch (error) {
                this.sendError = error instanceof Error && error.message !== ''
                    ? error.message
                    : 'Encryption or delivery failed. Try again.';
            } finally {
                this.sending = false;
            }
        },
    }));

    Alpine.data('e2eeChatInbox', () => ({
        username: '',
        error: '',
        localUserPublicId: '',
        conversations: [],
        unreadCounts: {},
        unreadLabelOne: ':count unread message',
        unreadLabelOther: ':count unread messages',
        emptyConversationsLabel: 'No conversations yet.',
        unreadCountsChannel: null,
        chatOpenUrl: '/chat/to',
        conversationsUrl: '',
        refreshingConversations: false,
        pendingConversationsRefresh: false,
        handleNavigated: null,
        handlePageShow: null,

        init() {
            this.chatOpenUrl = this.$el.dataset.chatOpenUrl ?? '/chat/to';
            this.conversationsUrl = this.$el.dataset.conversationsUrl ?? '';
            this.localUserPublicId = this.$el.dataset.localUserPublicId ?? '';
            this.unreadLabelOne = this.$el.dataset.unreadLabelOne ?? this.unreadLabelOne;
            this.unreadLabelOther = this.$el.dataset.unreadLabelOther ?? this.unreadLabelOther;
            this.emptyConversationsLabel = this.$el.dataset.emptyConversationsLabel ?? this.emptyConversationsLabel;

            try {
                this.conversations = normalizeConversationsPayload({
                    conversations: JSON.parse(this.$el.dataset.initialConversations || '[]'),
                });
            } catch {
                this.conversations = [];
            }

            syncUnreadCountsFromConversations(this.unreadCounts, this.conversations);
            this.subscribeToUnreadCounts();
            this.bindInboxVisibilityListeners();
            // Livewire navigate / browser back can restore a cached inbox with stale badges.
            this.refreshConversations();
        },

        destroy() {
            this.leaveUnreadCountsChannel();
            this.unbindInboxVisibilityListeners();
        },

        bindInboxVisibilityListeners() {
            this.unbindInboxVisibilityListeners();

            this.handleNavigated = () => {
                this.refreshConversations();
            };
            this.handlePageShow = (event) => {
                if (shouldRefreshInboxOnPageShow(event)) {
                    this.refreshConversations();
                }
            };

            document.addEventListener('livewire:navigated', this.handleNavigated);
            window.addEventListener('pageshow', this.handlePageShow);
        },

        unbindInboxVisibilityListeners() {
            if (this.handleNavigated) {
                document.removeEventListener('livewire:navigated', this.handleNavigated);
                this.handleNavigated = null;
            }

            if (this.handlePageShow) {
                window.removeEventListener('pageshow', this.handlePageShow);
                this.handlePageShow = null;
            }
        },

        /**
         * @param {string|null|undefined} createdAt
         */
        formatConversationTime(createdAt) {
            return formatMessageTime(createdAt ?? '');
        },

        /**
         * @param {string} conversationPublicKey
         */
        unreadCountFor(conversationPublicKey) {
            return Number(this.unreadCounts[conversationPublicKey] ?? 0);
        },

        /**
         * @param {string} conversationPublicKey
         */
        unreadLabelFor(conversationPublicKey) {
            return formatUnreadMessagesLabel(
                this.unreadCountFor(conversationPublicKey),
                this.unreadLabelOne,
                this.unreadLabelOther,
            );
        },

        /**
         * @param {{ conversation_public_key?: unknown, unread_messages_count?: unknown, refresh?: unknown }} event
         */
        updateUnreadCount(event) {
            applyUnreadCountUpdate(this.unreadCounts, event);

            if (event?.refresh === true) {
                this.refreshConversations();
            }
        },

        async refreshConversations() {
            if (! this.conversationsUrl) {
                return;
            }

            if (this.refreshingConversations) {
                this.pendingConversationsRefresh = true;

                return;
            }

            this.refreshingConversations = true;

            try {
                const response = await fetch(this.conversationsUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    return;
                }

                const data = await response.json();
                this.conversations = normalizeConversationsPayload(data);
                syncUnreadCountsFromConversations(this.unreadCounts, this.conversations);
            } catch {
                // Keep the current inbox state if the refresh request fails.
            } finally {
                this.refreshingConversations = false;

                if (this.pendingConversationsRefresh) {
                    this.pendingConversationsRefresh = false;
                    this.refreshConversations();
                }
            }
        },

        subscribeToUnreadCounts() {
            if (! this.localUserPublicId || ! window.Echo) {
                return;
            }

            this.leaveUnreadCountsChannel();

            this.unreadCountsChannel = window.Echo
                .private(`chat.${this.localUserPublicId}`)
                .listen('.ChatUnreadCount', (event) => {
                    this.updateUnreadCount(event);
                });
        },

        leaveUnreadCountsChannel() {
            if (! this.localUserPublicId || ! window.Echo) {
                return;
            }

            window.Echo.leave(`chat.${this.localUserPublicId}`);
            this.unreadCountsChannel = null;
        },

        startChat() {
            this.error = '';
            const name = this.username.trim().replace(/,/g, '');

            if (! name) {
                return;
            }

            if (name.length > 255) {
                this.error = 'User name must be 255 characters or fewer.';
                return;
            }

            const segment = encodeURIComponent(name);

            if (window.Livewire?.navigate) {
                window.Livewire.navigate(`${this.chatOpenUrl}/${segment}`);
            } else {
                window.location.href = `${this.chatOpenUrl}/${segment}`;
            }
        },
    }));
});
}
