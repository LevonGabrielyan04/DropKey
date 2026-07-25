<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;

readonly class CreateFileDownloadLinkData
{
    public function __construct(
        public User $user,
        public string $path,
    ) {}

    /**
     * @param  array{path: string}  $validated
     */
    public static function from(User $user, array $validated): self
    {
        return new self(
            user: $user,
            path: $validated['path'],
        );
    }
}
