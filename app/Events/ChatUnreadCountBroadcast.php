<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatUnreadCountBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Dedicated queue so chat realtime is not blocked by slow jobs.
     */
    public string $queue = 'broadcasts';

    public function __construct(
        public User $recipient,
        public Conversation $conversation,
        public int $unreadMessagesCount,
        public int $totalUnreadMessagesCount,
    ) {}

    public function broadcastAs(): string
    {
        return 'ChatUnreadCount';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->recipient->public_key),
        ];
    }

    /**
     * @return array{
     *     conversation_public_key: string,
     *     unread_messages_count: int,
     *     total_unread_messages_count: int,
     *     refresh: true
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_public_key' => $this->conversation->public_key,
            'unread_messages_count' => $this->unreadMessagesCount,
            'total_unread_messages_count' => $this->totalUnreadMessagesCount,
            'refresh' => true,
        ];
    }
}
