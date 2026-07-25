<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

use App\DTOs\CreateFileUploadLinkData;
use App\DTOs\FileUploadLinkData;
use App\Models\User;

interface R2UploadServiceInterface
{
    /**
     * Create a temporary Cloudflare R2 upload link for the given file metadata.
     */
    public function createUploadLink(CreateFileUploadLinkData $data): FileUploadLinkData;

    /**
     * Refuse the request when the user's occupied storage plus any requested
     * bytes would exceed the configured per-user upload quota.
     */
    public function ensureWithinUploadLimit(User $user, int $requestedBytes = 0): void;
}
