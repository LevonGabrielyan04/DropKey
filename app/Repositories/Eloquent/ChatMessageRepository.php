<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TimePeriod;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Support\ChatMessageColumns;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChatMessageRepository implements ChatMessageRepositoryInterface
{
    public function __construct(protected ChatMessage $model) {}

    public function findConversationBetweenUsers(User $first, User $second): ?Conversation
    {
        if ($first->id === $second->id) {
            return null;
        }

        [$userOneId, $userTwoId] = $this->canonicalUserIds($first, $second);

        return Conversation::query()
            ->where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->first();
    }

    public function findOrCreateConversation(User $first, User $second): Conversation
    {
        if ($first->id === $second->id) {
            throw new InvalidArgumentException('Cannot create a conversation with yourself.');
        }

        [$userOneId, $userTwoId] = $this->canonicalUserIds($first, $second);

        return Conversation::query()->firstOrCreate([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getMessagesForConversation(Conversation $conversation, ?string $afterPublicId = null): Collection
    {
        $afterId = null;

        if ($afterPublicId !== null && $afterPublicId !== '' && Str::isUuid($afterPublicId)) {
            $cursorId = $conversation->messages()
                ->where('public_id', BinaryCodec::encode($afterPublicId, 'uuid'))
                ->value('id');

            if ($cursorId !== null) {
                $afterId = (string) $cursorId;
            }
        }

        return $conversation->messages()
            ->with(['sender:id,public_key'])
            ->when($afterId !== null, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit((int) config('chat.poll.batch_size'))
            ->get(ChatMessageColumns::COLUMNS);
    }

    /**
     * {@inheritDoc}
     */
    public function markMessagesAsViewed(Conversation $conversation, User $sender): array
    {
        $messages = $conversation->messages()
            ->where('sender_id', $sender->id)
            ->where('is_viewed', false)
            ->get(['id', 'public_id']);

        if ($messages->isEmpty()) {
            return [];
        }

        $conversation->messages()
            ->whereIn('id', $messages->modelKeys())
            ->update(['is_viewed' => true]);

        return array_values($messages
            ->map(static fn (ChatMessage $message): string => $message->public_id)
            ->all());
    }

    /**
     * {@inheritDoc}
     */
    public function markMessageAsViewed(ChatMessage $message): ?string
    {
        if ($message->is_viewed) {
            return null;
        }

        $updated = $this->model->query()
            ->whereKey($message->getKey())
            ->where('is_viewed', false)
            ->update(['is_viewed' => true]);

        if ($updated === 0) {
            return null;
        }

        $message->forceFill(['is_viewed' => true]);

        return $message->public_id;
    }

    public function createMessage(Conversation $conversation, User $sender, string $payload): ChatMessage
    {
        return $conversation->messages()->create([
            'sender_id' => $sender->id,
            'payload' => $payload,
        ]);
    }

    public function deleteExpired(): int
    {
        $deleted = 0;

        foreach (TimePeriod::cases() as $period) {
            $deleted += $this->model->query()
                ->join('conversations', 'chat_messages.conversation_id', '=', 'conversations.id')
                ->where('conversations.auto_delete', $period->value)
                ->where('chat_messages.created_at', '<', $period->retentionCutoff())
                ->delete();
        }

        return $deleted;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function canonicalUserIds(User $first, User $second): array
    {
        return $first->id < $second->id
            ? [$first->id, $second->id]
            : [$second->id, $first->id];
    }
}
