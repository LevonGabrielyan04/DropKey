<?php

declare(strict_types=1);

use App\DTOs\CreateFileUploadLinkData;
use App\Exceptions\CloudStorageCapacityExceededException;
use App\Models\User;
use App\Services\R2UploadService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

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

it('creates a temporary upload link with content length capped by the requested size', function () {
    $capturedOptions = null;

    Storage::disk('r2')->buildTemporaryUploadUrlsUsing(function (string $path, $expiration, array $options = []) use (&$capturedOptions) {
        $capturedOptions = $options;

        return [
            'url' => 'https://r2.example/upload?signature=test',
            'headers' => [
                'Host' => ['r2.example'],
                'Content-Type' => [$options['ContentType']],
                'Content-Length' => [(string) $options['ContentLength']],
            ],
        ];
    });

    $user = User::factory()->make(['id' => 42]);
    $service = app(R2UploadService::class);

    $link = $service->createUploadLink(new CreateFileUploadLinkData(
        user: $user,
        filename: 'secret.pdf',
        contentType: 'application/pdf',
        size: 1_048_576,
    ));

    expect($link->url)->toBe('https://r2.example/upload?signature=test')
        ->and($link->path)->toStartWith('uploads/42/')
        ->and($link->path)->toEndWith('.pdf')
        ->and($link->maxFileBytes)->toBe(10 * 1024 * 1024)
        ->and($link->expiresInSeconds)->toBe(300)
        ->and($capturedOptions)->toMatchArray([
            'ContentType' => 'application/pdf',
            'ContentLength' => 1_048_576,
        ])
        ->and($link->headers['Content-Length'])->toBe(['1048576']);
});

it('rejects files larger than the configured maximum before creating a link', function () {
    $user = User::factory()->make(['id' => 1]);
    $service = app(R2UploadService::class);

    $service->createUploadLink(new CreateFileUploadLinkData(
        user: $user,
        filename: 'too-large.bin',
        contentType: 'application/octet-stream',
        size: (10 * 1024 * 1024) + 1,
    ));
})->throws(ValidationException::class);

it('refuses to create a link when cloud storage would exceed 10gb', function () {
    config(['filesystems.upload.max_storage_bytes' => 100]);

    Storage::disk('r2')->put('existing.bin', str_repeat('a', 90));

    Cache::flush();

    $user = User::factory()->make(['id' => 1]);
    $service = app(R2UploadService::class);

    $service->createUploadLink(new CreateFileUploadLinkData(
        user: $user,
        filename: 'another.bin',
        contentType: 'application/octet-stream',
        size: 20,
    ));
})->throws(CloudStorageCapacityExceededException::class);
