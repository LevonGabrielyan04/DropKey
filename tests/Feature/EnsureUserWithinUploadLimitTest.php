<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.upload.disk' => 'r2',
        'filesystems.upload.max_file_bytes' => 10 * 1024 * 1024,
        'filesystems.upload.max_storage_bytes' => 50,
        'filesystems.upload.url_expires_minutes' => 5,
        'filesystems.upload.occupied_bytes_cache_seconds' => 0,
    ]);

    Cache::flush();
    Storage::fake('r2');
});

it('blocks upload link requests when the user has already exceeded their quota', function () {
    $user = User::factory()->create();

    Storage::disk('r2')->put("uploads/{$user->id}/full.bin", str_repeat('b', 50));
    Cache::flush();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'content_type' => 'application/octet-stream',
            'size' => 1,
        ])
        ->assertStatus(507)
        ->assertJsonPath('message', 'Cloud storage capacity limit exceeded.')
        ->assertJsonPath('occupied_bytes', 50)
        ->assertJsonPath('max_storage_bytes', 50);
});

it('allows upload link requests when the user is under their quota', function () {
    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function () {
        return [
            'url' => 'https://r2.example/presigned-put',
            'headers' => [],
        ];
    });

    $user = User::factory()->create();

    Storage::disk('r2')->put("uploads/{$user->id}/partial.bin", str_repeat('b', 20));
    Cache::flush();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'content_type' => 'application/octet-stream',
            'size' => 10,
        ])
        ->assertCreated();
});
