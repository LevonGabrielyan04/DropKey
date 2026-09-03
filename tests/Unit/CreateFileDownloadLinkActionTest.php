<?php

declare(strict_types=1);

use App\Actions\CreateFileDownloadLinkAction;
use App\DTOs\CreateFileDownloadLinkData;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

it('creates a temporary download link for an owned object', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(function (string $path) {
        return 'https://r2.example/download?path='.urlencode($path);
    });

    $user = User::factory()->create();
    $path = 'uploads/'.$user->id.'/'.Str::uuid7();
    Storage::disk('r2')->put($path, 'ciphertext');

    $link = app(CreateFileDownloadLinkAction::class)->execute(new CreateFileDownloadLinkData(
        user: $user,
        path: $path,
    ));

    expect($link->url)->toBe('https://r2.example/download?path='.urlencode($path))
        ->and($link->path)->toBe($path)
        ->and($link->expiresInSeconds)->toBe(300);
});

it('allows a conversation partner to download an attachment', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn (string $path) => 'https://r2.example/'.$path);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    createConversation($alice, $bob);

    $path = 'uploads/'.$alice->id.'/'.Str::uuid7();
    Storage::disk('r2')->put($path, 'ciphertext');

    $link = app(CreateFileDownloadLinkAction::class)->execute(new CreateFileDownloadLinkData(
        user: $bob,
        path: $path,
    ));

    expect($link->path)->toBe($path);
});

it('rejects download requests from users outside the conversation', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn () => 'https://r2.example/unused');

    $alice = User::factory()->create();
    $stranger = User::factory()->create();
    $path = 'uploads/'.$alice->id.'/'.Str::uuid7();
    Storage::disk('r2')->put($path, 'ciphertext');

    app(CreateFileDownloadLinkAction::class)->execute(new CreateFileDownloadLinkData(
        user: $stranger,
        path: $path,
    ));
})->throws(AuthorizationException::class);

it('rejects download requests for missing objects', function () {
    Storage::disk('r2')->buildTemporaryUrlsUsing(fn () => 'https://r2.example/unused');

    $user = User::factory()->create();
    $path = 'uploads/'.$user->id.'/'.Str::uuid7();

    app(CreateFileDownloadLinkAction::class)->execute(new CreateFileDownloadLinkData(
        user: $user,
        path: $path,
    ));
})->throws(NotFoundHttpException::class);
