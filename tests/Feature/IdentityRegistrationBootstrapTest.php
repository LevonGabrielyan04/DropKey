<?php

use App\Models\User;
use App\Models\UserIdentityKey;

it('includes identity registration bootstrap data on authenticated app pages', function () {
    $user = User::factory()->create();

    UserIdentityKey::query()->create([
        'user_id' => $user->id,
        ...validPublicKeyPayload(),
    ]);

    $identityKey = $user->identityKey()->firstOrFail();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-browser-db-id="'.e($identityKey->browser_db_id).'"', false)
        ->assertSee('data-identity-register-url="'.route('api.identity.public-key.store').'"', false)
        ->assertSee('data-identity-mine-url="'.route('api.identity.public-key.mine').'"', false)
        ->assertSee('data-password-confirmation-status-url="'.route('password.confirmation').'"', false)
        ->assertSee('data-password-confirm-url="'.route('password.confirm.store').'"', false)
        ->assertSee('data-csrf-token="', false)
        ->assertSee('data-test="identity-key-overwrite-modal"', false)
        ->assertSee('data-test="identity-key-overwrite-password-section"', false)
        ->assertSee(__('Confirm your account password before replacing your encryption key.'), false)
        ->assertSee(__('Replacing your encryption key will permanently remove access to your old messages. This cannot be undone.'), false)
        ->assertSee(__('Your previous decryption key was not found on this device.'), false)
        ->assertSee(__('Browsers may automatically delete stored encryption keys under certain circumstances, such as after a period of inactivity or when using Incognito / private browsing.'), false)
        ->assertSee(__('If the key still exists elsewhere, sign in on the same device and browser where you originally encrypted your messages.'), false);
});

it('does not include identity registration bootstrap data on guest pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('data-identity-register-url', false)
        ->assertDontSee('data-identity-mine-url', false);
});
