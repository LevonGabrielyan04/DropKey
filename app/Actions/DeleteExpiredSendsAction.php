<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ExpirableCleanupInterface;
use App\Repositories\Interfaces\SendRepositoryInterface;

class DeleteExpiredSendsAction implements ExpirableCleanupInterface
{
    public function __construct(private SendRepositoryInterface $sendRepository) {}

    /**
     * Permanently delete all sends whose validity has expired.
     */
    public function execute(): int
    {
        return $this->sendRepository->deleteExpired();
    }

    public function getEntityName(): string
    {
        return 'send(s)';
    }
}
