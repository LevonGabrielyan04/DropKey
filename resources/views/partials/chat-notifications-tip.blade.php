@unless (auth()->user()->pushSubscriptions()->exists())
    <div
        x-data="notificationsTip"
        data-storage-key="passshare.notifications-tip.dismissed.{{ auth()->id() }}"
        x-show="!dismissed"
        x-cloak
        class="border-x-2 border-b-2 border-zinc-950 bg-amber-50 dark:border-zinc-100 dark:bg-amber-950/40"
    >
        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-amber-950 dark:text-amber-100">
                {{ $message }}
            </p>

            <div class="flex shrink-0 items-center gap-2">
                <a
                    href="{{ route('notifications.edit') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center border-2 border-zinc-950 bg-amber-400 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-950 transition-colors hover:bg-amber-300 dark:border-zinc-100"
                >
                    {{ __('Notification settings') }}
                </a>

                <button
                    type="button"
                    @click="dismiss"
                    class="inline-flex size-9 cursor-pointer items-center justify-center border-2 border-zinc-950 bg-transparent text-amber-950 transition-colors hover:bg-amber-200 dark:border-zinc-100 dark:text-amber-100 dark:hover:bg-amber-900/60"
                    aria-label="{{ __('Dismiss') }}"
                    title="{{ __('Dismiss') }}"
                >
                    <flux:icon.x-mark variant="outline" class="size-4" />
                </button>
            </div>
        </div>
    </div>
@endunless
