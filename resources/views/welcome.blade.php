<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth scroll-pt-24">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.name') }} — end-to-end encrypted messaging between registered users. Messages are encrypted in your browser before they ever reach the server.">

        <title>{{ config('app.name') }} — End-to-end encrypted messaging</title>

        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="{{ route('manifest') }}">
        <meta name="theme-color" content="#09090b">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/registerServiceWorker.js'])

        <link rel="stylesheet" href="{{ asset('css/cursors.css') }}">
    </head>
    <body class="min-h-screen bg-zinc-50 font-mono antialiased">
        {{-- Sticky header --}}
        <header class="sticky top-0 z-50 border-b-2 border-zinc-950 bg-zinc-50">
            <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                End-to-end encrypted messaging
            </div>

            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="group flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center border-2 border-zinc-950 bg-emerald-500/10">
                        <x-app-logo-icon class="size-5 fill-emerald-700" />
                    </span>
                    <span class="text-sm font-bold uppercase tracking-tight text-zinc-950">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden items-center gap-6 text-xs font-bold uppercase tracking-[0.16em] text-zinc-600 md:flex">
                    <a href="#messages" class="transition-colors hover:text-emerald-700">Messages</a>
                    <a href="#sends" class="transition-colors hover:text-emerald-700">Sends</a>
                    <a href="#features" class="transition-colors hover:text-emerald-700">Features</a>
                    <a href="#security" class="transition-colors hover:text-emerald-700">Security</a>
                    <a href="#faq" class="transition-colors hover:text-emerald-700">FAQ</a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center justify-center border-2 border-zinc-950 bg-zinc-50 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-zinc-950 transition-colors hover:bg-zinc-200"
                        >
                            Dashboard
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-xs font-bold uppercase tracking-[0.16em] text-zinc-600 transition-colors hover:text-zinc-950">
                                Log in
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex items-center justify-center border-2 border-zinc-950 bg-emerald-500 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-400"
                            >
                                Get started
                            </a>
                        @endif
                    @endauth
                </div>

                {{-- Mobile menu --}}
                <details class="group relative md:hidden">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center border-2 border-zinc-950 bg-zinc-100 [&::-webkit-details-marker]:hidden">
                        <svg class="size-5 group-open:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="hidden size-5 group-open:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span class="sr-only">Menu</span>
                    </summary>
                    <div class="absolute end-0 top-full mt-2 w-56 border-2 border-zinc-950 bg-zinc-50 p-2 shadow-lg">
                        <nav class="flex flex-col divide-y-2 divide-zinc-950 text-xs font-bold uppercase tracking-[0.16em]">
                            <a href="#messages" class="px-3 py-2 text-zinc-700 hover:bg-zinc-200">Messages</a>
                            <a href="#sends" class="px-3 py-2 text-zinc-700 hover:bg-zinc-200">Sends</a>
                            <a href="#features" class="px-3 py-2 text-zinc-700 hover:bg-zinc-200">Features</a>
                            <a href="#security" class="px-3 py-2 text-zinc-700 hover:bg-zinc-200">Security</a>
                            <a href="#faq" class="px-3 py-2 text-zinc-700 hover:bg-zinc-200">FAQ</a>
                            @auth
                                <a href="{{ route('dashboard') }}" class="px-3 py-2 text-emerald-700 hover:bg-emerald-500/10">Dashboard</a>
                            @else
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="px-3 py-2 text-zinc-700 hover:bg-zinc-200">Log in</a>
                                @endif
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="px-3 py-2 text-emerald-700 hover:bg-emerald-500/10">Get started</a>
                                @endif
                            @endauth
                        </nav>
                    </div>
                </details>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {{-- Hero --}}
            <section class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                    Zero-knowledge relay
                </div>

                <div class="flex flex-col gap-8 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8">
                    <div class="max-w-2xl">
                        <h1 class="text-3xl font-black uppercase tracking-tight text-zinc-950 sm:text-4xl lg:text-5xl">
                            Private messages.<br class="hidden sm:block"> Zero server access.
                        </h1>
                        <p class="mt-4 text-sm leading-relaxed text-zinc-600">
                            {{ config('app.name') }} is built for end-to-end encrypted messaging between registered users.
                            Pairwise channels use ECDH + HKDF — and we also support one-time Sends when you need to drop a secret.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:items-end">
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex w-full items-center justify-center border-2 border-zinc-950 bg-emerald-500 px-5 py-3 text-xs font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-400 sm:w-auto"
                            >
                                Start messaging
                            </a>
                        @endif
                        <a
                            href="#how-messages"
                            class="inline-flex w-full items-center justify-center border-2 border-zinc-950 bg-zinc-50 px-5 py-3 text-xs font-bold uppercase tracking-[0.18em] text-zinc-950 transition-colors hover:bg-zinc-200 sm:w-auto"
                        >
                            See how it works
                        </a>
                    </div>
                </div>

                {{-- Product mockup --}}
                <div class="border-t-2 border-zinc-950">
                    <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">
                            Encrypted channel — dev_ops ↔ deploy_svc
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2">
                        <div class="space-y-4 border-b-2 border-zinc-950 bg-white p-6 sm:border-b-0 sm:border-r-2">
                            <div>
                                <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Recipient</label>
                                <div class="mt-2 border-2 border-zinc-950 bg-zinc-50 px-3 py-2.5 text-sm font-bold uppercase text-zinc-950">
                                    deploy_svc
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Key fingerprint</label>
                                <div class="mt-2 border-2 border-zinc-950 bg-zinc-50 px-3 py-2.5 font-mono text-xs tabular-nums text-emerald-800">
                                    A3F2 · 8C91 · 4E7B · 1D05
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Retention</label>
                                <div class="mt-2 border-2 border-zinc-950 bg-zinc-50 px-3 py-2.5 text-sm tabular-nums text-zinc-950">
                                    Auto-delete after 7 days
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 bg-zinc-50 p-6">
                            <div>
                                <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Message thread</label>
                                <div class="relative mt-2 border-2 border-zinc-950 bg-white text-xs leading-relaxed">
                                    <div class="relative z-0 space-y-3 p-4">
                                        <div class="border-s-2 border-emerald-500 ps-3 text-zinc-600">
                                            <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">deploy_svc · 14:02 UTC</span>
                                            <span class="blur-[2px] select-none">Rotation keys are ready on staging.</span>
                                        </div>
                                        <div class="border-s-2 border-zinc-400 ps-3 text-zinc-600">
                                            <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">you · 14:03 UTC</span>
                                            <span class="blur-[2px] select-none">Copying creds to the vault now.</span>
                                        </div>
                                    </div>
                                    <div class="absolute inset-0 z-10 flex items-center justify-center p-4">
                                        <div class="absolute inset-0 bg-zinc-950/5" aria-hidden="true"></div>
                                        <div class="relative flex items-center gap-2 border-2 border-emerald-500 bg-emerald-500/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-800">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            ECDH + HKDF · AES-256-GCM
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 border-2 border-emerald-500/40 bg-emerald-500/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-800">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Server relays ciphertext only
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">P-256 · Web Crypto</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Stats strip --}}
            <section class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">
                        Built for teams who take security seriously
                    </p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4">
                    @foreach ([
                        ['value' => 'ECDH', 'label' => 'P-256 key exchange'],
                        ['value' => 'AES-256', 'label' => 'GCM encryption'],
                        ['value' => '0', 'label' => 'Plaintext on server'],
                        ['value' => 'HKDF', 'label' => 'Conversation keys'],
                    ] as $stat)
                        <div @class([
                            'px-4 py-6 text-center',
                            'border-r-2 border-b-2 border-zinc-950 sm:border-b-0' => ! $loop->last,
                            'border-b-2 border-zinc-950 sm:border-b-0 sm:border-r-0' => $loop->last,
                        ])>
                            <p class="text-2xl font-black tabular-nums text-emerald-700">{{ $stat['value'] }}</p>
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Dual product pillars --}}
            <section class="mt-8 grid gap-8 lg:grid-cols-2">
                <article id="messages" class="border-2 border-zinc-950">
                    <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                        Encrypted messenger
                    </div>
                    <div class="p-6">
                        <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">Messages</h2>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                            Open pairwise encrypted channels with any registered user. Messages are encrypted with per-conversation keys
                            derived via ECDH (P-256) and HKDF. The server relays opaque ciphertext only.
                        </p>
                        <ul class="mt-4 space-y-2 border-t-2 border-zinc-950 pt-4 text-sm text-zinc-700">
                            <li>Identity key fingerprints to verify your partner</li>
                            <li>Private keys stay local in your browser</li>
                            <li>Auto-delete messages after 1 hour to 30 days</li>
                        </ul>
                    </div>
                </article>

                <article id="sends" class="border-2 border-zinc-950">
                    <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-zinc-600">
                        One-time secret sharing
                    </div>
                    <div class="p-6">
                        <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">Sends</h2>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                            Share passwords and secrets with up to 100 registered viewer names. Set expiry from 1 hour to 30 days.
                            Only listed viewers — plus you — can open a Send.
                        </p>
                        <ul class="mt-4 space-y-2 border-t-2 border-zinc-950 pt-4 text-sm text-zinc-700">
                            <li>Password-protected payloads encrypted with AES-256-GCM</li>
                            <li>Argon2id key derivation in a Web Worker</li>
                            <li>Automatic deletion when expiry is reached</li>
                        </ul>
                    </div>
                </article>
            </section>

            {{-- Features --}}
            <section id="features" class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">Capabilities</p>
                </div>

                <div class="border-b-2 border-zinc-950 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">Security without the friction</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-zinc-600">
                        Every layer is designed so your messages stay yours — from first handshake to auto-delete.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['title' => 'Pairwise E2E messaging', 'desc' => 'Per-conversation keys derived via ECDH (P-256) + HKDF. Chat messages are encrypted with AES-256-GCM before they leave your browser.'],
                        ['title' => 'Client-side encryption', 'desc' => 'Sends and chat messages are encrypted in your browser before submission. The server stores only ciphertext.'],
                        ['title' => 'Identity verification', 'desc' => 'Public key fingerprints let you confirm you are messaging the right person — not an impostor or MITM.'],
                        ['title' => 'Automatic expiry', 'desc' => 'Set message retention from 1 hour to 30 days. Expired messages and Sends are permanently deleted every 30 minutes.'],
                        ['title' => 'Hardened accounts', 'desc' => 'Passkeys, two-factor authentication, and email verification protect who can access your channels.'],
                        ['title' => 'Defense in depth', 'desc' => 'Strict Content Security Policy, short-lived Valkey sessions, and Laravel encrypted casts at rest for Sends and chat payloads.'],
                    ] as $feature)
                        <div @class([
                            'p-6',
                            'border-b-2 border-zinc-950' => true,
                            'sm:border-r-2' => ! $loop->last && ($loop->index + 1) % 2 !== 0,
                            'lg:border-r-2' => ! $loop->last && ($loop->index + 1) % 3 !== 0,
                        ])>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-zinc-950">{{ $feature['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $feature['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- How Messages work --}}
            <section id="how-messages" class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                    Messenger protocol
                </div>

                <div class="border-b-2 border-zinc-950 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">Pairwise encrypted channels</h2>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                        Private messaging between registered users. The server relays ciphertext — it cannot decrypt content.
                    </p>
                </div>

                <ol class="divide-y-2 divide-zinc-950">
                    @foreach ([
                        ['step' => '01', 'title' => 'Start a conversation', 'desc' => 'Open Messages, enter a registered user name, and open a pairwise encrypted channel.'],
                        ['step' => '02', 'title' => 'Identity keys', 'desc' => 'Each user gets an ECDH (P-256) key pair in the browser. The public key is registered with the server; the private key stays local.'],
                        ['step' => '03', 'title' => 'Derived conversation keys', 'desc' => 'Messages are encrypted with AES-256-GCM using a per-conversation key derived via ECDH + HKDF.'],
                        ['step' => '04', 'title' => 'Server relay only', 'desc' => 'The server stores and relays opaque ciphertext. It cannot decrypt message content.'],
                        ['step' => '05', 'title' => 'Verify your partner', 'desc' => 'A fingerprint of the recipient\'s public key is shown so you can confirm you are talking to the right person.'],
                        ['step' => '06', 'title' => 'Auto-delete', 'desc' => 'Each conversation can be configured to delete messages after 1 hour to 30 days.'],
                    ] as $item)
                        <li class="flex gap-6 p-6 @if($loop->even) bg-zinc-50 @else bg-white @endif">
                            <span class="shrink-0 text-sm font-bold tabular-nums text-emerald-700">#{{ $item['step'] }}</span>
                            <div>
                                <h3 class="text-sm font-bold uppercase tracking-tight text-zinc-950">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $item['desc'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- How Sends work --}}
            <section id="how-sends" class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-zinc-600">
                    Send protocol
                </div>

                <div class="border-b-2 border-zinc-950 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">Five steps. Zero exposure.</h2>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                        From creation to decryption, your secret never touches our servers in plaintext.
                    </p>
                </div>

                <ol class="divide-y-2 divide-zinc-950">
                    @foreach ([
                        ['step' => '01', 'title' => 'Create a Send', 'desc' => 'Name it, add registered viewer names, write your secret, and pick an expiry between 1 hour and 30 days.'],
                        ['step' => '02', 'title' => 'Encrypt locally', 'desc' => 'Optional password protection encrypts the message with AES-256-GCM. The key is derived via Argon2id in a Web Worker.'],
                        ['step' => '03', 'title' => 'Share securely', 'desc' => 'Only registered users whose names you listed can open the Send. You can always view your own Sends too.'],
                        ['step' => '04', 'title' => 'Decrypt in browser', 'desc' => 'Authorized viewers enter the shared password locally. Decryption runs off the main thread — we never see it.'],
                        ['step' => '05', 'title' => 'Auto-delete', 'desc' => 'When the timer runs out, the Send is permanently removed. No recovery, no residue.'],
                    ] as $item)
                        <li class="flex gap-6 p-6 @if($loop->even) bg-zinc-50 @else bg-white @endif">
                            <span class="shrink-0 text-sm font-bold tabular-nums text-emerald-700">#{{ $item['step'] }}</span>
                            <div>
                                <h3 class="text-sm font-bold uppercase tracking-tight text-zinc-950">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $item['desc'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Security model --}}
            <section id="security" class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">Security model</p>
                </div>

                <div class="grid lg:grid-cols-2">
                    <div class="border-b-2 border-zinc-950 p-6 lg:border-b-0 lg:border-r-2">
                        <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">We cannot read your messages</h2>
                        <p class="mt-4 text-sm leading-relaxed text-zinc-600">
                            {{ config('app.name') }} is built on a zero-knowledge model. Chat messages and password-protected Sends are encrypted before they leave your browser.
                            If you lose your local identity key or a Send password, the content cannot be recovered — by design.
                        </p>
                        <ul class="mt-6 space-y-3">
                            @foreach ([
                                'ECDH + HKDF conversation keys for every chat',
                                'Client-side E2E encryption protects content from operators',
                                'Identity key fingerprints prevent wrong-recipient attacks',
                                'Strict CSP blocks XSS and injection attacks',
                            ] as $point)
                                <li class="flex items-start gap-3 text-sm text-zinc-700">
                                    <span class="mt-0.5 shrink-0 font-bold text-emerald-700">[+]</span>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse">
                            <thead>
                                <tr class="border-b-2 border-zinc-950 bg-zinc-200 text-left">
                                    <th scope="col" class="border-r-2 border-zinc-950 px-4 py-3 text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">
                                        Layer
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">
                                        Protection
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ([
                                    ['Client-side E2E', 'Send and chat content from server operator'],
                                    ['Laravel encrypted cast', 'Send & chat payloads at rest'],
                                    ['ECDH + HKDF', 'Per-conversation chat encryption'],
                                    ['Identity key fingerprints', 'Wrong recipient or key substitution'],
                                    ['Opaque chat relay', 'Chat ciphertext stored as-is'],
                                    ['Passkeys + 2FA', 'Account access'],
                                    ['Per-Send viewer ACL', 'Who can open a Send'],
                                    ['Valkey sessions', 'Session hijacking surface'],
                                    ['Content Security Policy', 'XSS and injection'],
                                ] as [$layer, $protection])
                                    <tr @class([
                                        'border-b-2 border-zinc-950',
                                        'bg-zinc-50' => $loop->even,
                                        'bg-white' => $loop->odd,
                                    ])>
                                        <td class="border-r-2 border-zinc-950 px-4 py-3.5 text-sm font-bold text-zinc-950">{{ $layer }}</td>
                                        <td class="px-4 py-3.5 text-sm text-zinc-600">{{ $protection }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- FAQ --}}
            <section id="faq" class="mt-8 border-2 border-zinc-950">
                <div class="border-b-2 border-zinc-950 bg-zinc-200 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-600">Support</p>
                </div>

                <div class="border-b-2 border-zinc-950 p-6">
                    <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950">Frequently asked questions</h2>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                        Everything you need to know before opening your first encrypted channel.
                    </p>
                </div>

                <div class="divide-y-2 divide-zinc-950">
                    @foreach ([
                        ['q' => 'Can '.config('app.name').' staff read my messages?', 'a' => 'No. Chat messages and password-protected Sends are encrypted in your browser before upload. We store only ciphertext and never receive your decryption keys. We cannot recover lost identity keys or Send passwords.'],
                        ['q' => 'How does chat encryption work?', 'a' => 'Each user has an ECDH (P-256) identity key pair. Per-conversation keys are derived via ECDH + HKDF, then messages are encrypted with AES-256-GCM. Both parties derive the same key independently.'],
                        ['q' => 'Who can view a Send?', 'a' => 'Only registered users whose names you listed as viewers, plus you as the owner. There are no public links — access is strictly invite-based.'],
                        ['q' => 'What encryption is used?', 'a' => 'Sends use AES-256-GCM with Argon2id key derivation in a Web Worker. Chat uses ECDH (P-256) + HKDF + AES-256-GCM. At rest, Laravel\'s encrypted cast adds another layer for Send and chat payloads.'],
                        ['q' => 'How long do Sends last?', 'a' => 'You choose an expiry between 1 hour and 30 days when creating a Send. Expired Sends are permanently deleted by a scheduled task that runs every 30 minutes.'],
                        ['q' => 'How many Sends can I have?', 'a' => 'Each user can have up to '.config('send.max_per_user', 15).' active Sends at a time, with up to 100 viewer names per Send and a '.number_format(config('send.message.max_length', 1000)).'-character plaintext message limit before encryption.'],
                        ['q' => 'What protects my account?', 'a' => 'Accounts support passkeys, two-factor authentication (TOTP), and email verification. Sessions are short-lived and stored in Valkey to minimize hijacking risk.'],
                        ['q' => 'Is this better than sharing via chat or email?', 'a' => 'Yes. Chat and email leave plaintext copies in message history, logs, and backups. '.config('app.name').' encrypts before transmission, limits access to named viewers, and auto-deletes on expiry.'],
                        ['q' => 'What happens if I forget the Send password?', 'a' => 'The secret cannot be recovered. This is intentional — it proves we never had access to the decryption key. Only share passwords through a separate secure channel.'],
                    ] as $faq)
                        <details class="group bg-white open:bg-zinc-50">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 text-sm font-bold uppercase tracking-tight text-zinc-950 [&::-webkit-details-marker]:hidden">
                                {{ $faq['q'] }}
                                <span class="shrink-0 text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500 group-open:text-emerald-700">[+]</span>
                            </summary>
                            <p class="border-t-2 border-zinc-950 px-6 py-4 text-sm leading-relaxed text-zinc-600">{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- Final CTA --}}
            <section class="my-8 border-2 border-zinc-950">
                <div class="border-b-2 border-emerald-500 bg-emerald-500 px-4 py-1 text-[10px] font-bold uppercase tracking-[0.24em] text-emerald-950">
                    Ready to deploy
                </div>

                <div class="p-6 text-center sm:p-8">
                    <h2 class="text-2xl font-black uppercase tracking-tight text-zinc-950 sm:text-3xl">
                        Stop chatting in plain text
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-zinc-600">
                        Open your first encrypted channel in minutes. Verify your partner, message with confidence, and let auto-delete handle retention.
                    </p>
                    <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex w-full items-center justify-center border-2 border-zinc-950 bg-emerald-500 px-5 py-3 text-xs font-bold uppercase tracking-[0.18em] text-emerald-950 transition-colors hover:bg-emerald-400 sm:w-auto"
                            >
                                Get started free
                            </a>
                        @endif
                        @if (Route::has('login'))
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex w-full items-center justify-center border-2 border-zinc-950 bg-zinc-50 px-5 py-3 text-xs font-bold uppercase tracking-[0.18em] text-zinc-950 transition-colors hover:bg-zinc-200 sm:w-auto"
                            >
                                Log in to your account
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        </main>

        {{-- Footer --}}
        <footer class="border-t-2 border-zinc-950 bg-zinc-100">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-8 border-b-2 border-zinc-950 pb-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <a href="{{ route('home') }}" class="flex items-center gap-3">
                            <span class="flex size-8 items-center justify-center border-2 border-zinc-950 bg-emerald-500/10">
                                <x-app-logo-icon class="size-4 fill-emerald-700" />
                            </span>
                            <span class="text-sm font-bold uppercase tracking-tight text-zinc-950">{{ config('app.name') }}</span>
                        </a>
                        <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-600">
                            Free and open-source end-to-end encrypted messaging and one-time secret sharing for teams who refuse to compromise on security.
                        </p>
                    </div>
                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Product</h3>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-600">
                            <li><a href="#messages" class="transition-colors hover:text-emerald-700">Messages</a></li>
                            <li><a href="#sends" class="transition-colors hover:text-emerald-700">Sends</a></li>
                            <li><a href="#features" class="transition-colors hover:text-emerald-700">Features</a></li>
                            <li><a href="#security" class="transition-colors hover:text-emerald-700">Security</a></li>
                            <li><a href="#faq" class="transition-colors hover:text-emerald-700">FAQ</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Account</h3>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-600">
                            @if (Route::has('login'))
                                <li><a href="{{ route('login') }}" class="transition-colors hover:text-emerald-700">Log in</a></li>
                            @endif
                            @if (Route::has('register'))
                                <li><a href="{{ route('register') }}" class="transition-colors hover:text-emerald-700">Register</a></li>
                            @endif
                            @auth
                                <li><a href="{{ route('dashboard') }}" class="transition-colors hover:text-emerald-700">Dashboard</a></li>
                            @endauth
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500">Security</h3>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-600">
                            <li>AES-256-GCM encryption</li>
                            <li>Argon2id key derivation</li>
                            <li>ECDH + HKDF messaging</li>
                            <li>Zero-knowledge architecture</li>
                        </ul>
                    </div>
                </div>
                @include('partials.global-footer', ['nested' => true])

                <div class="mt-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <p class="text-sm text-zinc-600">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">Messages encrypted client-side. Server stores ciphertext only.</p>
                </div>
            </div>
        </footer>
    </body>
</html>
