<?php

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserIdentityKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves the conversation partner for a participant', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $conversation = createConversation($alice, $bob);

    expect($conversation->partnerFor($alice)->is($bob))->toBeTrue()
        ->and($conversation->partnerFor($bob)->is($alice))->toBeTrue()
        ->and($conversation->includesUser($alice))->toBeTrue()
        ->and($conversation->includesUser($bob))->toBeTrue();
});

it('relates users to identity keys and chat messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    UserIdentityKey::query()->create([
        'user_id' => $alice->id,
        ...validPublicKeyPayload(),
    ]);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $alice->refresh();

    expect($alice->identityKey)->not->toBeNull()
        ->and($alice->sentChatMessages)->toHaveCount(1)
        ->and($conversation->messages)->toHaveCount(1)
        ->and($alice->conversationsAsUserOne()->count() + $alice->conversationsAsUserTwo()->count())->toBe(1);
});
