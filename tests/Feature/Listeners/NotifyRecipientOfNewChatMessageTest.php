<?php

use App\Events\ChatMessageSent;
use App\Listeners\NotifyRecipientOfNewChatMessage;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('is registered to listen for ChatMessageSent', function () {
    Event::fake();

    Event::assertListening(
        ChatMessageSent::class,
        NotifyRecipientOfNewChatMessage::class,
    );
});

it('implements ShouldQueue', function () {
    expect(app(NotifyRecipientOfNewChatMessage::class))->toBeInstanceOf(ShouldQueue::class);
});

it('is queued when ChatMessageSent is dispatched', function () {
    Queue::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ]);

    ChatMessageSent::dispatch($message, $sender, $recipient);

    Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job) {
        return $job->class === NotifyRecipientOfNewChatMessage::class;
    });
});

it('notifies the recipient when they have a push subscription', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    $recipient->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/test-endpoint',
        'p256dh-test-key',
        'auth-test-token',
        'aes128gcm',
    );

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ]);

    app(NotifyRecipientOfNewChatMessage::class)->handle(new ChatMessageSent($message, $sender, $recipient));

    Notification::assertSentTo($recipient, NewChatMessageNotification::class, function (NewChatMessageNotification $notification) use ($sender) {
        return $notification->sender->is($sender)
            && $notification->unreadCount === 1;
    });
    Notification::assertNotSentTo($sender, NewChatMessageNotification::class);
});

it('does not notify recipients without a push subscription', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'payload' => fakeChatPayload(),
    ]);

    app(NotifyRecipientOfNewChatMessage::class)->handle(new ChatMessageSent($message, $sender, $recipient));

    Notification::assertNothingSent();
});
