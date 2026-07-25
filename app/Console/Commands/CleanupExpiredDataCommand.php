<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ExpirableCleanupInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Tag;

#[Signature('system:cleanup-expired')]
#[Description('Permanently delete all expired records and files across the system')]
class CleanupExpiredDataCommand extends Command
{
    /**
     * Execute the console command.
     *
     * @param  iterable<ExpirableCleanupInterface>  $cleanupTasks
     */
    public function handle(
        #[Tag('expirable-cleanup')] iterable $cleanupTasks,
    ): int {
        foreach ($cleanupTasks as $task) {
            $deletedCount = $task->execute();
            $entity = $task->getEntityName();

            $this->info("Deleted {$deletedCount} expired {$entity}.");
        }

        return self::SUCCESS;
    }
}
