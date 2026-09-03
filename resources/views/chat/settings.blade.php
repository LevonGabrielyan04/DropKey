<x-layouts::app :title="__('Chat settings with :name', ['name' => $recipient->name])">
    <div
        class="flex h-full w-full flex-1 flex-col font-mono"
        x-data="e2eeChatSettings"
        data-local-user-id="{{ auth()->id() }}"
        data-recipient-id="{{ $recipient->id }}"
        data-csrf-token="{{ csrf_token() }}"
        data-public-key-url="{{ route('api.users.public-key.show', $recipient) }}"
        data-register-url="{{ route('api.identity.public-key.store') }}"
        data-mine-url="{{ route('api.identity.public-key.mine') }}"
        data-auto-delete="{{ $autoDelete->value }}"
        data-auto-delete-url="{{ route('conversations.auto-delete.update', $recipient) }}"
    >
        <header class="border-2 border-zinc-950 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-950">
            <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                {{ __('Chat settings') }}
            </div>

            <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <flux:heading size="xl" class="!font-mono !text-2xl !font-black !uppercase !tracking-tight !text-zinc-950 dark:!text-zinc-50">
                        {{ $recipient->name }}
                    </flux:heading>

                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Messages are encrypted in your browser before transmission. The server only relays ciphertext.') }}
                    </p>
                </div>

                <flux:button
                    :href="route('chat.show', $recipient)"
                    icon="arrow-left"
                    wire:navigate
                    class="!rounded-none !border-2 !border-zinc-950 !bg-zinc-50 !px-4 !py-3 !text-xs !font-bold !uppercase !tracking-[0.18em] !text-zinc-950 hover:!bg-zinc-200 dark:!border-zinc-100 dark:!bg-zinc-950 dark:!text-zinc-50 dark:hover:!bg-zinc-800"
                >
                    {{ __('Back to chat') }}
                </flux:button>
            </div>
        </header>

        <section class="border-x-2 border-b-2 border-zinc-950 dark:border-zinc-100">
            <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3 dark:border-zinc-100 dark:bg-zinc-800">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600 dark:text-zinc-400">
                    {{ __('Pairwise encrypted channel') }}
                </p>
            </div>

            <div class="space-y-6 bg-white p-6 dark:bg-zinc-950">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                        {{ __('Partner fingerprint') }}
                    </p>

                    <p x-show="loading" x-cloak class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Loading partner fingerprint...') }}
                    </p>

                    <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm text-red-600"></p>

                    <p
                        x-show="ready && partnerFingerprint"
                        x-text="partnerFingerprint"
                        x-cloak
                        class="mt-2 break-all text-[10px] uppercase tracking-[0.14em] text-zinc-500"
                    ></p>
                </div>

                <div class="max-w-xs">
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
        </section>
    </div>
</x-layouts::app>
