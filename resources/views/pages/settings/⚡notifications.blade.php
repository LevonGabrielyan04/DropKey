<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Notification settings') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Notifications')"
        :subheading="__('Get alerted when you receive a new encrypted message')"
    >
        <div
            class="space-y-4"
            x-data="pushNotificationSettings"
            data-vapid-public-key-url="{{ route('api.push.vapid-public-key') }}"
            data-store-url="{{ route('api.push-subscriptions.store') }}"
            data-destroy-url="{{ route('api.push-subscriptions.destroy') }}"
            data-csrf-token="{{ csrf_token() }}"
        >
            <flux:callout icon="bell" variant="secondary">
                <flux:callout.heading>{{ __('Message alerts') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Notifications stay generic — "You have a new message" — so message contents are never included.') }}
                </flux:callout.text>
            </flux:callout>

            <template x-if="! supported && ios && ! installed">
                <flux:callout icon="device-phone-mobile" color="amber">
                    <flux:callout.heading>{{ __('Install the app for notifications') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('On iPhone and iPad, open Share, then Add to Home Screen. After you install :app, return here to enable push notifications.', ['app' => config('app.name')]) }}
                    </flux:callout.text>
                </flux:callout>
            </template>

            <template x-if="! supported && ios && installed">
                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.heading>{{ __('Not supported') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Push notifications on this installed app require iOS 16.4 or later.') }}
                    </flux:callout.text>
                </flux:callout>
            </template>

            <template x-if="! supported && ! ios">
                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.heading>{{ __('Not supported') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Push notifications require a modern browser with service worker support, and a secure context (HTTPS or localhost).') }}
                    </flux:callout.text>
                </flux:callout>
            </template>

            <template x-if="supported && permission === 'denied'">
                <flux:callout icon="no-symbol" color="red">
                    <flux:callout.heading>{{ __('Permission blocked') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Notifications are blocked in your browser settings. Allow them for this site, then enable again here.') }}
                    </flux:callout.text>
                </flux:callout>
            </template>

            <div class="space-y-3" x-show="supported" x-cloak>
                <flux:text class="text-sm">
                    <span x-text="enabled ? @js(__('Push notifications are enabled on this device.')) : @js(__('Push notifications are disabled on this device.'))"></span>
                </flux:text>

                <div class="flex flex-wrap gap-2">
                    <flux:button
                        type="button"
                        variant="primary"
                        x-show="! enabled"
                        x-bind:disabled="busy || permission === 'denied'"
                        x-on:click="enable"
                    >
                        {{ __('Enable notifications') }}
                    </flux:button>

                    <flux:button
                        type="button"
                        variant="danger"
                        x-show="enabled"
                        x-bind:disabled="busy"
                        x-on:click="disable"
                    >
                        {{ __('Disable notifications') }}
                    </flux:button>
                </div>

                <flux:text
                    x-show="error"
                    x-text="error"
                    class="text-sm text-red-600 dark:text-red-400"
                ></flux:text>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
