<?php

use App\DTOs\SendData;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Eloquent\SendRepository;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendWriteServiceInterface;
use App\Support\SendIndexColumns;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Factories\SendFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(SendRepository::class);
});

it('returns all sends created by the given user', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();

    SendFactory::create($author, [
        'message' => 'secret one',
        'name' => 'First Send',
    ]);
    SendFactory::create($author, [
        'message' => 'secret two',
        'name' => 'Second Send',
    ]);
    SendFactory::create($otherUser, [
        'message' => 'not mine',
        'name' => 'Other User Send',
    ]);

    $sends = $this->repository->findAll((string) $author->id, SendIndexColumns::COLUMNS);

    expect($sends)->toHaveCount(2)
        ->and($sends->pluck('name')->all())->toBe(['First Send', 'Second Send'])
        ->and($sends->every(fn ($send) => $send->relationLoaded('authorizedUsers')))->toBeTrue();
});

it('returns an empty collection when the user has no sends', function () {
    $user = User::factory()->create();

    $sends = $this->repository->findAll((string) $user->id, SendIndexColumns::COLUMNS);

    expect($sends)->toBeEmpty();
});

it('persists the user id from SendData without relying on auth', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser);

    $sendData = new SendData(
        userId: $owner->id,
        message: 'secret',
        name: 'Owner Send',
        validTo: now()->addDay(),
        id: (string) Str::uuid7(),
    );

    $send = $this->repository->create($sendData);

    expect($send->user_id)->toBe($owner->id);
});

it('throws when updating a send that does not exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $sendData = new SendData(
        userId: $user->id,
        message: 'updated secret',
        name: 'Updated Send',
        validTo: now()->addDay(),
    );

    $this->repository->update((string) Str::uuid7(), $sendData);
})->throws(ModelNotFoundException::class);

it('caches findAll results by user id', function () {
    $author = User::factory()->create();
    $columns = SendIndexColumns::COLUMNS;

    SendFactory::create($author, [
        'message' => 'secret one',
        'name' => 'First Send',
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $sends = $repository->findAll((string) $author->id, $columns);
    $cacheKey = 'sends_'.$author->id.'_'.hash('xxh128', json_encode(array_values($columns)));

    expect(Cache::has($cacheKey))->toBeTrue()
        ->and($sends)->toHaveCount(1)
        ->and($sends->first()->name)->toBe('First Send');
});

it('returns hydrated sends from cache without incomplete class errors', function () {
    $author = User::factory()->create();
    $columns = SendIndexColumns::COLUMNS;

    SendFactory::create($author, [
        'message' => 'secret one',
        'name' => 'Cached Send',
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $repository->findAll((string) $author->id, $columns);

    $sends = $repository->findAll((string) $author->id, $columns);

    expect($sends)->toHaveCount(1)
        ->and($sends->first())->toBeInstanceOf(Send::class)
        ->and($sends->first()->name)->toBe('Cached Send');
});

it('invalidates findAll cache after creating a send', function () {
    $author = User::factory()->create();
    $columns = SendIndexColumns::COLUMNS;
    $cacheKey = 'sends_'.$author->id.'_'.hash('xxh128', json_encode(array_values($columns)));

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $this->actingAs($author);

    $repository->findAll((string) $author->id, $columns);

    expect(Cache::has($cacheKey))->toBeTrue();

    app(SendWriteServiceInterface::class)->createSend([
        'name' => 'New Send',
        'message' => 'secret',
        'expire_after' => '1 day',
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('deletes a send by its uuid', function () {
    $author = User::factory()->create();

    $send = SendFactory::create($author, [
        'message' => 'secret',
        'name' => 'Delete Me',
    ]);

    expect($this->repository->delete($send->id))->toBeTrue()
        ->and($this->repository->find($send->id))->toBeNull();
});

it('counts only active sends for the given user', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();

    SendFactory::create($author, [
        'message' => 'active one',
        'name' => 'Active Send',
    ]);
    SendFactory::create($author, [
        'message' => 'expired',
        'name' => 'Expired Send',
        'valid_to' => now()->subMinute(),
    ]);
    SendFactory::create($otherUser, [
        'message' => 'other user',
        'name' => 'Other User Send',
    ]);

    expect($this->repository->countActiveForUser((string) $author->id))->toBe(1);
});

it('caches active send counts by user id', function () {
    $author = User::factory()->create();

    SendFactory::create($author, [
        'message' => 'secret one',
        'name' => 'Active Send',
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $count = $repository->countActiveForUser((string) $author->id);
    $cacheKey = 'active_sends_count_'.$author->id;

    expect($count)->toBe(1)
        ->and(Cache::has($cacheKey))->toBeTrue();
});

it('invalidates active send count cache after creating a send', function () {
    $author = User::factory()->create();
    $cacheKey = 'active_sends_count_'.$author->id;

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $this->actingAs($author);

    $repository->countActiveForUser((string) $author->id);

    expect(Cache::has($cacheKey))->toBeTrue();

    app(SendWriteServiceInterface::class)->createSend([
        'name' => 'New Send',
        'message' => 'secret',
        'expire_after' => '1 day',
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('returns true when a user is authorized to view an active send', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($author);

    $send = app(SendWriteServiceInterface::class)->createSend([
        'name' => 'Shared Send',
        'message' => 'secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    expect($this->repository->userHasActiveAuthorizedAccess((string) $viewer->id, $send->id))->toBeTrue()
        ->and($this->repository->userHasActiveAuthorizedAccess((string) $stranger->id, $send->id))->toBeFalse();
});

it('returns false when a user is authorized but the send has expired', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author);

    $send = app(SendWriteServiceInterface::class)->createSend([
        'name' => 'Expired Shared Send',
        'message' => 'secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $send->update(['valid_to' => now()->subMinute()]);

    expect($this->repository->userHasActiveAuthorizedAccess((string) $viewer->id, $send->id))->toBeFalse();
});

it('caches active authorized access by user and send id', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author);

    $send = app(SendWriteServiceInterface::class)->createSend([
        'name' => 'Shared Send',
        'message' => 'secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $hasAccess = $repository->userHasActiveAuthorizedAccess((string) $viewer->id, $send->id);
    $cacheKey = "active_authorized_access_{$viewer->id}_{$send->id}";

    expect($hasAccess)->toBeTrue()
        ->and(Cache::has($cacheKey))->toBeTrue();
});

it('invalidates active authorized access cache after deleting a send', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($author);

    $send = app(SendWriteServiceInterface::class)->createSend([
        'name' => 'Shared Send',
        'message' => 'secret',
        'expire_after' => '1 day',
        'viewers' => [$viewer->name],
    ]);

    $repository = app(SendRepositoryInterface::class);
    Cache::flush();

    $repository->userHasActiveAuthorizedAccess((string) $viewer->id, $send->id);
    $cacheKey = "active_authorized_access_{$viewer->id}_{$send->id}";

    expect(Cache::has($cacheKey))->toBeTrue();

    $repository->delete($send->id);

    expect(Cache::has($cacheKey))->toBeFalse();
});
