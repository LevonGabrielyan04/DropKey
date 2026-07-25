<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExpirableCleanupInterface
{
    /**
     * Execute the cleanup and return the number of deleted records or objects.
     */
    public function execute(): int;

    /**
     * Human-readable name of the entity being deleted (e.g. "chat message(s)").
     */
    public function getEntityName(): string;
}
