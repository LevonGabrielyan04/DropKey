<?php

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserIdentityKey;
use Illuminate\Support\Facades\DB;

it('relays bidirectional encrypted payloads without modifying ciphertext', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    UserIdentityKey::query()->create([
        'user_id' => $alice->id,
        ...validPublicKeyPayload(),
    ]);

    $bobJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'bob-public-x',
        'y' => 'bob-public-y',
    ];

    UserIdentityKey::query()->create([
        'user_id' => $bob->id,
        'public_key_jwk' => $bobJwk,
        'fingerprint' => publicKeyJwkFingerprint($bobJwk),
    ]);

    $alicePayload = fakeChatPayload();
    $bobPayload = fakeChatPayload(48);

    $this->actingAs($alice)
        ->postJson(route('messages.store'), [
            'recipient_id' => $bob->id,
            'payload' => $alicePayload,
        ])
        ->assertCreated();

    $this->actingAs($bob)
        ->postJson(route('messages.store'), [
            'recipient_id' => $alice->id,
            'payload' => $bobPayload,
        ])
        ->assertCreated();

    $conversation = Conversation::query()->first();

    expect($conversation)->not->toBeNull()
        ->and(ChatMessage::query()->count())->toBe(2);

    $storedAlicePayload = ChatMessage::query()
        ->where('sender_id', $alice->id)
        ->value('payload');

    $storedBobPayload = ChatMessage::query()
        ->where('sender_id', $bob->id)
        ->value('payload');

    expect($storedAlicePayload)->toBe($alicePayload)
        ->and($storedBobPayload)->toBe($bobPayload);

    $this->actingAs($bob)
        ->getJson(route('messages.index', $alice))
        ->assertSuccessful()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.payload', $alicePayload)
        ->assertJsonPath('messages.1.payload', $bobPayload);

    $this->actingAs($alice)
        ->getJson(route('messages.index', $bob))
        ->assertSuccessful()
        ->assertJsonCount(2, 'messages');
});

it('encrypts relay payloads at rest while preserving client ciphertext through eloquent', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $payload = fakeChatPayload();

    $this->actingAs($sender)
        ->postJson(route('messages.store'), [
            'recipient_id' => $recipient->id,
            'payload' => $payload,
        ])
        ->assertCreated();

    $rawPayload = DB::table('chat_messages')->value('payload');
    $message = ChatMessage::query()->first();

    expect($rawPayload)->not->toBe($payload)
        ->and($rawPayload)->toStartWith('eyJpdiI6')
        ->and($message)->not->toBeNull()
        ->and($message->payload)->toBe($payload);
});

it('supports the full register-key then relay workflow over HTTP', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertSuccessful();

    $bobJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'bob-public-x',
        'y' => 'bob-public-y',
    ];

    $this->actingAs($bob)
        ->postJson(route('api.identity.public-key.store'), [
            'public_key_jwk' => $bobJwk,
            'fingerprint' => publicKeyJwkFingerprint($bobJwk),
        ])
        ->assertSuccessful();

    $this->actingAs($alice)
        ->getJson(route('api.users.public-key.show', $bob))
        ->assertSuccessful()
        ->assertJsonPath('public_key_jwk.x', 'bob-public-x');

    $payload = fakeChatPayload();

    $this->actingAs($alice)
        ->postJson(route('messages.store'), [
            'recipient_id' => $bob->id,
            'payload' => $payload,
        ])
        ->assertCreated();

    $this->actingAs($bob)
        ->getJson(route('messages.index', $alice))
        ->assertSuccessful()
        ->assertJsonPath('messages.0.payload', $payload);
});
