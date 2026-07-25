<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;

interface UserUploadStorageTrackerInterface
{
    /**
     * Effective occupied bytes = on-disk usage + bytes reserved by outstanding upload URLs.
     */
    public function occupiedBytes(User $user, ?Filesystem $disk = null, ?string $diskName = null): int;

    /**
     * Reserve bytes for an outstanding upload URL until it expires.
     */
    public function reservePendingBytes(
        User $user,
        string $path,
        int $bytes,
        DateTimeInterface $expiresAt,
        ?string $diskName = null,
    ): void;

    /**
     * Object-key prefix for the user's uploads on the cloud disk.
     */
    public function userPrefix(User $user): string;
}
