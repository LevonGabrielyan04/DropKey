<?php

use App\Events\ChatUnreadCountBroadcast;
use App\Models\User;

it('broadcasts unread count payload on the recipient chat channel', function () {
    $recipient = User::factory()->create();
    $sender = User::factory()->create();
    $conversation = createConversation($sender, $recipient);

    $event = new ChatUnreadCountBroadcast($recipient, $conversation, 3, 5);

    expect($event->queue)->toBe('broadcasts')
        ->and($event->broadcastAs())->toBe('ChatUnreadCount')
        ->and($event->broadcastOn()[0]->name)->toBe('private-chat.'.$recipient->public_key)
        ->and($event->broadcastWith())->toBe([
            'conversation_public_key' => $conversation->public_key,
            'unread_messages_count' => 3,
            'total_unread_messages_count' => 5,
            'refresh' => true,
        ]);
});
