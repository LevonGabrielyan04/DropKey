<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class FileUploadLinkData
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public string $url,
        public array $headers,
        public string $path,
        public int $maxFileBytes,
        public int $expiresInSeconds,
    ) {}

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
