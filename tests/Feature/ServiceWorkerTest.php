<?php

it('provides a shared service worker with a network-only fetch handler', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)->toBeString()
        ->toContain("addEventListener('fetch'")
        ->toContain('event.respondWith(fetch(event.request))')
        ->toContain("addEventListener('push'")
        ->toContain("addEventListener('notificationclick'")
        ->toContain('setAppBadge')
        ->toContain('clearAppBadge')
        ->toContain('unread_count')
        ->not->toContain('caches.open')
        ->not->toContain('cache.put')
        ->not->toContain('cache.add');
});

it('provides the required pwa icon assets', function (string $path) {
    expect(is_file(public_path($path)))->toBeTrue();
})->with([
    'icons/icon-192.png',
    'icons/icon-512.png',
    'icons/icon-512-maskable.png',
    'apple-touch-icon.png',
]);
