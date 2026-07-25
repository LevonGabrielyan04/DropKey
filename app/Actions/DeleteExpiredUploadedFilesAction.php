<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ExpirableCleanupInterface;
use App\Enums\TimePeriod;
use App\Services\Interfaces\UserUploadStorageTrackerInterface;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\StorageAttributes;

class DeleteExpiredUploadedFilesAction implements ExpirableCleanupInterface
{
    private const DELETE_CHUNK_SIZE = 1000;

    public function __construct(
        private UserUploadStorageTrackerInterface $storage,
    ) {}

    /**
     * Permanently delete cloud upload objects older than the longest chat
     * retention window. Attachment paths live inside E2EE payloads, so age on
     * the bucket is the only server-side signal available for cleanup.
     */
    public function execute(): int
    {
        $diskName = (string) config('filesystems.upload.disk');
        $disk = Storage::disk($diskName);
        $cutoff = TimePeriod::THIRTY_DAYS->retentionCutoff()->getTimestamp();

        $pathsToDelete = [];
        /** @var array<int, true> $affectedUserIds */
        $affectedUserIds = [];

        foreach ($disk->getDriver()->listContents('uploads', true) as $attributes) {
            if (! $attributes instanceof StorageAttributes || ! $attributes->isFile()) {
                continue;
            }

            $lastModified = $attributes->lastModified();

            if ($lastModified === null || $lastModified >= $cutoff) {
                continue;
            }

            $path = $attributes->path();
            $pathsToDelete[] = $path;

            if (preg_match('#^uploads/(\d+)/#', $path, $matches) === 1) {
                $affectedUserIds[(int) $matches[1]] = true;
            }
        }

        if ($pathsToDelete === []) {
            return 0;
        }

        foreach (array_chunk($pathsToDelete, self::DELETE_CHUNK_SIZE) as $chunk) {
            $disk->delete($chunk);
        }

        foreach (array_keys($affectedUserIds) as $userId) {
            $this->storage->forgetOccupiedBytes($userId, $diskName);
        }

        return count($pathsToDelete);
    }

    public function getEntityName(): string
    {
        return 'uploaded file(s)';
    }
}
