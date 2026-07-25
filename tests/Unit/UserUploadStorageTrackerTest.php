<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UserUploadStorageTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'filesystems.upload.disk' => 'r2',
        'filesystems.upload.occupied_bytes_cache_seconds' => 0,
    ]);

    Cache::flush();
    Storage::fake('r2');
});

it('sums on-disk usage for the user prefix', function () {
    $user = User::factory()->make(['id' => 1]);
    $other = User::factory()->make(['id' => 2]);

    Storage::disk('r2')->put("uploads/{$user->id}/a.bin", str_repeat('a', 40));
    Storage::disk('r2')->put("uploads/{$user->id}/b.bin", str_repeat('b', 15));
    Storage::disk('r2')->put("uploads/{$other->id}/c.bin", str_repeat('c', 90));

    $tracker = app(UserUploadStorageTracker::class);

    expect($tracker->occupiedBytes($user))->toBe(55)
        ->and($tracker->userPrefix($user))->toBe('uploads/1');
});

it('includes pending reservations in occupied bytes', function () {
    $user = User::factory()->make(['id' => 1]);

    Storage::disk('r2')->put("uploads/{$user->id}/existing.bin", str_repeat('a', 90));

    $tracker = app(UserUploadStorageTracker::class);

    $tracker->reservePendingBytes(
        $user,
        "uploads/{$user->id}/pending.bin",
        10,
        now()->plus(minutes: 5),
    );

    expect($tracker->occupiedBytes($user))->toBe(100);
});

it('counts pending reservations even when the occupied-bytes cache is warm', function () {
    config(['filesystems.upload.occupied_bytes_cache_seconds' => 60]);

    $user = User::factory()->make(['id' => 1]);

    Storage::disk('r2')->put("uploads/{$user->id}/existing.bin", str_repeat('a', 90));
    Cache::flush();

    $tracker = app(UserUploadStorageTracker::class);

    // Warm the occupied-bytes cache at 90 before any reservation exists.
    expect($tracker->occupiedBytes($user))->toBe(90);

    $tracker->reservePendingBytes(
        $user,
        "uploads/{$user->id}/pending.bin",
        10,
        now()->plus(minutes: 5),
    );

    expect($tracker->occupiedBytes($user))->toBe(100);
});

it('releases pending reservations after they expire', function () {
    $user = User::factory()->make(['id' => 1]);

    Storage::disk('r2')->put("uploads/{$user->id}/existing.bin", str_repeat('a', 90));

    $tracker = app(UserUploadStorageTracker::class);

    $tracker->reservePendingBytes(
        $user,
        "uploads/{$user->id}/pending.bin",
        10,
        now()->plus(minutes: 5),
    );

    expect($tracker->occupiedBytes($user))->toBe(100);

    $this->travel(301)->seconds();

    expect($tracker->occupiedBytes($user))->toBe(90);
});

it('invalidates the occupied-bytes cache when pending reservations expire', function () {
    config(['filesystems.upload.occupied_bytes_cache_seconds' => 60]);

    $user = User::factory()->make(['id' => 1]);

    Storage::disk('r2')->put("uploads/{$user->id}/existing.bin", str_repeat('a', 90));
    Cache::flush();

    $tracker = app(UserUploadStorageTracker::class);

    $tracker->reservePendingBytes(
        $user,
        "uploads/{$user->id}/pending.bin",
        10,
        now()->plus(minutes: 5),
    );

    // Warm cache while the reservation is still active (disk 90 + pending 10).
    expect($tracker->occupiedBytes($user))->toBe(100);

    Storage::disk('r2')->put("uploads/{$user->id}/uploaded.bin", str_repeat('c', 10));

    $this->travel(301)->seconds();

    // Expired reservation should clear the stale disk cache so the upload is visible.
    expect($tracker->occupiedBytes($user))->toBe(100);
});
