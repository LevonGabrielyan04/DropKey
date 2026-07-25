<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CreateFileDownloadLinkData;
use App\DTOs\FileDownloadLinkData;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateFileDownloadLinkAction
{
    public function __construct(
        private ChatMessageRepositoryInterface $chatMessages,
    ) {}

    /**
     * Authorize the requester and create a temporary R2 download URL for an
     * encrypted chat attachment path.
     */
    public function execute(CreateFileDownloadLinkData $data): FileDownloadLinkData
    {
        $expiresInMinutes = (int) config('filesystems.upload.url_expires_minutes');
        $disk = Storage::disk((string) config('filesystems.upload.disk'));
        $owner = $this->resolveObjectOwner($data->path);

        $this->ensureUserMayDownload($data->user, $owner);

        if (! $disk->exists($data->path)) {
            throw new NotFoundHttpException(__('The requested file was not found.'));
        }

        $expiresAt = now()->plus(minutes: $expiresInMinutes);

        return new FileDownloadLinkData(
            url: $disk->temporaryUrl($data->path, $expiresAt),
            path: $data->path,
            expiresInSeconds: $expiresInMinutes * 60,
        );
    }

    private function resolveObjectOwner(string $path): User
    {
        if (preg_match('#^uploads/(\d+)/#', $path, $matches) !== 1) {
            throw ValidationException::withMessages([
                'path' => __('The selected path is invalid.'),
            ]);
        }

        $owner = User::query()->find((int) $matches[1]);

        if ($owner === null) {
            throw new NotFoundHttpException(__('The requested file was not found.'));
        }

        return $owner;
    }

    private function ensureUserMayDownload(User $requester, User $owner): void
    {
        if ($requester->is($owner)) {
            return;
        }

        if ($this->chatMessages->findConversationBetweenUsers($requester, $owner) !== null) {
            return;
        }

        throw new AuthorizationException(__('You are not authorized to download this file.'));
    }
}
