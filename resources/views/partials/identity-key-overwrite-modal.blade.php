<flux:modal
    name="identity-key-overwrite"
    :closable="false"
    :dismissible="false"
    class="max-w-md !rounded-none !border-2 !border-zinc-950 dark:!border-zinc-100"
    data-test="identity-key-overwrite-modal"
>
    <div class="space-y-6 font-mono">
        <div class="space-y-3 border-b-2 border-zinc-950 pb-4 dark:border-zinc-100">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-red-600 dark:text-red-400">
                {{ __('Destructive action') }}
            </p>

            <flux:heading size="lg" class="!font-mono !font-black !uppercase !tracking-tight">
                {{ __('Replace encryption key?') }}
            </flux:heading>

            <flux:text class="!font-mono !text-sm">
                {{ __('Replacing your encryption key will permanently remove access to your old messages. This cannot be undone.') }}
            </flux:text>

            <flux:callout
                variant="warning"
                icon="exclamation-triangle"
                class="!rounded-none !border-2 !border-zinc-950 dark:!border-zinc-100"
            >
                <flux:text class="!font-mono !text-sm">
                    {{ __('Your previous decryption key was not found on this device.') }}
                </flux:text>

                <flux:text class="!mt-2 !font-mono !text-sm">
                    {{ __('Browsers may automatically delete stored encryption keys under certain circumstances, such as after a period of inactivity or when using Incognito / private browsing.') }}
                </flux:text>

                <flux:text class="!mt-2 !font-mono !text-sm">
                    {{ __('If the key still exists elsewhere, sign in on the same device and browser where you originally encrypted your messages.') }}
                </flux:text>
            </flux:callout>
        </div>

        <div
            class="space-y-3 border-2 border-zinc-950 p-4 dark:border-zinc-100"
            x-show="passwordRequired"
            x-cloak
            data-test="identity-key-overwrite-password-section"
        >
            <flux:text class="!font-mono !text-sm">
                {{ __('Confirm your account password before replacing your encryption key.') }}
            </flux:text>

            <div class="relative" x-data="passwordVisibility">
                <label for="identity-key-overwrite-password" class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                    {{ __('Password') }}
                </label>

                <input
                    :type="showPassword ? 'text' : 'password'"
                    id="identity-key-overwrite-password"
                    x-model="password"
                    autocomplete="current-password"
                    placeholder="{{ __('Password') }}"
                    class="mt-2 block w-full !rounded-none border-2 border-zinc-950 bg-white px-3 py-2.5 pr-12 font-mono text-sm text-zinc-950 focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500 dark:border-zinc-100 dark:bg-zinc-900 dark:text-zinc-50"
                    data-test="identity-key-overwrite-password"
                />

                <button
                    type="button"
                    @click="toggle"
                    class="absolute bottom-2.5 right-3 border-2 border-transparent p-1 text-zinc-600 transition-colors hover:border-zinc-950 hover:text-zinc-950 dark:text-zinc-400 dark:hover:border-zinc-100 dark:hover:text-zinc-100"
                    :aria-label="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                >
                    <flux:icon.eye x-show="!showPassword" x-cloak variant="outline" class="size-4" />
                    <flux:icon.eye-slash x-show="showPassword" x-cloak variant="outline" class="size-4" />
                </button>
            </div>

            <span
                class="block text-sm text-red-600"
                x-show="passwordError"
                x-text="passwordError"
                x-cloak
                data-test="identity-key-overwrite-password-error"
            ></span>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button
                type="button"
                variant="filled"
                class="!rounded-none !border-2 !border-zinc-950 !font-bold !uppercase !tracking-[0.16em] dark:!border-zinc-100"
                data-test="identity-key-overwrite-cancel"
                x-on:click="cancelIdentityKeyOverwrite()"
            >
                {{ __('Cancel') }}
            </flux:button>

            <flux:button
                type="button"
                variant="danger"
                class="!rounded-none !border-2 !border-red-700 !font-bold !uppercase !tracking-[0.16em]"
                data-test="identity-key-overwrite-confirm"
                x-bind:disabled="confirming"
                x-on:click="confirmIdentityKeyOverwrite()"
            >
                <span x-show="! confirming">{{ __('Replace key') }}</span>
                <span x-show="confirming" x-cloak>{{ __('Verifying...') }}</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
