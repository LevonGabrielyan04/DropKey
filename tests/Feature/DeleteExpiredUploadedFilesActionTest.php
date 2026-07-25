<?php

declare(strict_types=1);

use App\Actions\DeleteExpiredUploadedFilesAction;
use App\Models\User;
use App\Services\UserUploadStorageTracker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.upload.disk' => 'r2',
        'filesystems.upload.occupied_bytes_cache_seconds' => 60,
    ]);

    Cache::flush();
    Storage::fake('r2');
});

it('permanently deletes uploaded files older than the longest chat retention period', function () {
    $user = User::factory()->make(['id' => 1]);
    $expiredPath = "uploads/{$user->id}/expired.bin";
    $freshPath = "uploads/{$user->id}/fresh.bin";

    Storage::disk('r2')->put($expiredPath, 'expired');
    Storage::disk('r2')->put($freshPath, 'fresh');

    touch(
        Storage::disk('r2')->path($expiredPath),
        now()->subDays(31)->getTimestamp(),
    );

    $deletedCount = app(DeleteExpiredUploadedFilesAction::class)->execute();

    expect($deletedCount)->toBe(1);

    Storage::disk('r2')->assertMissing($expiredPath);
    Storage::disk('r2')->assertExists($freshPath);
});

it('returns zero when no uploaded files have expired', function () {
    Storage::disk('r2')->put('uploads/1/fresh.bin', 'fresh');

    expect(app(DeleteExpiredUploadedFilesAction::class)->execute())->toBe(0);

    Storage::disk('r2')->assertExists('uploads/1/fresh.bin');
});

it('invalidates occupied-bytes cache for users whose files were deleted', function () {
    $user = User::factory()->make(['id' => 7]);
    $path = "uploads/{$user->id}/expired.bin";

    Storage::disk('r2')->put($path, str_repeat('a', 40));
    touch(Storage::disk('r2')->path($path), now()->subDays(31)->getTimestamp());

    $tracker = app(UserUploadStorageTracker::class);
    expect($tracker->occupiedBytes($user))->toBe(40);

    app(DeleteExpiredUploadedFilesAction::class)->execute();

    Storage::disk('r2')->assertMissing($path);
    expect($tracker->occupiedBytes($user))->toBe(0);
});
