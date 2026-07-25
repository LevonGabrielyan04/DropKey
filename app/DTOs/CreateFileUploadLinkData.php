<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;

readonly class CreateFileUploadLinkData
{
    public function __construct(
        public User $user,
        public string $filename,
        public string $contentType,
        public int $size,
    ) {}

    /**
     * @param  array{filename: string, content_type: string, size: int|numeric-string}  $validated
     */
    public static function from(User $user, array $validated): self
    {
        return new self(
            user: $user,
            filename: $validated['filename'],
            contentType: $validated['content_type'],
            size: (int) $validated['size'],
        );
    }
}
