<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Interfaces\UserUploadStorageTrackerInterface;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

class UserUploadStorageTracker implements UserUploadStorageTrackerInterface
{
    public function occupiedBytes(User $user, ?Filesystem $disk = null, ?string $diskName = null): int
    {
        $diskName ??= (string) config('filesystems.upload.disk');
        $disk ??= Storage::disk($diskName);

        // Resolve pending first so expired reservations can invalidate the
        // occupied-bytes cache before on-disk usage is read.
        $pendingBytes = $this->pendingBytes($user, $diskName);

        return $this->diskOccupiedBytes($user, $disk, $diskName) + $pendingBytes;
    }

    public function reservePendingBytes(
        User $user,
        string $path,
        int $bytes,
        DateTimeInterface $expiresAt,
        ?string $diskName = null,
    ): void {
        $diskName ??= (string) config('filesystems.upload.disk');
        $key = $this->pendingBytesCacheKey($user);
        /** @var array<string, array{bytes: int, expires_at: int}> $reservations */
        $reservations = Cache::get($key, []);
        $reservations = $this->pruneExpiredReservations($reservations, $diskName, $user);

        $reservations[$path] = [
            'bytes' => $bytes,
            'expires_at' => $expiresAt->getTimestamp(),
        ];

        $this->storePendingReservations($key, $reservations);
    }

    public function userPrefix(User $user): string
    {
        return sprintf('uploads/%d', $user->id);
    }

    public function forgetOccupiedBytes(int $userId, ?string $diskName = null): void
    {
        $diskName ??= (string) config('filesystems.upload.disk');

        Cache::forget($this->occupiedBytesCacheKeyForUserId($diskName, $userId));
    }

    private function diskOccupiedBytes(User $user, Filesystem $disk, string $diskName): int
    {
        $ttlSeconds = (int) config('filesystems.upload.occupied_bytes_cache_seconds');

        if ($ttlSeconds <= 0) {
            return $this->calculateOccupiedBytes($user, $disk);
        }

        return (int) Cache::remember(
            $this->occupiedBytesCacheKey($diskName, $user),
            $ttlSeconds,
            fn (): int => $this->calculateOccupiedBytes($user, $disk),
        );
    }

    private function calculateOccupiedBytes(User $user, Filesystem $disk): int
    {
        $total = 0;
        $prefix = $this->userPrefix($user);

        foreach ($disk->getDriver()->listContents($prefix, true) as $attributes) {
            if (! $attributes instanceof FileAttributes) {
                continue;
            }

            $total += $attributes->fileSize() ?? 0;
        }

        return $total;
    }

    /**
     * @param  array<string, array{bytes: int, expires_at: int}>  $reservations
     * @return array<string, array{bytes: int, expires_at: int}>
     */
    private function pruneExpiredReservations(array $reservations, string $diskName, User $user): array
    {
        $now = now()->getTimestamp();
        $active = [];

        foreach ($reservations as $path => $reservation) {
            if ($reservation['expires_at'] > $now) {
                $active[$path] = $reservation;
            }
        }

        if (count($active) !== count($reservations)) {
            // Expired reservations may have been uploaded; refresh on-disk usage next check.
            Cache::forget($this->occupiedBytesCacheKey($diskName, $user));
        }

        return $active;
    }

    private function pendingBytes(User $user, string $diskName): int
    {
        $key = $this->pendingBytesCacheKey($user);
        /** @var array<string, array{bytes: int, expires_at: int}> $reservations */
        $reservations = Cache::get($key, []);

        if ($reservations === []) {
            return 0;
        }

        $active = $this->pruneExpiredReservations($reservations, $diskName, $user);

        if ($active !== $reservations) {
            $this->storePendingReservations($key, $active);
        }

        return (int) array_sum(array_column($active, 'bytes'));
    }

    /**
     * @param  array<string, array{bytes: int, expires_at: int}>  $reservations
     */
    private function storePendingReservations(string $key, array $reservations): void
    {
        if ($reservations === []) {
            Cache::forget($key);

            return;
        }

        // Entries carry their own expires_at; keep the map until pruned so
        // occupied-bytes cache invalidation still runs when reservations lapse.
        Cache::forever($key, $reservations);
    }

    private function occupiedBytesCacheKey(string $diskName, User $user): string
    {
        return $this->occupiedBytesCacheKeyForUserId($diskName, $user->id);
    }

    private function occupiedBytesCacheKeyForUserId(string $diskName, int $userId): string
    {
        return "r2-storage-occupied-bytes:{$diskName}:{$userId}";
    }

    private function pendingBytesCacheKey(User $user): string
    {
        return "r2-upload-pending-bytes:{$user->id}";
    }
}
