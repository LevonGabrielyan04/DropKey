<?php

use App\Models\User;
use App\Models\UserIdentityKey;

/**
 * Server-side contract for the live-chat key-rotation fix:
 * after a partner refreshes their identity key and sends a new message,
 * the other participant must be able to (1) observe the new fingerprint
 * and (2) poll the new ciphertext without refreshing the page.
 */
it('exposes a rotated partner key and relays post-rotation messages for live polling', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertSuccessful();

    $bobOriginalJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'bob-original-x',
        'y' => 'bob-original-y',
    ];

    $bobOriginalPayload = [
        'public_key_jwk' => $bobOriginalJwk,
        'fingerprint' => publicKeyJwkFingerprint($bobOriginalJwk),
    ];

    $this->actingAs($bob)
        ->postJson(route('api.identity.public-key.store'), $bobOriginalPayload)
        ->assertSuccessful();

    $this->actingAs($alice)
        ->getJson(route('api.users.public-key.show', $bob))
        ->assertSuccessful()
        ->assertJsonPath('fingerprint', $bobOriginalPayload['fingerprint'])
        ->assertJsonPath('public_key_jwk.x', 'bob-original-x');

    $this->actingAs($alice)
        ->postJson(route('messages.store'), [
            'recipient_id' => $bob->id,
            'payload' => fakeChatPayload(),
        ])
        ->assertCreated();

    $this->actingAs($bob)
        ->postJson(route('messages.store'), [
            'recipient_id' => $alice->id,
            'payload' => fakeChatPayload(40),
        ])
        ->assertCreated();

    $aliceHistory = $this->actingAs($alice)
        ->getJson(route('messages.index', $bob))
        ->assertSuccessful()
        ->assertJsonCount(2, 'messages')
        ->json('messages');

    $lastSeenPublicId = $aliceHistory[array_key_last($aliceHistory)]['public_id'];

    $bobRotatedJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'bob-rotated-x',
        'y' => 'bob-rotated-y',
    ];

    $bobRotatedPayload = [
        'public_key_jwk' => $bobRotatedJwk,
        'fingerprint' => publicKeyJwkFingerprint($bobRotatedJwk),
    ];

    $this->actingAs($bob)
        ->postJson(route('api.identity.public-key.store'), $bobRotatedPayload)
        ->assertSuccessful();

    expect(UserIdentityKey::query()->where('user_id', $bob->id)->count())->toBe(1);

    $this->actingAs($alice)
        ->getJson(route('api.users.public-key.show', $bob))
        ->assertSuccessful()
        ->assertJsonPath('fingerprint', $bobRotatedPayload['fingerprint'])
        ->assertJsonPath('public_key_jwk.x', 'bob-rotated-x')
        ->assertJsonMissingPath('public_key_jwk.d');

    $postRotationPayload = fakeChatPayload(48);

    $this->actingAs($bob)
        ->postJson(route('messages.store'), [
            'recipient_id' => $alice->id,
            'payload' => $postRotationPayload,
        ])
        ->assertCreated();

    $this->actingAs($alice)
        ->getJson(route('messages.index', $bob).'?after_public_id='.$lastSeenPublicId)
        ->assertSuccessful()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.payload', $postRotationPayload)
        ->assertJsonPath('messages.0.sender.public_id', $bob->public_key);
});
