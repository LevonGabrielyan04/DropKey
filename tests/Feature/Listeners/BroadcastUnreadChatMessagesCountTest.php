<?php

use App\Events\ChatMessageSent;
use App\Events\ChatUnreadCountBroadcast;
use App\Listeners\BroadcastUnreadChatMessagesCount;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('is registered to listen for ChatMessageSent', function () {
    Event::fake();

    Event::assertListening(
        ChatMessageSent::class,
        BroadcastUnreadChatMessagesCount::class,
    );
});

it('implements ShouldQueue', function () {
    expect(app(BroadcastUnreadChatMessagesCount::class))->toBeInstanceOf(ShouldQueue::class);
});

it('is queued on the broadcasts queue when ChatMessageSent is dispatched', function () {
    Queue::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ])->load(['conversation', 'sender:id,public_key']);

    ChatMessageSent::dispatch($message, $sender, $recipient);

    Queue::assertPushedOn('broadcasts', CallQueuedListener::class, function (CallQueuedListener $job) {
        return $job->class === BroadcastUnreadChatMessagesCount::class;
    });
});

it('broadcasts the unread messages count on the recipient chat channel', function () {
    Event::fake([ChatUnreadCountBroadcast::class]);

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ]);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ])->load(['conversation', 'sender:id,public_key']);

    app(BroadcastUnreadChatMessagesCount::class)->handle(new ChatMessageSent($message, $sender, $recipient));

    Event::assertDispatched(ChatUnreadCountBroadcast::class, function (ChatUnreadCountBroadcast $event) use ($recipient, $conversation) {
        return $event->recipient->is($recipient)
            && $event->conversation->is($conversation)
            && $event->unreadMessagesCount === 2
            && $event->totalUnreadMessagesCount === 2
            && $event->queue === 'broadcasts'
            && $event->broadcastAs() === 'ChatUnreadCount'
            && $event->broadcastOn()[0]->name === 'private-chat.'.$recipient->public_key
            && $event->broadcastWith() === [
                'conversation_public_key' => $conversation->public_key,
                'unread_messages_count' => 2,
                'total_unread_messages_count' => 2,
                'refresh' => true,
            ];
    });
});

it('excludes viewed messages and messages sent by the recipient from the unread count', function () {
    Event::fake([ChatUnreadCountBroadcast::class]);

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    $viewedMessage = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ]);
    $viewedMessage->forceFill(['is_viewed' => true])->save();

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $recipient->id,
        'payload' => fakeChatPayload(),
    ]);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ])->load(['conversation', 'sender:id,public_key']);

    app(BroadcastUnreadChatMessagesCount::class)->handle(new ChatMessageSent($message, $sender, $recipient));

    Event::assertDispatched(ChatUnreadCountBroadcast::class, function (ChatUnreadCountBroadcast $event) {
        return $event->unreadMessagesCount === 1
            && $event->totalUnreadMessagesCount === 1
            && $event->broadcastWith()['unread_messages_count'] === 1
            && $event->broadcastWith()['total_unread_messages_count'] === 1;
    });
});

it('includes unread messages from other conversations in the total unread count', function () {
    Event::fake([ChatUnreadCountBroadcast::class]);

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $otherSender = User::factory()->create();
    $conversation = createConversation($sender, $recipient);
    $otherConversation = createConversation($otherSender, $recipient);

    ChatMessage::query()->create([
        'conversation_id' => $otherConversation->id,
        'sender_id' => $otherSender->id,
        'payload' => fakeChatPayload(),
    ]);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ])->load(['conversation', 'sender:id,public_key']);

    app(BroadcastUnreadChatMessagesCount::class)->handle(new ChatMessageSent($message, $sender, $recipient));

    Event::assertDispatched(ChatUnreadCountBroadcast::class, function (ChatUnreadCountBroadcast $event) use ($conversation) {
        return $event->conversation->is($conversation)
            && $event->unreadMessagesCount === 1
            && $event->totalUnreadMessagesCount === 2
            && $event->broadcastWith()['total_unread_messages_count'] === 2;
    });
});

it('places the broadcast job on the dedicated broadcasts queue', function () {
    Queue::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    broadcast(new ChatUnreadCountBroadcast($recipient, $conversation, 1, 1));

    Queue::assertPushedOn('broadcasts', BroadcastEvent::class);
});
