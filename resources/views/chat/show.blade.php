<x-layouts::app :title="__('Chat with :name', ['name' => $recipient->name])">
    <div
        class="flex h-full w-full flex-1 flex-col font-mono"
        x-data="e2eeChatSession"
        data-local-user-id="{{ auth()->id() }}"
        data-local-user-public-id="{{ auth()->user()->public_key }}"
        data-recipient-id="{{ $recipient->id }}"
        data-csrf-token="{{ csrf_token() }}"
        data-public-key-url="{{ route('api.users.public-key.show', $recipient) }}"
        data-register-url="{{ route('api.identity.public-key.store') }}"
        data-mine-url="{{ route('api.identity.public-key.mine') }}"
        data-messages-url="{{ route('messages.index', $recipient) }}"
        data-send-url="{{ route('messages.store') }}"
        data-uploads-url="{{ route('api.uploads.store') }}"
        data-downloads-url="{{ route('api.uploads.download') }}"
        data-upload-max-file-bytes="{{ (int) config('filesystems.upload.max_file_bytes') }}"
        data-message-viewed-url-template="{{ route('messages.viewed', ['message' => '__PUBLIC_ID__']) }}"
        data-conversation-public-key="{{ $conversation?->public_key ?? '' }}"
        data-decryption-failed-message="{{ __('Unable to decrypt this message.') }}"
        data-auto-delete="{{ $autoDelete->value }}"
        data-auto-delete-url="{{ route('conversations.auto-delete.update', $recipient) }}"
    >
        <header class="border-2 border-zinc-950 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-950">
            <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                {{ __('Pairwise encrypted channel') }}
            </div>

            <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <flux:heading size="xl" class="!font-mono !text-2xl !font-black !uppercase !tracking-tight !text-zinc-950 dark:!text-zinc-50">
                        {{ $recipient->name }}
                    </flux:heading>

                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Messages are encrypted in your browser before transmission. The server only relays ciphertext.') }}
                    </p>

                    <p
                        x-show="ready && partnerFingerprint"
                        x-text="'{{ __('Partner fingerprint') }}: ' + partnerFingerprint"
                        x-cloak
                        class="mt-2 break-all text-[10px] uppercase tracking-[0.14em] text-zinc-500"
                    ></p>

                    <div class="mt-4 max-w-xs">
                        <label for="auto-delete" class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                            {{ __('Auto-delete messages after') }}
                        </label>

                        <select
                            id="auto-delete"
                            x-model="autoDelete"
                            @change="updateAutoDelete"
                            :disabled="autoDeleteSaving"
                            class="mt-2 block w-full !rounded-none border-2 border-zinc-950 bg-white px-3 py-2 font-mono text-xs text-zinc-950 focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-100 dark:bg-zinc-900 dark:text-zinc-50"
                        >
                            @foreach ($timePeriods as $period)
                                <option value="{{ $period->value }}">{{ $period->value }}</option>
                            @endforeach
                        </select>

                        <span x-show="autoDeleteError" x-text="autoDeleteError" x-cloak class="mt-2 block text-xs text-red-600"></span>
                    </div>
                </div>

                <flux:button
                    :href="route('chat.index')"
                    icon="arrow-left"
                    wire:navigate
                    class="!rounded-none !border-2 !border-zinc-950 !bg-zinc-50 !px-4 !py-3 !text-xs !font-bold !uppercase !tracking-[0.18em] !text-zinc-950 hover:!bg-zinc-200 dark:!border-zinc-100 dark:!bg-zinc-950 dark:!text-zinc-50 dark:hover:!bg-zinc-800"
                >
                    {{ __('Inbox') }}
                </flux:button>
            </div>
        </header>

        @include('partials.chat-notifications-tip', [
            'message' => __('Enable notifications so you know when a new encrypted message arrives.'),
        ])

        <div class="border-x-2 border-b-2 border-zinc-950 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-950">
            <p x-show="loading" x-cloak class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Establishing encrypted session...') }}
            </p>

            <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600"></p>

            <p x-show="ready" x-cloak class="text-sm text-emerald-700 dark:text-emerald-400">
                {{ __('Encrypted session ready.') }}
            </p>
        </div>

        <div
            x-ref="messageList"
            x-show="ready"
            x-cloak
            class="flex max-h-[28rem] min-h-[12rem] flex-1 flex-col gap-3 overflow-y-auto border-x-2 border-b-2 border-zinc-950 bg-zinc-50 p-4 dark:border-zinc-100 dark:bg-zinc-900"
        >
            <template x-if="messages.length === 0">
                <p class="text-sm text-zinc-500">{{ __('No messages yet. Send the first encrypted message below.') }}</p>
            </template>

            <template x-for="message in messages" :key="message.publicId">
                <article
                    class="max-w-[85%] border-2 px-3 py-2 text-sm"
                    :class="message.decryptionError
                        ? (message.isMine
                            ? 'ms-auto border-red-600 bg-red-50 text-red-700 dark:border-red-500 dark:bg-red-950/40 dark:text-red-400'
                            : 'me-auto border-red-600 bg-red-50 text-red-700 dark:border-red-500 dark:bg-red-950/40 dark:text-red-400')
                        : (message.isMine
                            ? 'ms-auto border-zinc-950 bg-emerald-500/20 text-zinc-950 dark:border-zinc-100 dark:text-zinc-50'
                            : 'me-auto border-zinc-950 bg-white text-zinc-950 dark:border-zinc-100 dark:bg-zinc-950 dark:text-zinc-50')"
                >
                    <p
                        x-show="!message.decryptionError && message.plaintext"
                        class="whitespace-pre-wrap break-words"
                        x-text="message.plaintext"
                    ></p>
                    <div
                        x-show="!message.decryptionError && message.attachment"
                        x-cloak
                        class="mt-2 flex items-center gap-2 border-t-2 border-zinc-950/20 pt-2 text-xs dark:border-zinc-100/20"
                    >
                        <flux:icon.paper-clip class="size-3.5 shrink-0" />
                        <button
                            type="button"
                            class="min-w-0 truncate font-bold uppercase tracking-[0.08em] underline decoration-2 underline-offset-2 hover:text-emerald-700 disabled:cursor-wait disabled:no-underline disabled:opacity-60 dark:hover:text-emerald-400"
                            :disabled="downloadingPath === message.attachment.path"
                            @click="downloadAttachment(message.attachment)"
                            x-text="downloadingPath === message.attachment.path
                                ? '{{ __('Downloading…') }}'
                                : (message.attachment ? message.attachment.name : '')"
                        ></button>
                    </div>
                    <p
                        x-show="downloadError && message.attachment && downloadErrorPath === message.attachment.path"
                        x-cloak
                        x-text="downloadError"
                        class="mt-1 text-[10px] uppercase tracking-[0.08em] text-red-600"
                    ></p>
                    <p
                        x-show="message.decryptionError"
                        x-cloak
                        class="whitespace-pre-wrap break-words"
                        x-text="message.decryptionError"
                    ></p>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <p
                                class="text-[10px] uppercase tracking-[0.14em] text-zinc-500"
                                x-text="message.isMine ? '{{ __('You') }}' : '{{ $recipient->name }}'"
                            ></p>
                            <time
                                class="text-[10px] tracking-[0.08em] text-zinc-500"
                                :datetime="message.createdAt"
                                x-text="formatMessageTime(message.createdAt)"
                            ></time>
                        </div>
                        <span
                            x-show="message.isMine"
                            class="inline-flex shrink-0"
                            :class="message.isViewed
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-zinc-400 dark:text-zinc-500'"
                            :title="message.isViewed ? '{{ __('Viewed') }}' : '{{ __('Sent') }}'"
                            :aria-label="message.isViewed ? '{{ __('Viewed') }}' : '{{ __('Sent') }}'"
                        >
                            {{-- Lucide check-check --}}
                            <svg
                                class="size-3.5"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M18 6 7 17l-5-5" />
                                <path d="m22 10-7.5 7.5L13 16" />
                            </svg>
                        </span>
                    </div>
                </article>
            </template>
        </div>

        <form
            @submit.prevent="sendMessage"
            class="border-x-2 border-b-2 border-zinc-950 dark:border-zinc-100"
        >
            <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3 dark:border-zinc-100 dark:bg-zinc-800">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600 dark:text-zinc-400">
                    {{ __('Compose') }}
                </p>
            </div>

            <div class="bg-white p-6 dark:bg-zinc-950">
                <label for="message" class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                    {{ __('Message') }}
                </label>

                <div class="mt-2 flex items-stretch gap-2">
                    <textarea
                        id="message"
                        x-model="messageText"
                        rows="4"
                        placeholder="{{ __('Type your message...') }}"
                        class="block w-full min-w-0 flex-1 !rounded-none border-2 border-zinc-950 bg-white px-3 py-2.5 font-mono text-sm text-zinc-950 focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500 dark:border-zinc-100 dark:bg-zinc-900 dark:text-zinc-50"
                        :disabled="!ready || sending"
                    ></textarea>

                    <input
                        type="file"
                        x-ref="fileInput"
                        class="hidden"
                        @change="onFileSelected"
                    >

                    <button
                        type="button"
                        @click="openFilePicker"
                        :disabled="!ready || sending"
                        class="inline-flex shrink-0 cursor-pointer items-center justify-center !rounded-none border-2 border-zinc-950 bg-zinc-50 px-3 text-zinc-950 transition-colors hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-100 dark:bg-zinc-900 dark:text-zinc-50 dark:hover:bg-zinc-800"
                        aria-label="{{ __('Attach file') }}"
                        title="{{ __('Attach file') }}"
                    >
                        <flux:icon.paper-clip class="size-5" />
                    </button>
                </div>

                <div
                    x-show="selectedFileName"
                    x-cloak
                    class="mt-3 flex items-center justify-between gap-3 border-2 border-zinc-950 bg-zinc-50 px-3 py-2 text-xs dark:border-zinc-100 dark:bg-zinc-900"
                >
                    <span
                        class="min-w-0 truncate font-bold uppercase tracking-[0.08em] text-zinc-700 dark:text-zinc-300"
                        x-text="selectedFileName"
                    ></span>
                    <button
                        type="button"
                        @click="clearSelectedFile"
                        :disabled="sending"
                        class="shrink-0 cursor-pointer font-bold uppercase tracking-[0.14em] text-zinc-500 underline-offset-2 hover:text-zinc-950 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:hover:text-zinc-50"
                    >
                        {{ __('Remove') }}
                    </button>
                </div>

                <span x-show="sendError" x-text="sendError" x-cloak class="mt-2 block text-sm text-red-600"></span>
            </div>

            <div class="bg-zinc-50 p-6 dark:bg-zinc-900">
                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center !rounded-none border-2 border-zinc-950 bg-emerald-500 px-4 py-3 text-xs font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-100"
                    :disabled="!canSendMessage"
                >
                    <span x-show="!sending">{{ __('Send encrypted message') }}</span>
                    <span x-show="sending && selectedFile" x-cloak>{{ __('Uploading...') }}</span>
                    <span x-show="sending && !selectedFile" x-cloak>{{ __('Encrypting...') }}</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts::app>
