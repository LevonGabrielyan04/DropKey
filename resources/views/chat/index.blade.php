<x-layouts::app :title="__('Messages')">
    <div
        x-data="e2eeChatInbox"
        data-chat-open-url="{{ url('/chat/to') }}"
        data-conversations-url="{{ route('conversations.index') }}"
        data-local-user-public-id="{{ auth()->user()->public_key }}"
        data-initial-conversations='@json($conversationsPayload['conversations'])'
        data-unread-label-one="{{ __(':count unread message') }}"
        data-unread-label-other="{{ __(':count unread messages') }}"
        data-empty-conversations-label="{{ __('No conversations yet.') }}"
        class="flex h-full min-w-0 w-full flex-1 flex-col font-mono"
    >
        <header class="border-2 border-zinc-950 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-950">
            <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                {{ __('Encrypted inbox') }}
            </div>

            <div class="p-6">
                <flux:heading size="xl" class="!font-mono !text-2xl !font-black !uppercase !tracking-tight !text-zinc-950 dark:!text-zinc-50">
                    {{ __('Messages') }}
                </flux:heading>

                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Pairwise encrypted channels. Plaintext never touches our servers.') }}
                </p>
            </div>
        </header>

        @include('partials.chat-notifications-tip', [
            'message' => __('Enable notifications so you know when a new message arrives.'),
        ])

        <section class="border-x-2 border-b-2 border-zinc-950 dark:border-zinc-100">
            <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3 dark:border-zinc-100 dark:bg-zinc-800">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600 dark:text-zinc-400">
                    {{ __('Start a conversation') }}
                </p>
            </div>

            <form @submit.prevent="startChat" class="bg-white p-6 dark:bg-zinc-950">
                <label for="chat-username" class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                    {{ __('Recipient user name') }}
                </label>

                <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                    <input
                        id="chat-username"
                        type="text"
                        x-model="username"
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="{{ __('Enter a registered user name') }}"
                        class="block w-full !rounded-none border-2 border-zinc-950 bg-white px-3 py-2.5 font-mono text-sm text-zinc-950 focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500 dark:border-zinc-100 dark:bg-zinc-900 dark:text-zinc-50"
                    />

                    <button
                        type="submit"
                        class="inline-flex shrink-0 cursor-pointer items-center justify-center !rounded-none border-2 border-zinc-950 bg-emerald-500 px-4 py-3 text-xs font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-400 dark:border-zinc-100"
                    >
                        {{ __('Open channel') }}
                    </button>
                </div>

                <span x-show="error" x-text="error" x-cloak class="mt-2 block text-sm text-red-600"></span>
            </form>
        </section>

        <section class="min-w-0 border-x-2 border-b-2 border-zinc-950 dark:border-zinc-100">
            <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3 dark:border-zinc-100 dark:bg-zinc-800">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600 dark:text-zinc-400">
                    {{ __('Recent conversations') }}
                </p>
            </div>

            <p
                x-show="conversations.length === 0"
                x-text="emptyConversationsLabel"
                class="bg-white p-6 text-sm text-zinc-500 dark:bg-zinc-950"
                @if ($conversations->isNotEmpty())
                    style="display: none;"
                @endif
            >{{ __('No conversations yet.') }}</p>

            <ul
                x-show="conversations.length > 0"
                class="min-w-0 divide-y-2 divide-zinc-950 bg-white dark:divide-zinc-100 dark:bg-zinc-950"
                @if ($conversations->isEmpty())
                    style="display: none;"
                @endif
            >
                <template x-for="conversation in conversations" :key="conversation.public_key">
                    <li class="min-w-0">
                        <a
                            :href="conversation.partner.url"
                            wire:navigate
                            class="flex min-w-0 items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-900"
                        >
                            <span
                                x-text="conversation.partner.name"
                                :title="conversation.partner.name"
                                class="min-w-0 truncate text-sm font-bold uppercase tracking-[0.16em] text-zinc-950 dark:text-zinc-50"
                            ></span>

                            <span class="flex shrink-0 items-center gap-3">
                                <span
                                    x-show="unreadCountFor(conversation.public_key) > 0"
                                    x-text="unreadCountFor(conversation.public_key)"
                                    :aria-label="unreadLabelFor(conversation.public_key)"
                                    class="inline-flex min-w-6 items-center justify-center border-2 border-zinc-950 bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-emerald-950 dark:border-zinc-100"
                                ></span>

                                <span
                                    x-show="conversation.last_message_at"
                                    :data-utc-datetime="conversation.last_message_at"
                                    x-text="formatConversationTime(conversation.last_message_at)"
                                    class="text-[10px] uppercase tracking-[0.14em] text-zinc-500"
                                ></span>
                            </span>
                        </a>
                    </li>
                </template>
            </ul>
        </section>
    </div>
</x-layouts::app>
