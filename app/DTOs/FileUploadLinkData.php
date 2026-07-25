<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class FileUploadLinkData
{
    public int $maxFileBytes;

    public int $expiresInSeconds;

    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public string $url,
        public array $headers,
        public string $path,
    ) {
        $this->maxFileBytes = (int) config('filesystems.upload.max_file_bytes');
        $this->expiresInSeconds = (int) config('filesystems.upload.url_expires_minutes') * 60;
    }

    /**
     * @return array{url: string, headers: array<string, list<string>>, path: string, max_file_bytes: int, expires_in: int}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'headers' => $this->headers,
            'path' => $this->path,
            'max_file_bytes' => $this->maxFileBytes,
            'expires_in' => $this->expiresInSeconds,
        ];
    }
}
