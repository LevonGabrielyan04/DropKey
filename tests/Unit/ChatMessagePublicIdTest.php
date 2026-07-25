<?php

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('assigns a unique uuid public id when creating a chat message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    expect($message->id)->toBeString()
        ->and(Str::isUuid($message->id))->toBeTrue()
        ->and($message->public_id)->toBeString()
        ->and(Str::isUuid($message->public_id))->toBeTrue();
});

it('stores chat message public ids as binary uuids in the database', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $rawPublicId = DB::table('chat_messages')
        ->where('id', $message->id)
        ->value('public_id');

    expect($rawPublicId)
        ->toBeString()
        ->and(strlen($rawPublicId))->toBe(16)
        ->and(BinaryCodec::decode($rawPublicId, 'uuid'))->toBe($message->public_id);
});

it('resolves chat message route model bindings by public id', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    expect($message->getRouteKeyName())->toBe('public_id')
        ->and($message->getRouteKey())->toBe($message->public_id);

    $resolved = (new ChatMessage)->resolveRouteBinding($message->public_id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($message))->toBeTrue();
});

it('does not resolve chat message route model bindings by numeric id', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    expect((new ChatMessage)->resolveRouteBinding((string) $message->id))->toBeNull();
});

it('enforces unique public ids across chat messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $firstMessage = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $secondMessage = new ChatMessage([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'payload' => fakeChatPayload(),
    ]);
    $secondMessage->public_id = $firstMessage->public_id;

    expect(fn () => $secondMessage->save())->toThrow(UniqueConstraintViolationException::class);
});
