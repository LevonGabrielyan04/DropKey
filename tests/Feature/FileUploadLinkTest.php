<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.upload.disk' => 'r2',
        'filesystems.upload.max_file_bytes' => 10 * 1024 * 1024,
        'filesystems.upload.max_storage_bytes' => 10 * 1024 * 1024 * 1024,
        'filesystems.upload.url_expires_minutes' => 5,
        'filesystems.upload.occupied_bytes_cache_seconds' => 0,
    ]);

    Cache::flush();
    Storage::fake('r2');
});

it('creates an r2 upload link for authenticated users', function () {
    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function (string $path, $expiration, array $options = []) {
        return [
            'url' => 'https://r2.example/presigned-put',
            'headers' => [
                'Content-Type' => [$options['ContentType'] ?? 'application/octet-stream'],
                'Content-Length' => [(string) ($options['ContentLength'] ?? 0)],
            ],
        ];
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'notes.txt',
            'content_type' => 'text/plain',
            'size' => 2048,
        ])
        ->assertCreated()
        ->assertJsonPath('url', 'https://r2.example/presigned-put')
        ->assertJsonPath('max_file_bytes', 10 * 1024 * 1024)
        ->assertJsonPath('expires_in', 300)
        ->assertJsonPath('headers.Content-Length.0', '2048')
        ->assertJsonStructure([
            'url',
            'headers',
            'path',
            'max_file_bytes',
            'expires_in',
        ]);
});

it('validates the maximum upload size on the request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'huge.bin',
            'content_type' => 'application/octet-stream',
            'size' => (10 * 1024 * 1024) + 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size']);
});

it('returns insufficient storage when the bucket is at capacity', function () {
    config(['filesystems.upload.max_storage_bytes' => 50]);

    Storage::disk('r2')->put('full.bin', str_repeat('b', 50));
    Cache::flush();

    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function () {
        return [
            'url' => 'https://r2.example/should-not-be-used',
            'headers' => [],
        ];
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'overflow.bin',
            'content_type' => 'application/octet-stream',
            'size' => 1,
        ])
        ->assertStatus(507)
        ->assertJsonPath('message', 'Cloud storage capacity limit exceeded.');
});

it('requires authentication to request an upload link', function () {
    $this->postJson(route('api.uploads.store'), [
        'filename' => 'notes.txt',
        'content_type' => 'text/plain',
        'size' => 100,
    ])->assertUnauthorized();
});
