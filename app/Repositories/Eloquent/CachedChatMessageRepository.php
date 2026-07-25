<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TimePeriod;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Support\ChatMessageColumns;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;

readonly class CachedChatMessageRepository implements ChatMessageRepositoryInterface
{
    public function __construct(
        private ChatMessageRepositoryInterface $repository,
        private CacheRepository $cache,
    ) {}

    public function findConversationBetweenUsers(User $first, User $second): ?Conversation
    {
        return $this->repository->findConversationBetweenUsers($first, $second);
    }

    public function findOrCreateConversation(User $first, User $second): Conversation
    {
        return $this->repository->findOrCreateConversation($first, $second);
    }

    /**
     * {@inheritDoc}
     */
    public function getMessagesForConversation(Conversation $conversation, ?string $afterPublicId = null): Collection
    {
        $conversationId = (string) $conversation->id;
        $taggedCache = $this->conversationMessagesCache($conversationId);
        $cacheKey = $this->messagesCacheKey($conversationId, $afterPublicId, ChatMessageColumns::COLUMNS);
        $ttl = $this->cacheExpiresAt();

        return $this->resolveCachedMessageCollection(
            $this->rememberLocked(
                $taggedCache,
                $cacheKey,
                fn (): Collection => $this->repository->getMessagesForConversation($conversation, $afterPublicId),
                fn (): CarbonInterface => $ttl,
            ),
            $taggedCache,
            $cacheKey,
            $conversation,
            $afterPublicId,
            $ttl,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function markMessagesAsViewed(Conversation $conversation, User $sender): array
    {
        $viewedPublicIds = $this->repository->markMessagesAsViewed($conversation, $sender);

        if ($viewedPublicIds !== []) {
            $this->forgetConversationMessages((string) $conversation->id);
        }

        return $viewedPublicIds;
    }

    /**
     * {@inheritDoc}
     */
    public function markMessageAsViewed(ChatMessage $message): ?string
    {
        $viewedPublicId = $this->repository->markMessageAsViewed($message);

        if ($viewedPublicId !== null) {
            $this->cache->forget($this->messageCacheKey($message->id));
            $this->forgetConversationMessages((string) $message->conversation_id);
        }

        return $viewedPublicId;
    }

    public function createMessage(Conversation $conversation, User $sender, string $payload): ChatMessage
    {
        $message = $this->repository->createMessage($conversation, $sender, $payload);

        $this->forgetConversationMessages((string) $conversation->id);

        $this->cache->put(
            $this->messageCacheKey($message->id),
            $message,
            $this->cacheExpiresAt(),
        );

        return $message;
    }

    public function deleteExpired(): int
    {
        $expiredMessages = $this->findExpiredMessages();

        if ($expiredMessages->isEmpty()) {
            return $this->repository->deleteExpired();
        }

        $conversationIds = [];

        foreach ($expiredMessages as $message) {
            $this->cache->forget($this->messageCacheKey($message->id));
            $conversationIds[(string) $message->conversation_id] = true;
        }

        foreach (array_keys($conversationIds) as $conversationId) {
            $this->forgetConversationMessages((string) $conversationId);
        }

        return $this->repository->deleteExpired();
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function findExpiredMessages(): Collection
    {
        $messages = new Collection;

        foreach (TimePeriod::cases() as $period) {
            $batch = ChatMessage::query()
                ->join('conversations', 'chat_messages.conversation_id', '=', 'conversations.id')
                ->where('conversations.auto_delete', $period->value)
                ->where('chat_messages.created_at', '<', $period->retentionCutoff())
                ->get(['chat_messages.id', 'chat_messages.conversation_id']);

            $messages = $messages->merge($batch);
        }

        return $messages;
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function resolveCachedMessageCollection(
        mixed $cached,
        CacheRepository $taggedCache,
        string $cacheKey,
        Conversation $conversation,
        ?string $afterPublicId,
        CarbonInterface $ttl,
    ): Collection {
        if (! $cached instanceof Collection) {
            return $this->refreshMessageCollectionCache($taggedCache, $cacheKey, $conversation, $afterPublicId, $ttl);
        }

        foreach ($cached as $message) {
            if (! $message instanceof ChatMessage) {
                return $this->refreshMessageCollectionCache($taggedCache, $cacheKey, $conversation, $afterPublicId, $ttl);
            }
        }

        return $cached;
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function refreshMessageCollectionCache(
        CacheRepository $taggedCache,
        string $cacheKey,
        Conversation $conversation,
        ?string $afterPublicId,
        CarbonInterface $ttl,
    ): Collection {
        $taggedCache->forget($cacheKey);
        $collection = $this->repository->getMessagesForConversation($conversation, $afterPublicId);
        $taggedCache->put($cacheKey, $collection, $ttl);

        return $collection;
    }

    /**
     * @param  Closure(): mixed  $callback
     * @param  Closure(mixed): CarbonInterface  $ttlResolver
     */
    /**
     * @phpstan-impure
     */
    private function cacheHas(CacheRepository $cache, string $key): bool
    {
        return $cache->has($key);
    }

    private function rememberLocked(
        CacheRepository $cache,
        string $key,
        Closure $callback,
        Closure $ttlResolver,
    ): mixed {
        if ($this->cacheHas($cache, $key)) {
            return $cache->get($key);
        }

        return $this->cache->withoutOverlapping(
            "lock:{$key}",
            function () use ($cache, $key, $callback, $ttlResolver): mixed {
                if ($this->cacheHas($cache, $key)) {
                    return $cache->get($key);
                }

                $value = $callback();
                $cache->put($key, $value, $ttlResolver($value));

                return $value;
            },
            lockFor: 10,
            waitFor: 5,
        );
    }

    private function cacheExpiresAt(): CarbonInterface
    {
        return now()->addMinutes((int) config('chat.cache_ttl'));
    }

    private function messageCacheKey(int|string $id): string
    {
        return "chat_message_{$id}";
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function messagesCacheKey(string $conversationId, ?string $afterPublicId, array $columns): string
    {
        $encodedColumns = json_encode(array_values($columns));

        if ($encodedColumns === false) {
            throw new \RuntimeException('Unable to encode columns for cache key.');
        }

        $cursor = $afterPublicId ?? 'start';

        return 'chat_messages_'.$conversationId.'_'.$cursor.'_'.hash('xxh128', $encodedColumns);
    }

    /**
     * @return list<string>
     */
    private function conversationMessagesTags(string $conversationId): array
    {
        return ["conversation:{$conversationId}:messages"];
    }

    private function conversationMessagesCache(string $conversationId): CacheRepository
    {
        return $this->cache->tags($this->conversationMessagesTags($conversationId));
    }

    private function forgetConversationMessages(string $conversationId): void
    {
        $this->conversationMessagesCache($conversationId)->flush();
    }
}
