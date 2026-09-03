<?php

use App\Models\User;

it('serves an installable web app manifest', function () {
    config(['app.name' => 'DropKey']);

    $response = $this->get(route('manifest'));

    $response
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'DropKey')
        ->assertJsonPath('short_name', 'DropKey')
        ->assertJsonPath('start_url', '/chat')
        ->assertJsonPath('scope', '/')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('theme_color', '#09090b')
        ->assertJsonPath('background_color', '#fafafa')
        ->assertJsonPath('icons.0.src', '/icons/icon-192.png')
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.1.src', '/icons/icon-512.png')
        ->assertJsonPath('icons.2.src', '/icons/icon-512-maskable.png')
        ->assertJsonPath('icons.2.purpose', 'maskable');
});

it('links the web app manifest from the landing page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('rel="manifest"', false)
        ->assertSee(route('manifest'), false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('content="#09090b"', false)
        ->assertSee('id="pwa-install-prompt"', false)
        ->assertSee('data-pwa-install', false)
        ->assertSee('data-pwa-dismiss', false);
});

it('links the web app manifest from authenticated app pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('rel="manifest"', false)
        ->assertSee(route('manifest'), false)
        ->assertSee('id="pwa-install-prompt"', false);
});

it('includes the install offer on auth pages', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('id="pwa-install-prompt"', false)
        ->assertSee(__('Install :app', ['app' => config('app.name')]), false);
});
