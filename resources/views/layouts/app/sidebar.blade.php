<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        class="min-h-screen bg-zinc-50 font-mono dark:bg-zinc-950"
        data-browser-db-id="{{ auth()->user()->identityKey?->browser_db_id }}"
        data-identity-register-url="{{ route('api.identity.public-key.store') }}"
        data-identity-mine-url="{{ route('api.identity.public-key.mine') }}"
        data-password-confirmation-status-url="{{ route('password.confirmation') }}"
        data-password-confirm-url="{{ route('password.confirm.store') }}"
        data-csrf-token="{{ csrf_token() }}"
        x-data="identityKeyOverwriteModal()"
    >
        <flux:sidebar
            sticky
            collapsible="mobile"
            class="!rounded-none border-e-2 border-zinc-950 bg-zinc-100 dark:border-zinc-100 dark:bg-zinc-900"
        >
            <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1.5 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                {{ __('Secure workspace') }}
            </div>

            <flux:sidebar.header class="border-b-2 border-zinc-950 px-0 py-0 dark:border-zinc-100">
                <div class="flex w-full items-center justify-between px-4 py-4">
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                    <flux:sidebar.collapse class="lg:hidden" />
                </div>
            </flux:sidebar.header>

            <flux:sidebar.nav class="px-0 py-0">
                <div class="border-b-2 border-zinc-950 px-4 py-3 dark:border-zinc-100">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                        {{ __('Navigation') }}
                    </p>
                </div>

                <nav aria-label="{{ __('Primary navigation') }}" class="divide-y-2 divide-zinc-950 dark:divide-zinc-100">
                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        @class([
                            'group flex items-center gap-3 px-4 py-4 text-xs font-bold uppercase tracking-[0.18em] transition-colors',
                            'border-s-4 border-emerald-500 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400' => request()->routeIs('dashboard'),
                            'border-s-4 border-transparent text-zinc-700 hover:border-zinc-950 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:border-zinc-100 dark:hover:bg-zinc-800' => ! request()->routeIs('dashboard'),
                        ])
                    >
                        <flux:icon.home
                            variant="outline"
                            class="size-4 shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500 group-hover:text-zinc-950 dark:text-zinc-400 dark:group-hover:text-zinc-100' }}"
                        />
                        {{ __('Dashboard') }}
                    </a>

                    <a
                        href="{{ route('sends.create') }}"
                        wire:navigate
                        @class([
                            'group flex items-center gap-3 px-4 py-4 text-xs font-bold uppercase tracking-[0.18em] transition-colors',
                            'border-s-4 border-emerald-500 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400' => request()->routeIs('sends.*'),
                            'border-s-4 border-transparent text-zinc-700 hover:border-zinc-950 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:border-zinc-100 dark:hover:bg-zinc-800' => ! request()->routeIs('sends.*'),
                        ])
                    >
                        <flux:icon.plus
                            variant="outline"
                            class="size-4 shrink-0 {{ request()->routeIs('sends.*') ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500 group-hover:text-zinc-950 dark:text-zinc-400 dark:group-hover:text-zinc-100' }}"
                        />
                        {{ __('New Send') }}
                    </a>

                    <a
                        href="{{ route('chat.index') }}"
                        wire:navigate
                        @class([
                            'group flex items-center gap-3 px-4 py-4 text-xs font-bold uppercase tracking-[0.18em] transition-colors',
                            'border-s-4 border-emerald-500 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400' => request()->routeIs('chat.*'),
                            'border-s-4 border-transparent text-zinc-700 hover:border-zinc-950 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:border-zinc-100 dark:hover:bg-zinc-800' => ! request()->routeIs('chat.*'),
                        ])
                    >
                        <flux:icon.chat-bubble-left-right
                            variant="outline"
                            class="size-4 shrink-0 {{ request()->routeIs('chat.*') ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500 group-hover:text-zinc-950 dark:text-zinc-400 dark:group-hover:text-zinc-100' }}"
                        />
                        {{ __('Messages') }}
                    </a>
                </nav>
            </flux:sidebar.nav>

            <div class="border-y-2 border-zinc-950 px-4 py-4 lg:hidden dark:border-zinc-100">
                @include('partials.global-footer-content')
            </div>

            <flux:spacer />

            <div class="hidden border-t-2 border-zinc-950 dark:border-zinc-100 lg:block">
                <div class="border-b-2 border-zinc-950 px-4 py-2 dark:border-zinc-100">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                        {{ __('Session') }}
                    </p>
                </div>

                <flux:dropdown position="top" align="start" class="w-full">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-3 px-4 py-4 text-start transition-colors hover:bg-zinc-200 dark:hover:bg-zinc-800"
                        data-test="sidebar-menu-button"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center border-2 border-zinc-950 bg-zinc-50 text-[10px] font-bold uppercase tracking-wider text-zinc-950 dark:border-zinc-100 dark:bg-zinc-950 dark:text-zinc-50">
                            {{ auth()->user()->initials() }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
                                {{ auth()->user()->name }}
                            </span>
                            <span class="mt-0.5 block truncate text-[10px] text-zinc-500">
                                {{ auth()->user()->email }}
                            </span>
                        </span>

                        <flux:icon.chevrons-up-down variant="outline" class="size-4 shrink-0 text-zinc-500" />
                    </button>

                    <flux:menu class="!rounded-none !border-2 !border-zinc-950 !p-1 font-mono dark:!border-zinc-100">
                        <div class="border-b-2 border-zinc-950 px-3 py-3 dark:border-zinc-100">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                                {{ __('Operator') }}
                            </p>
                            <p class="mt-1 truncate text-xs font-bold text-zinc-950 dark:text-zinc-50">
                                {{ auth()->user()->name }}
                            </p>
                            <p class="mt-0.5 truncate text-[10px] text-zinc-500">
                                {{ auth()->user()->email }}
                            </p>
                        </div>

                        <flux:menu.separator class="!my-1 !bg-zinc-950 dark:!bg-zinc-100" />

                        <flux:menu.radio.group>
                            <flux:menu.item
                                :href="route('profile.edit')"
                                icon="cog"
                                wire:navigate
                                class="!rounded-none !text-xs !font-bold !uppercase !tracking-[0.16em]"
                            >
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator class="!my-1 !bg-zinc-950 dark:!bg-zinc-100" />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer !rounded-none !text-xs !font-bold !uppercase !tracking-[0.16em]"
                                data-test="logout-button"
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:sidebar>

        <flux:header class="border-b-2 border-zinc-950 bg-zinc-50 lg:hidden dark:border-zinc-100 dark:bg-zinc-950">
            <flux:sidebar.toggle
                class="lg:hidden"
                icon="bars-2"
                inset="left"
            />

            <div class="min-w-0 flex-1 px-2">
                <p class="truncate text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-400">
                    {{ __('Secure workspace') }}
                </p>
                <p class="truncate text-xs font-bold uppercase tracking-tight text-zinc-950 dark:text-zinc-50">
                    {{ config('app.name') }}
                </p>
            </div>

            <flux:dropdown position="bottom" align="end">
                <button
                    type="button"
                    class="flex size-10 cursor-pointer items-center justify-center border-2 border-zinc-950 bg-zinc-50 text-[10px] font-bold uppercase text-zinc-950 dark:border-zinc-100 dark:bg-zinc-950 dark:text-zinc-50"
                    aria-label="{{ __('Account menu') }}"
                >
                    {{ auth()->user()->initials() }}
                </button>

                <flux:menu class="!rounded-none !border-2 !border-zinc-950 !p-1 font-mono dark:!border-zinc-100">
                    <div class="border-b-2 border-zinc-950 px-3 py-3 dark:border-zinc-100">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">
                            {{ __('Operator') }}
                        </p>
                        <p class="mt-1 truncate text-xs font-bold text-zinc-950 dark:text-zinc-50">
                            {{ auth()->user()->name }}
                        </p>
                        <p class="mt-0.5 truncate text-[10px] text-zinc-500">
                            {{ auth()->user()->email }}
                        </p>
                    </div>

                    <flux:menu.separator class="!my-1 !bg-zinc-950 dark:!bg-zinc-100" />

                    <flux:menu.radio.group>
                        <flux:menu.item
                            :href="route('profile.edit')"
                            icon="cog"
                            wire:navigate
                            class="!rounded-none !text-xs !font-bold !uppercase !tracking-[0.16em]"
                        >
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="!my-1 !bg-zinc-950 dark:!bg-zinc-100" />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer !rounded-none !text-xs !font-bold !uppercase !tracking-[0.16em]"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @include('partials.global-footer')

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @include('partials.identity-key-overwrite-modal')

        @include('partials.flux-scripts')
    </body>
</html>
