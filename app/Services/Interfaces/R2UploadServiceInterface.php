<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

use App\DTOs\CreateFileUploadLinkData;
use App\DTOs\FileUploadLinkData;

interface R2UploadServiceInterface
{
    /**
     * Create a temporary Cloudflare R2 upload link for the given file metadata.
     */
    public function createUploadLink(CreateFileUploadLinkData $data): FileUploadLinkData;
}
