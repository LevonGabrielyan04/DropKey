{{--
    Install offer for the first two website visits.
    Visibility is controlled by resources/js/pwaInstallPrompt.js (no Alpine).
--}}
<div
    id="pwa-install-prompt"
    hidden
    aria-hidden="true"
    role="dialog"
    aria-labelledby="pwa-install-prompt-title"
    class="fixed inset-x-0 bottom-0 z-50 border-t-2 border-zinc-950 bg-emerald-50 p-4 shadow-lg dark:border-zinc-100 dark:bg-emerald-950/90"
>
    <div class="mx-auto flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p
                id="pwa-install-prompt-title"
                class="text-sm font-bold uppercase tracking-[0.16em] text-emerald-950 dark:text-emerald-100"
            >
                {{ __('Install :app', ['app' => config('app.name')]) }}
            </p>

            <p data-pwa-copy="chromium" class="mt-1 text-sm text-emerald-900 dark:text-emerald-100/90">
                {{ __('Add :app to your home screen for faster access.', ['app' => config('app.name')]) }}
            </p>

            <p data-pwa-copy="ios" hidden class="mt-1 text-sm text-emerald-900 dark:text-emerald-100/90">
                {{ __('On iPhone or iPad, tap Share, then Add to Home Screen.') }}
            </p>

            <p data-pwa-copy="manual" hidden class="mt-1 text-sm text-emerald-900 dark:text-emerald-100/90">
                {{ __('Use your browser menu to install or add this site to your home screen.') }}
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <button
                type="button"
                data-pwa-install
                class="inline-flex cursor-pointer items-center justify-center border-2 border-zinc-950 bg-emerald-500 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-400 dark:border-zinc-100"
            >
                {{ __('Install') }}
            </button>

            <button
                type="button"
                data-pwa-dismiss
                class="inline-flex cursor-pointer items-center justify-center border-2 border-zinc-950 bg-transparent px-4 py-2 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-200 dark:border-zinc-100 dark:text-emerald-100 dark:hover:bg-emerald-900/60"
            >
                {{ __('Not now') }}
            </button>
        </div>
    </div>
</div>
