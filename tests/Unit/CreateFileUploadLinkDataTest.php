<?php

declare(strict_types=1);

use App\DTOs\CreateFileUploadLinkData;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('builds upload link data from a validated request payload', function () {
    $user = User::factory()->make(['id' => 7]);

    $data = CreateFileUploadLinkData::from($user, [
        'filename' => 'report.pdf',
        'content_type' => 'application/pdf',
        'size' => '4096',
    ]);

    expect($data->user)->toBe($user)
        ->and($data->filename)->toBe('report.pdf')
        ->and($data->contentType)->toBe('application/pdf')
        ->and($data->size)->toBe(4096);
});
