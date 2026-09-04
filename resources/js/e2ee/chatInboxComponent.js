import {
    applyUnreadCountUpdate,
    formatUnreadMessagesLabel,
    normalizeConversationsPayload,
    syncUnreadCountsFromConversations,
} from './inboxState.js';
import { formatMessageTime } from './messageContent.js';
import { shouldRefreshInboxOnPageShow } from './visibility.js';

if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
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
