<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Interfaces\PreparesSendPivotData;

class PrepareSendPivotDataAction implements PreparesSendPivotData
{
    /**
     * Transform a send identifier and viewer IDs into a pivot array.
     *
     * @param  array<int, int>  $viewerIds
     * @return array<int, array{send_id: string, user_id: int}>
     */
    public function execute(string $sendId, array $viewerIds): array
    {
        if (empty($viewerIds)) {
            return [];
        }

        return collect($viewerIds)
            ->map(fn (int $viewerId): array => [
                'send_id' => $sendId,
                'user_id' => $viewerId,
            ])
            ->values()
            ->all();
    }
}
