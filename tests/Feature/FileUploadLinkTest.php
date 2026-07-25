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
            'content_type' => 'application/octet-stream',
            'size' => 2048,
        ])
        ->assertCreated()
        ->assertJsonPath('url', 'https://r2.example/presigned-put')
        ->assertJsonPath('max_file_bytes', 10 * 1024 * 1024)
        ->assertJsonPath('expires_in', 300)
        ->assertJsonPath('headers.Content-Type.0', 'application/octet-stream')
        ->assertJsonPath('headers.Content-Length.0', '2048')
        ->assertJsonStructure([
            'url',
            'headers',
            'path',
            'max_file_bytes',
            'expires_in',
        ]);
});

it('rejects content types outside the encrypted upload allowlist', function (string $contentType) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'payload.bin',
            'content_type' => $contentType,
            'size' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content_type']);
})->with([
    'text/plain',
    'text/html',
    'application/javascript',
    'image/svg+xml',
    'application/pdf',
]);

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

it('returns insufficient storage when the user is at capacity', function () {
    config(['filesystems.upload.max_storage_bytes' => 50]);

    $user = User::factory()->create();

    Storage::disk('r2')->put("uploads/{$user->id}/full.bin", str_repeat('b', 50));
    Cache::flush();

    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function () {
        return [
            'url' => 'https://r2.example/should-not-be-used',
            'headers' => [],
        ];
    });

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'overflow.bin',
            'content_type' => 'application/octet-stream',
            'size' => 1,
        ])
        ->assertStatus(507)
        ->assertJsonPath('message', 'Cloud storage capacity limit exceeded.');
});

it('does not count another users uploads toward the storage limit', function () {
    config(['filesystems.upload.max_storage_bytes' => 50]);

    $user = User::factory()->create();
    $other = User::factory()->create();

    Storage::disk('r2')->put("uploads/{$other->id}/full.bin", str_repeat('b', 50));
    Cache::flush();

    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function () {
        return [
            'url' => 'https://r2.example/presigned-put',
            'headers' => [],
        ];
    });

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'notes.txt',
            'content_type' => 'application/octet-stream',
            'size' => 20,
        ])
        ->assertCreated();
});

it('requires authentication to request an upload link', function () {
    $this->postJson(route('api.uploads.store'), [
        'filename' => 'notes.txt',
        'content_type' => 'application/octet-stream',
        'size' => 100,
    ])->assertUnauthorized();
});

it('rejects a second upload link that would exceed quota via pending reservations', function () {
    config(['filesystems.upload.max_storage_bytes' => 50]);

    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function () {
        return [
            'url' => 'https://r2.example/presigned-put',
            'headers' => [],
        ];
    });

    $user = User::factory()->create();

    Storage::disk('r2')->put("uploads/{$user->id}/partial.bin", str_repeat('b', 40));
    Cache::flush();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'first.bin',
            'content_type' => 'application/octet-stream',
            'size' => 10,
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.uploads.store'), [
            'filename' => 'second.bin',
            'content_type' => 'application/octet-stream',
            'size' => 10,
        ])
        ->assertStatus(507)
        ->assertJsonPath('message', 'Cloud storage capacity limit exceeded.')
        ->assertJsonPath('occupied_bytes', 50);
});
