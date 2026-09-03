<?php

use App\DTOs\SendData;
use App\Repositories\Eloquent\CachedSendsRepository;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Support\SendIndexColumns;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Factories\SendFactory;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    Mockery::close();
    Cache::flush();
});

/**
 * @return array<int, string>
 */
function indexColumns(): array
{
    return SendIndexColumns::COLUMNS;
}

function sendsListCacheKey(string $userId, array $columns): string
{
    return 'sends_'.$userId.'_'.hash('xxh128', json_encode(array_values($columns)));
}

function makeCachedRepository(
    ?SendRepositoryInterface $innerRepository = null,
    ?CacheRepository $cache = null,
): array {
    $innerRepository ??= Mockery::mock(SendRepositoryInterface::class);
    $cache ??= Cache::store();

    return [
        new CachedSendsRepository($innerRepository, $cache),
        $innerRepository,
        $cache,
    ];
}

it('find returns a cached send without querying the inner repository', function () {
    $send = SendFactory::make();

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('find')->once()->with($send->id)->andReturn($send);

    expect($repository->find($send->id))->toBe($send)
        ->and($repository->find($send->id))->toEqual($send);
});

it('find queries the inner repository on a cache miss', function () {
    $send = SendFactory::make();

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('find')->once()->with($send->id)->andReturn($send);

    expect($repository->find($send->id))->toBe($send);
});

it('find caches a missing send and does not query the inner repository again', function () {
    $sendId = (string) Str::uuid7();

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('find')->once()->with($sendId)->andReturnNull();

    expect($repository->find($sendId))->toBeNull();
    expect($repository->find($sendId))->toBeNull();
});

it('find returns an expired send and caches it with the negative ttl', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60, 'send.negative_cache_ttl' => 5]);

    $send = SendFactory::make();
    $send->valid_to = now()->subMinute();

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $innerRepository->shouldReceive('find')->once()->with($send->id)->andReturn($send);

    expect($repository->find($send->id))->toBe($send)
        ->and($repository->find($send->id))->toEqual($send)
        ->and($cache->get("send_{$send->id}"))->toEqual($send);
});

it('find uses the configured ttl for active sends', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60, 'send.negative_cache_ttl' => 5]);

    $send = SendFactory::make();
    $send->valid_to = now()->addMinutes(30);

    [$repository, $innerRepository] = makeCachedRepository(cache: Cache::store('array'));

    $innerRepository->shouldReceive('find')->twice()->with($send->id)->andReturn($send);

    $repository->find($send->id);

    Carbon::setTestNow(now()->addMinutes(30)->addSecond());

    $repository->find($send->id);
});

it('find uses the negative cache ttl for missing sends', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60, 'send.negative_cache_ttl' => 5]);

    $sendId = (string) Str::uuid7();

    [$repository, $innerRepository] = makeCachedRepository(cache: Cache::store('array'));

    $innerRepository->shouldReceive('find')->twice()->with($sendId)->andReturnNull();

    $repository->find($sendId);

    Carbon::setTestNow(now()->addMinutes(5)->addSecond());

    $repository->find($sendId);
});

it('findAll returns a cached collection without querying the inner repository', function () {
    $send = SendFactory::make(1, ['name' => 'Cached Send']);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $collection = new Collection([$send]);

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('findAll')->once()->with($userId, $columns)->andReturn($collection);

    expect($repository->findAll($userId, $columns))->toHaveCount(1)
        ->and($repository->findAll($userId, $columns)->first())->toEqual($send);
});

it('findAll queries the inner repository on a cache miss', function () {
    $send = SendFactory::make(1, ['name' => 'Fresh Send']);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $collection = new Collection([$send]);

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('findAll')->once()->with($userId, $columns)->andReturn($collection);

    expect($repository->findAll($userId, $columns))->toBe($collection);
});

it('findAll refreshes list cache when any send valid_to is in the past', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60, 'send.negative_cache_ttl' => 5]);

    $expiredSend = SendFactory::make(1, ['name' => 'Expired Send']);
    $expiredSend->valid_to = now()->subMinute();
    $activeSend = SendFactory::make($expiredSend->user_id, ['name' => 'Active Send']);
    $activeSend->valid_to = now()->addDay();

    $userId = (string) $expiredSend->user_id;
    $columns = indexColumns();
    $staleCollection = new Collection([$expiredSend, $activeSend]);
    $freshCollection = new Collection([$activeSend]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();
    $cacheKey = sendsListCacheKey($userId, $columns);

    $cache->put($cacheKey, $staleCollection, now()->addMinutes(60));

    $innerRepository->shouldReceive('findAll')->once()->with($userId, $columns)->andReturn($freshCollection);

    expect($repository->findAll($userId, $columns))->toBe($freshCollection);
});

it('findAll recovers when the cached value is not a collection', function () {
    $send = SendFactory::make(1, ['name' => 'Recovered Send']);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->put($cacheKey, 'not-a-collection', now()->addMinutes(60));

    $innerRepository->shouldReceive('findAll')->once()->with($userId, $columns)->andReturn($collection);

    expect($repository->findAll($userId, $columns))->toBe($collection);
});

it('findAll recovers when the cached collection contains non-send items', function () {
    $send = SendFactory::make(1, ['name' => 'Recovered Send']);
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $cacheKey = sendsListCacheKey($userId, $columns);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->put($cacheKey, new Collection(['oops']), now()->addMinutes(60));

    $innerRepository->shouldReceive('findAll')->once()->with($userId, $columns)->andReturn($collection);

    $result = $repository->findAll($userId, $columns);

    expect($result)->toBe($collection)
        ->and($result->first())->toEqual($send);
});

it('create stores the send in cache and invalidates the user send list cache', function () {
    $send = SendFactory::make();
    $userId = (string) $send->user_id;
    $columns = indexColumns();
    $sendData = new SendData(
        userId: $send->user_id,
        message: 'secret',
        name: 'Test Send',
        validTo: CarbonImmutable::now()->addDay(),
        id: $send->id,
    );

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $innerRepository->shouldReceive('create')
        ->once()
        ->with($sendData, [])
        ->andReturn($send);

    $cache->put("sends_{$userId}", new Collection, now()->addMinutes(60));
    $cache->put(sendsListCacheKey($userId, $columns), new Collection, now()->addMinutes(60));
    $cache->put("active_sends_count_{$userId}", 1, now()->addMinutes(60));

    expect($repository->create($sendData))->toBe($send)
        ->and($cache->get("send_{$send->id}"))->toEqual($send)
        ->and($cache->has("sends_{$userId}"))->toBeFalse()
        ->and($cache->has(sendsListCacheKey($userId, $columns)))->toBeFalse()
        ->and($cache->has("active_sends_count_{$userId}"))->toBeFalse();
});

it('countActiveForUser casts cached scalar values to int', function () {
    $userId = '42';

    [$repository, $innerRepository, $cache] = makeCachedRepository();

    $cache->put(sendsListCacheKey($userId, ['valid_to']), new Collection([]), now()->addMinutes(60));
    $cache->put("active_sends_count_{$userId}", '3', now()->addMinutes(60));

    $innerRepository->shouldNotReceive('countActiveForUser');
    $innerRepository->shouldNotReceive('findAll');

    expect($repository->countActiveForUser($userId))->toBe(3);
});

it('countActiveForUser uses remember with an expiration derived from send valid_to', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60, 'send.negative_cache_ttl' => 5]);

    $userId = '42';

    $send = SendFactory::make((int) $userId);
    $send->valid_to = now()->addMinutes(30);
    $collection = new Collection([$send]);

    [$repository, $innerRepository, $cache] = makeCachedRepository(cache: Cache::store('array'));

    $cache->put(sendsListCacheKey($userId, ['valid_to']), $collection, now()->addMinutes(60));

    $innerRepository->shouldReceive('findAll')->once()->with($userId, ['valid_to'])->andReturn($collection);
    $innerRepository->shouldReceive('countActiveForUser')->twice()->with($userId)->andReturn(1);

    expect($repository->countActiveForUser($userId))->toBe(1);

    Carbon::setTestNow(now()->addMinutes(30)->addSecond());

    expect($repository->countActiveForUser($userId))->toBe(1);
});

it('userHasActiveAuthorizedAccess caches the result from the inner repository', function () {
    Carbon::setTestNow(now());
    config(['send.cache_ttl' => 60, 'send.negative_cache_ttl' => 5]);

    $userId = '42';
    $sendId = (string) Str::uuid7();

    $send = SendFactory::make((int) $userId, ['id' => $sendId]);
    $send->valid_to = now()->addMinutes(30);

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('find')->once()->with($sendId)->andReturn($send);
    $innerRepository->shouldReceive('userHasActiveAuthorizedAccess')->once()->with($userId, $sendId)->andReturnTrue();

    expect($repository->userHasActiveAuthorizedAccess($userId, $sendId))->toBeTrue()
        ->and($repository->userHasActiveAuthorizedAccess($userId, $sendId))->toBeTrue();
});

it('rememberLocked prevents duplicate inner repository calls for concurrent misses', function () {
    $sendId = (string) Str::uuid7();

    [$repository, $innerRepository] = makeCachedRepository();

    $innerRepository->shouldReceive('find')->once()->with($sendId)->andReturnNull();

    $results = collect(range(1, 5))->map(
        fn (): ?object => $repository->find($sendId),
    );

    expect($results->unique()->all())->toBe([null]);
});
