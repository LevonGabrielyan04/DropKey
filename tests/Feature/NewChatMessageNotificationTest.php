<?php

use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Support\Facades\Notification;

it('queues a web push notification when the recipient has a subscription', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $recipient->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/test-endpoint',
        'p256dh-test-key',
        'auth-test-token',
        'aes128gcm',
    );

    $this->actingAs($sender)
        ->postJson(route('messages.store'), [
            'recipient_id' => $recipient->id,
            'payload' => fakeChatPayload(),
        ])
        ->assertCreated();

    Notification::assertSentTo($recipient, NewChatMessageNotification::class, function (NewChatMessageNotification $notification) use ($sender, $recipient) {
        expect($notification->sender->is($sender))->toBeTrue();

        $message = $notification->toWebPush($recipient, $notification)->toArray();

        expect($message['title'])->toBe(config('app.name'))
            ->and($message['body'])->toBe('You have a new message')
            ->and($message['tag'])->toBe('chat-'.$sender->public_key)
            ->and($message['data']['url'])->toBe(route('chat.show', $sender))
            ->and($message['data']['unread_count'])->toBe(1)
            ->and(json_encode($message))->not->toContain('ciphertext')
            ->and(json_encode($message))->not->toContain('payload');

        return true;
    });
});

it('does not notify recipients without a push subscription', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('messages.store'), [
            'recipient_id' => $recipient->id,
            'payload' => fakeChatPayload(),
        ])
        ->assertCreated();

    Notification::assertNothingSent();
});

it('does not notify the sender', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $sender->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/sender-endpoint',
        'p256dh-sender-key',
        'auth-sender-token',
        'aes128gcm',
    );

    $recipient->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/recipient-endpoint',
        'p256dh-recipient-key',
        'auth-recipient-token',
        'aes128gcm',
    );

    $this->actingAs($sender)
        ->postJson(route('messages.store'), [
            'recipient_id' => $recipient->id,
            'payload' => fakeChatPayload(),
        ])
        ->assertCreated();

    Notification::assertSentTo($recipient, NewChatMessageNotification::class);
    Notification::assertNotSentTo($sender, NewChatMessageNotification::class);
});
