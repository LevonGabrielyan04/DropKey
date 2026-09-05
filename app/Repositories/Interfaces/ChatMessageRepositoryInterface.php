<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ChatMessageRepositoryInterface
{
    public function findConversationBetweenUsers(User $first, User $second): ?Conversation;

    public function findOrCreateConversation(User $first, User $second): Conversation;

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getMessagesForConversation(Conversation $conversation, ?string $afterPublicId = null): Collection;

    /**
     * Mark unviewed messages from the given sender as viewed.
     *
     * @return list<string> Public IDs of messages newly marked as viewed
     */
    public function markMessagesAsViewed(Conversation $conversation, User $sender): array;

    /**
     * Mark a single unviewed message as viewed.
     *
     * @return string|null Public ID when newly marked, otherwise null
     */
    public function markMessageAsViewed(ChatMessage $message): ?string;

    public function createMessage(Conversation $conversation, User $sender, string $payload): ChatMessage;

    /**
     * Count unviewed messages sent to the user across all of their conversations.
     */
    public function countUnreadMessagesFor(User $user): int;

    public function deleteExpired(): int;
}
