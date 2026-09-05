<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChatMessageSent;
use App\Events\ChatUnreadCountBroadcast;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastUnreadChatMessagesCount implements ShouldQueue
{
    /**
     * Dedicated queue so chat realtime is not blocked by slow jobs.
     */
    public string $queue = 'broadcasts';

    public function __construct(protected ChatMessageRepositoryInterface $chatMessages) {}

    public function handle(ChatMessageSent $event): void
    {
        $event->message->loadMissing('conversation');

        $unreadMessagesCount = $event->message->conversation
            ->messages()
            ->where('sender_id', '!=', $event->recipient->id)
            ->where('is_viewed', false)
            ->count();

        broadcast(new ChatUnreadCountBroadcast(
            $event->recipient,
            $event->message->conversation,
            $unreadMessagesCount,
            $this->chatMessages->countUnreadMessagesFor($event->recipient),
        ));
    }
}
