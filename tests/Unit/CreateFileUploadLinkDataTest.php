<?php

declare(strict_types=1);

use App\DTOs\CreateFileUploadLinkData;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('builds upload link data from a validated request payload', function () {
    $user = User::factory()->make(['id' => 7]);

    $data = CreateFileUploadLinkData::from($user, [
        'filename' => 'report.bin',
        'content_type' => 'application/octet-stream',
        'size' => '4096',
    ]);

    expect($data->user)->toBe($user)
        ->and($data->filename)->toBe('report.bin')
        ->and($data->contentType)->toBe('application/octet-stream')
        ->and($data->size)->toBe(4096);
});
