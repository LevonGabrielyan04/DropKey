<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateFileUploadLinkData;
use App\DTOs\FileUploadLinkData;
use App\Exceptions\CloudStorageCapacityExceededException;
use App\Models\User;
use App\Services\Interfaces\R2UploadServiceInterface;
use App\Services\Interfaces\UserUploadStorageTrackerInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class R2UploadService implements R2UploadServiceInterface
{
    public function __construct(
        protected UserUploadStorageTrackerInterface $storage,
    ) {}

    public function createUploadLink(CreateFileUploadLinkData $data): FileUploadLinkData
    {
        $maxFileBytes = (int) config('filesystems.upload.max_file_bytes');
        $maxStorageBytes = (int) config('filesystems.upload.max_storage_bytes');
        $expiresInMinutes = (int) config('filesystems.upload.url_expires_minutes');
        $diskName = (string) config('filesystems.upload.disk');

        if ($data->size > $maxFileBytes) {
            throw ValidationException::withMessages([
                'size' => __('The file may not be greater than :max bytes.', ['max' => $maxFileBytes]),
            ]);
        }

        $disk = Storage::disk($diskName);
        $path = $this->generateObjectPath($data->user, $data->filename);
        $expiresAt = now()->plus(minutes: $expiresInMinutes);

        return Cache::lock($this->capacityLockKey($data->user), 10)->block(5, function () use (
            $disk,
            $diskName,
            $path,
            $data,
            $maxFileBytes,
            $maxStorageBytes,
            $expiresAt,
            $expiresInMinutes,
        ): FileUploadLinkData {
            $this->ensureWithinUploadLimit($data->user, $data->size, $disk, $diskName, $maxStorageBytes);

            /**
             * R2 supports presigned PUT only (not POST policies), so the signed
             * ContentLength header is how the upload URL is capped to <= 10MB.
             *
             * @var array{url: string, headers: array<string, list<string>>} $upload
             */
            $upload = $disk->temporaryUploadUrl($path, $expiresAt, [
                'ContentType' => $data->contentType,
                'ContentLength' => $data->size,
            ]);

            $this->storage->reservePendingBytes($data->user, $path, $data->size, $expiresAt, $diskName);

            return new FileUploadLinkData(
                url: $upload['url'],
                headers: $upload['headers'],
                path: $path,
                maxFileBytes: $maxFileBytes,
                expiresInSeconds: $expiresInMinutes * 60,
            );
        });
    }

    public function ensureWithinUploadLimit(User $user, int $requestedBytes = 0, ?Filesystem $disk = null, ?string $diskName = null, ?int $maxStorageBytes = null): void
    {
        $diskName ??= (string) config('filesystems.upload.disk');
        $disk ??= Storage::disk($diskName);
        $maxStorageBytes ??= (int) config('filesystems.upload.max_storage_bytes');
        $occupiedBytes = $this->storage->occupiedBytes($user, $disk, $diskName);

        $exceedsLimit = $requestedBytes <= 0
            ? $occupiedBytes >= $maxStorageBytes
            : ($occupiedBytes + $requestedBytes) > $maxStorageBytes;

        if ($exceedsLimit) {
            throw new CloudStorageCapacityExceededException(
                $occupiedBytes,
                $maxStorageBytes,
                $requestedBytes,
            );
        }
    }

    private function generateObjectPath(User $user, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $safeExtension = $extension !== ''
            ? Str::lower(Str::substr($extension, 0, 20))
            : 'bin';

        return sprintf(
            '%s/%s.%s',
            $this->storage->userPrefix($user),
            (string) Str::ulid(),
            $safeExtension,
        );
    }

    private function capacityLockKey(User $user): string
    {
        return "r2-upload-capacity:{$user->id}";
    }
}
