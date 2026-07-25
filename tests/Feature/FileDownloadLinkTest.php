<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

it('creates an r2 download link for the uploader', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(function (string $path, $expiration, array $options = []) {
        return 'https://r2.example/presigned-get?path='.urlencode($path);
    });

    $user = User::factory()->create();
    $path = 'uploads/'.$user->id.'/'.Str::ulid().'.bin';

    Storage::disk('r2')->put($path, 'ciphertext');

    $this->actingAs($user)
        ->postJson(route('api.uploads.download'), ['path' => $path])
        ->assertSuccessful()
        ->assertJsonPath('url', 'https://r2.example/presigned-get?path='.urlencode($path))
        ->assertJsonPath('path', $path)
        ->assertJsonPath('expires_in', 300);
});

it('creates an r2 download link for a conversation partner', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(function (string $path) {
        return 'https://r2.example/presigned-get?path='.urlencode($path);
    });

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    createConversation($alice, $bob);

    $path = 'uploads/'.$alice->id.'/'.Str::ulid().'.bin';
    Storage::disk('r2')->put($path, 'ciphertext');

    $this->actingAs($bob)
        ->postJson(route('api.uploads.download'), ['path' => $path])
        ->assertSuccessful()
        ->assertJsonPath('path', $path);
});

it('forbids download when the requester has no conversation with the uploader', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn () => 'https://r2.example/unused');

    $alice = User::factory()->create();
    $stranger = User::factory()->create();
    $path = 'uploads/'.$alice->id.'/'.Str::ulid().'.bin';

    Storage::disk('r2')->put($path, 'ciphertext');

    $this->actingAs($stranger)
        ->postJson(route('api.uploads.download'), ['path' => $path])
        ->assertForbidden();
});

it('returns not found when the object is missing', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn () => 'https://r2.example/unused');

    $user = User::factory()->create();
    $path = 'uploads/'.$user->id.'/'.Str::ulid().'.bin';

    $this->actingAs($user)
        ->postJson(route('api.uploads.download'), ['path' => $path])
        ->assertNotFound();
});

it('rejects invalid download paths', function (string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.uploads.download'), ['path' => $path])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['path']);
})->with([
    '../uploads/1/01ARZ3NDEKTSV4RRFFQ69G5FAV.bin',
    'uploads/1/not-a-ulid.bin',
    'uploads/1/01ARZ3NDEKTSV4RRFFQ69G5FAV.pdf.exe',
    'other/1/01ARZ3NDEKTSV4RRFFQ69G5FAV.bin',
]);

it('requires authentication to request a download link', function () {
    $this->postJson(route('api.uploads.download'), [
        'path' => 'uploads/1/'.Str::ulid().'.bin',
    ])->assertUnauthorized();
});
