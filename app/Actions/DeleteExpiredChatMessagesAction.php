<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ExpirableCleanupInterface;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;

class DeleteExpiredChatMessagesAction implements ExpirableCleanupInterface
{
    public function __construct(private ChatMessageRepositoryInterface $chatMessages) {}

    /**
     * Permanently delete all chat messages older than the configured retention period.
     */
    public function execute(): int
    {
        return $this->chatMessages->deleteExpired();
    }

    public function getEntityName(): string
    {
        return 'chat message(s)';
    }
}
