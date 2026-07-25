<?php

use App\Enums\TimePeriod;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Eloquent\CachedChatMessageRepository;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Support\ChatMessageColumns;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
    Cache::store('redis')->flush();
});

/**
 * @return array{0: CachedChatMessageRepository, 1: ChatMessageRepositoryInterface&MockInterface, 2: CacheRepository}
 */
function makeCachedChatMessageRepository(
    ?ChatMessageRepositoryInterface $innerRepository = null,
    ?CacheRepository $cache = null,
): array {
    $innerRepository ??= Mockery::mock(ChatMessageRepositoryInterface::class);
    $cache ??= Cache::store('redis');

    return [
        new CachedChatMessageRepository($innerRepository, $cache),
        $innerRepository,
        $cache,
    ];
}

function conversationMessagesTag(int|string $conversationId): string
{
    return "conversation:{$conversationId}:messages";
}

function chatMessagesCacheKey(int|string $conversationId, ?string $afterPublicId = null): string
{
    $cursor = $afterPublicId ?? 'start';
    $encodedColumns = json_encode(array_values(ChatMessageColumns::COLUMNS));

    return 'chat_messages_'.$conversationId.'_'.$cursor.'_'.hash('xxh128', $encodedColumns);
}

function makeChatMessage(int|string $id, int|string $conversationId, array $attributes = []): ChatMessage
{
    $message = new ChatMessage;
    $message->id = $id;
    $message->conversation_id = $conversationId;
    $message->public_id = (string) Str::uuid();
    $message->sender_id = $attributes['sender_id'] ?? 1;
    $message->payload = $attributes['payload'] ?? 'encrypted-payload';
    $message->is_viewed = $attributes['is_viewed'] ?? false;

    return $message;
}

function makeConversation(int|string $id): Conversation
{
    $conversation = new Conversation;
    $conversation->id = $id;

    return $conversation;
}

it('getMessagesForConversation returns a cached collection without querying the inner repository', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $message = makeChatMessage(42, $conversation->id);
    $collection = new Collection([$message]);
    $cacheKey = chatMessagesCacheKey($conversation->id);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, null)
        ->andReturn($collection);

    expect($repository->getMessagesForConversation($conversation))->toHaveCount(1)
        ->and($repository->getMessagesForConversation($conversation)->first())->toEqual($message)
        ->and($taggedCache->has($cacheKey))->toBeTrue();
});

it('getMessagesForConversation queries the inner repository on a cache miss', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $message = makeChatMessage(42, $conversation->id);
    $collection = new Collection([$message]);

    [$repository, $innerRepository] = makeCachedChatMessageRepository();

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, null)
        ->andReturn($collection);

    expect($repository->getMessagesForConversation($conversation))->toBe($collection);
});

it('getMessagesForConversation caches results separately per cursor', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $cursor = (string) Str::uuid();
    $firstBatch = new Collection([makeChatMessage(1, $conversation->id)]);
    $secondBatch = new Collection([makeChatMessage(2, $conversation->id)]);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, null)
        ->andReturn($firstBatch);

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, $cursor)
        ->andReturn($secondBatch);

    $repository->getMessagesForConversation($conversation);
    $repository->getMessagesForConversation($conversation, $cursor);

    expect($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeTrue()
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id, $cursor)))->toBeTrue();
});

it('getMessagesForConversation recovers when the cached value is not a collection', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $message = makeChatMessage(42, $conversation->id);
    $collection = new Collection([$message]);
    $cacheKey = chatMessagesCacheKey($conversation->id);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $taggedCache->put($cacheKey, 'not-a-collection', now()->addMinutes(60));

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, null)
        ->andReturn($collection);

    expect($repository->getMessagesForConversation($conversation))->toBe($collection);
});

it('getMessagesForConversation recovers when the cached collection contains non-message items', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $message = makeChatMessage(42, $conversation->id);
    $collection = new Collection([$message]);
    $cacheKey = chatMessagesCacheKey($conversation->id);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $taggedCache->put($cacheKey, new Collection(['oops']), now()->addMinutes(60));

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, null)
        ->andReturn($collection);

    $result = $repository->getMessagesForConversation($conversation);

    expect($result)->toBe($collection)
        ->and($result->first())->toEqual($message);
});

it('createMessage stores the message in cache and flushes tagged conversation message list cache', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $sender = new User;
    $sender->id = 1;
    $payload = 'encrypted-payload';
    $message = makeChatMessage(42, $conversation->id, ['sender_id' => $sender->id, 'payload' => $payload]);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $innerRepository->shouldReceive('createMessage')
        ->once()
        ->with($conversation, $sender, $payload)
        ->andReturn($message);

    $taggedCache->put(chatMessagesCacheKey($conversation->id), new Collection, now()->addMinutes(60));
    $taggedCache->put("chat_messages_{$conversation->id}", new Collection, now()->addMinutes(60));

    expect($repository->createMessage($conversation, $sender, $payload))->toBe($message)
        ->and($cache->get("chat_message_{$message->id}"))->toEqual($message)
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeFalse()
        ->and($taggedCache->has("chat_messages_{$conversation->id}"))->toBeFalse();
});

it('createMessage stores the message in cache when no tagged conversation cache exists', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(7);
    $sender = new User;
    $sender->id = 2;
    $message = makeChatMessage(99, $conversation->id);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();

    $innerRepository->shouldReceive('createMessage')
        ->once()
        ->andReturn($message);

    $repository->createMessage($conversation, $sender, 'payload');

    expect($cache->get("chat_message_{$message->id}"))->toEqual($message);
});

it('markMessagesAsViewed flushes tagged conversation message cache when messages were updated', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $sender = new User;
    $sender->id = 1;

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $taggedCache->put(chatMessagesCacheKey($conversation->id), new Collection, now()->addMinutes(60));

    $innerRepository->shouldReceive('markMessagesAsViewed')
        ->once()
        ->with($conversation, $sender)
        ->andReturn(['message-public-id']);

    expect($repository->markMessagesAsViewed($conversation, $sender))->toBe(['message-public-id'])
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeFalse();
});

it('markMessagesAsViewed does not flush cache when no messages were updated', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $sender = new User;
    $sender->id = 1;

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $taggedCache->put(chatMessagesCacheKey($conversation->id), new Collection, now()->addMinutes(60));

    $innerRepository->shouldReceive('markMessagesAsViewed')
        ->once()
        ->with($conversation, $sender)
        ->andReturn([]);

    expect($repository->markMessagesAsViewed($conversation, $sender))->toBe([])
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeTrue();
});

it('markMessageAsViewed flushes tagged conversation cache and forgets the cached message', function () {
    config(['chat.cache_ttl' => 60]);

    $message = makeChatMessage(55, 10);
    $conversation = makeConversation(10);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $cache->put("chat_message_{$message->id}", $message, now()->addMinutes(60));
    $taggedCache->put(chatMessagesCacheKey($conversation->id), new Collection, now()->addMinutes(60));

    $innerRepository->shouldReceive('markMessageAsViewed')
        ->once()
        ->with($message)
        ->andReturn($message->public_id);

    expect($repository->markMessageAsViewed($message))->toBe($message->public_id)
        ->and($cache->has("chat_message_{$message->id}"))->toBeFalse()
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeFalse();
});

it('markMessageAsViewed does not flush cache when the message was already viewed', function () {
    config(['chat.cache_ttl' => 60]);

    $message = makeChatMessage(55, 10);
    $conversation = makeConversation(10);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $cache->put("chat_message_{$message->id}", $message, now()->addMinutes(60));
    $taggedCache->put(chatMessagesCacheKey($conversation->id), new Collection, now()->addMinutes(60));

    $innerRepository->shouldReceive('markMessageAsViewed')
        ->once()
        ->with($message)
        ->andReturnNull();

    expect($repository->markMessageAsViewed($message))->toBeNull()
        ->and($cache->has("chat_message_{$message->id}"))->toBeTrue()
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeTrue();
});

it('deleteExpired flushes tagged conversation cache and forgets cached messages', function () {
    config(['chat.cache_ttl' => 60]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    Conversation::query()
        ->whereKey($conversation->id)
        ->update(['auto_delete' => TimePeriod::ONE_DAY->value]);

    $message = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    ChatMessage::query()
        ->whereKey($message->id)
        ->update(['created_at' => now()->subDays(8)]);

    [$repository, $innerRepository, $cache] = makeCachedChatMessageRepository();
    $taggedCache = $cache->tags(conversationMessagesTag($conversation->id));

    $cache->put("chat_message_{$message->id}", $message, now()->addMinutes(60));
    $taggedCache->put(chatMessagesCacheKey($conversation->id), new Collection([$message]), now()->addMinutes(60));

    $innerRepository->shouldReceive('deleteExpired')->once()->andReturn(1);

    expect($repository->deleteExpired())->toBe(1)
        ->and($cache->has("chat_message_{$message->id}"))->toBeFalse()
        ->and($taggedCache->has(chatMessagesCacheKey($conversation->id)))->toBeFalse();
});

it('deleteExpired delegates directly when no messages have expired', function () {
    [$repository, $innerRepository] = makeCachedChatMessageRepository();

    $innerRepository->shouldReceive('deleteExpired')->once()->andReturn(0);

    expect($repository->deleteExpired())->toBe(0);
});

it('rememberLocked prevents duplicate inner repository calls for concurrent misses', function () {
    config(['chat.cache_ttl' => 60]);

    $conversation = makeConversation(10);
    $collection = new Collection([makeChatMessage(1, $conversation->id)]);

    [$repository, $innerRepository] = makeCachedChatMessageRepository();

    $innerRepository->shouldReceive('getMessagesForConversation')
        ->once()
        ->with($conversation, null)
        ->andReturn($collection);

    collect(range(1, 5))->each(
        fn (): Collection => $repository->getMessagesForConversation($conversation),
    );
});

it('delegates findOrCreateConversation to the inner repository', function () {
    $first = new User;
    $second = new User;
    $conversation = new Conversation;

    [$repository, $innerRepository] = makeCachedChatMessageRepository();

    $innerRepository->shouldReceive('findOrCreateConversation')
        ->once()
        ->with($first, $second)
        ->andReturn($conversation);

    expect($repository->findOrCreateConversation($first, $second))->toBe($conversation);
});
