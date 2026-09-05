<?php

use App\Events\ChatMessagesViewedBroadcast;
use App\Events\ChatUnreadCountBroadcast;
use App\Models\ChatMessage;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Services\Interfaces\ChatMessageServiceInterface;
use Illuminate\Support\Facades\Event;

it('defaults new chat messages to not viewed', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    expect($message->fresh()->is_viewed)->toBeFalse();
});

it('exposes is_viewed on polled messages before and after the recipient reads them', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $this->actingAs($alice)
        ->getJson(route('messages.index', $bob))
        ->assertSuccessful()
        ->assertJsonPath('messages.0.is_viewed', false);

    $this->actingAs($bob)
        ->getJson(route('messages.index', $alice))
        ->assertSuccessful();

    $this->actingAs($alice)
        ->getJson(route('messages.index', $bob))
        ->assertSuccessful()
        ->assertJsonPath('messages.0.is_viewed', true);
});

it('marks the opposite users messages as viewed when fetching messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);
    $repository = app(ChatMessageRepositoryInterface::class);
    $service = app(ChatMessageServiceInterface::class);

    $fromAlice = $repository->createMessage($conversation, $alice, fakeChatPayload());
    $fromBob = $repository->createMessage($conversation, $bob, fakeChatPayload());

    $this->actingAs($bob);

    $messages = $service->getMessagesForUsers($bob, $alice);

    expect($messages)->toHaveCount(2)
        ->and($fromAlice->fresh()->is_viewed)->toBeTrue()
        ->and($fromBob->fresh()->is_viewed)->toBeFalse();
});

it('does not mark the viewers own messages as viewed', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);
    $repository = app(ChatMessageRepositoryInterface::class);
    $service = app(ChatMessageServiceInterface::class);

    $fromAlice = $repository->createMessage($conversation, $alice, fakeChatPayload());

    $this->actingAs($alice);

    $service->getMessagesForUsers($alice, $bob);

    expect($fromAlice->fresh()->is_viewed)->toBeFalse();
});

it('marks only unviewed messages from the opposite user via the repository', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);
    $repository = app(ChatMessageRepositoryInterface::class);

    $unviewed = $repository->createMessage($conversation, $alice, fakeChatPayload());
    $alreadyViewed = $repository->createMessage($conversation, $alice, fakeChatPayload());
    $alreadyViewed->forceFill(['is_viewed' => true])->save();
    $fromBob = $repository->createMessage($conversation, $bob, fakeChatPayload());

    $updated = $repository->markMessagesAsViewed($conversation, $alice);

    expect($updated)->toBe([$unviewed->public_id])
        ->and($unviewed->fresh()->is_viewed)->toBeTrue()
        ->and($alreadyViewed->fresh()->is_viewed)->toBeTrue()
        ->and($fromBob->fresh()->is_viewed)->toBeFalse();
});

it('marks a single unviewed message via the repository', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);
    $repository = app(ChatMessageRepositoryInterface::class);

    $message = $repository->createMessage($conversation, $alice, fakeChatPayload());

    expect($repository->markMessageAsViewed($message))->toBe($message->public_id)
        ->and($message->fresh()->is_viewed)->toBeTrue()
        ->and($repository->markMessageAsViewed($message->fresh()))->toBeNull();
});

it('broadcasts read receipts when the recipient fetches messages', function () {
    Event::fake([ChatMessagesViewedBroadcast::class, ChatUnreadCountBroadcast::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $this->actingAs($bob)
        ->getJson(route('messages.index', $alice))
        ->assertSuccessful();

    Event::assertDispatched(ChatMessagesViewedBroadcast::class, function (ChatMessagesViewedBroadcast $event) use ($conversation, $message) {
        return $event->conversation->is($conversation)
            && $event->broadcastWith() === ['public_ids' => [$message->public_id]];
    });

    Event::assertDispatched(ChatUnreadCountBroadcast::class, function (ChatUnreadCountBroadcast $event) use ($bob, $conversation) {
        return $event->recipient->is($bob)
            && $event->conversation->is($conversation)
            && $event->unreadMessagesCount === 0
            && $event->totalUnreadMessagesCount === 0
            && $event->broadcastWith() === [
                'conversation_public_key' => $conversation->public_key,
                'unread_messages_count' => 0,
                'total_unread_messages_count' => 0,
                'refresh' => true,
            ];
    });
});
