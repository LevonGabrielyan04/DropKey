import { syncAppBadge } from '../appBadge.js';
import {
    buildAttachmentMetadata,
    downloadAndDecryptAttachment,
    hydrateMessageContent,
    prepareEncryptedUpload,
    requestUploadLink,
    serializeChatMessageContent,
    uploadFileToLink,
    validateSelectedFile,
} from '../chatFileUpload.js';
import {
    encryptChatMessage,
    establishSession,
    fetchPartnerConversationKey,
} from '../cryptography/e2ee/session.js';
import { applyMessageViewedReceipts } from './inboxState.js';
import {
    formatMessageTime,
    hasPartnerSessionChanged,
    redecryptStoredMessages,
    resolveChatMessageContent,
    resolveIncomingMessageContent,
} from './messageContent.js';
import {
    shouldRefreshInboxOnPageShow,
    shouldResumeChatSessionOnVisibility,
} from './visibility.js';

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
            unreadCountsChannel: null,
            localUserId: 0,
            localUserPublicId: '',
            recipientId: 0,
            csrfToken: '',
            messagesUrl: '',
            sendUrl: '',
            uploadsUrl: '',
            downloadsUrl: '',
            uploadMaxFileBytes: 10 * 1024 * 1024,
            messageViewedUrlTemplate: '',
            registerUrl: '',
            mineUrl: '',
            publicKeyUrl: '',
            decryptionFailedMessage: 'Unable to decrypt this message.',
            downloadingPath: '',
            downloadError: '',
            downloadErrorPath: '',
            wasHidden: false,
            bootstrapping: false,
            pendingBootstrap: false,
            handleVisibilityChange: null,
            handlePageShow: null,

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
                this.downloadsUrl = this.$el.dataset.downloadsUrl ?? '';
                this.uploadMaxFileBytes = Number(this.$el.dataset.uploadMaxFileBytes)
                    || (10 * 1024 * 1024);
                this.messageViewedUrlTemplate = this.$el.dataset.messageViewedUrlTemplate ?? '';
                this.registerUrl = this.$el.dataset.registerUrl ?? '';
                this.mineUrl = this.$el.dataset.mineUrl ?? '';
                this.publicKeyUrl = this.$el.dataset.publicKeyUrl ?? '';
                this.conversationPublicKey = this.$el.dataset.conversationPublicKey ?? '';
                this.decryptionFailedMessage = this.$el.dataset.decryptionFailedMessage
                    ?? 'Unable to decrypt this message.';

                this.bindVisibilityListeners();
                this.subscribeToUnreadCounts();
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

            async downloadAttachment(attachment) {
                if (! attachment || ! this.ready || ! this.conversationKey || this.downloadingPath !== '') {
                    return;
                }

                if (! this.downloadsUrl) {
                    this.downloadError = 'File downloads are unavailable.';
                    return;
                }

                this.downloadingPath = attachment.path;
                this.downloadError = '';
                this.downloadErrorPath = '';

                try {
                    await downloadAndDecryptAttachment({
                        attachment,
                        conversationKey: this.conversationKey,
                        downloadsUrl: this.downloadsUrl,
                        csrfToken: this.csrfToken,
                    });
                } catch (error) {
                    this.downloadErrorPath = attachment.path;
                    this.downloadError = error instanceof Error && error.message !== ''
                        ? error.message
                        : 'Failed to download file.';
                } finally {
                    this.downloadingPath = '';
                }
            },

            destroy() {
                this.unbindVisibilityListeners();
                this.leaveConversationChannel();
                this.leaveReceiptsChannel();
                this.leaveUnreadCountsChannel();
            },

            bindVisibilityListeners() {
                this.unbindVisibilityListeners();

                this.handleVisibilityChange = () => {
                    if (document.visibilityState === 'hidden') {
                        this.wasHidden = true;

                        return;
                    }

                    if (shouldResumeChatSessionOnVisibility(document.visibilityState, this.wasHidden)) {
                        this.wasHidden = false;
                        this.bootstrap();
                    }
                };

                this.handlePageShow = (event) => {
                    if (shouldRefreshInboxOnPageShow(event)) {
                        this.wasHidden = false;
                        this.bootstrap();
                    }
                };

                document.addEventListener('visibilitychange', this.handleVisibilityChange);
                window.addEventListener('pageshow', this.handlePageShow);
            },

            unbindVisibilityListeners() {
                if (this.handleVisibilityChange) {
                    document.removeEventListener('visibilitychange', this.handleVisibilityChange);
                    this.handleVisibilityChange = null;
                }

                if (this.handlePageShow) {
                    window.removeEventListener('pageshow', this.handlePageShow);
                    this.handlePageShow = null;
                }
            },

            async bootstrap() {
                if (this.bootstrapping) {
                    this.pendingBootstrap = true;

                    return;
                }

                this.bootstrapping = true;
                const isResume = this.ready;

                // Keep an already-ready thread visible while catching up after backgrounding.
                if (! isResume) {
                    this.loading = true;
                    this.ready = false;
                }

                this.error = '';

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
                    this.bootstrapping = false;

                    if (this.pendingBootstrap) {
                        this.pendingBootstrap = false;
                        this.bootstrap();
                    }
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

            subscribeToUnreadCounts() {
                if (! this.localUserPublicId || ! window.Echo) {
                    return;
                }

                this.leaveUnreadCountsChannel();

                this.unreadCountsChannel = window.Echo
                    .private(`chat.${this.localUserPublicId}`)
                    .listen('.ChatUnreadCount', (event) => {
                        if (typeof event?.total_unread_messages_count === 'number'
                            && Number.isFinite(event.total_unread_messages_count)) {
                            void syncAppBadge(event.total_unread_messages_count);
                        }
                    });
            },

            leaveUnreadCountsChannel() {
                if (! this.localUserPublicId || ! window.Echo) {
                    return;
                }

                window.Echo.leave(`chat.${this.localUserPublicId}`);
                this.unreadCountsChannel = null;
            },

            /**
             * @param {unknown} publicIds
             */
            markMessagesAsViewed(publicIds) {
                applyMessageViewedReceipts(this.messages, publicIds);
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
    });
}
