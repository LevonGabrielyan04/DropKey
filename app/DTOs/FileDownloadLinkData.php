<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class FileDownloadLinkData
{
    public int $expiresInSeconds;

    public function __construct(
        public string $url,
        public string $path,
    ) {
        $this->expiresInSeconds = (int) config('filesystems.upload.url_expires_minutes') * 60;
    }

    /**
     * @return array{url: string, path: string, expires_in: int}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'path' => $this->path,
            'expires_in' => $this->expiresInSeconds,
        ];
    }
}
