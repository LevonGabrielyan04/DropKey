<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Interfaces\PreparesSendPivotData;
use App\DTOs\SendData;
use App\Enums\TimePeriod;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendWriteServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SendWriteService implements SendWriteServiceInterface
{
    public function __construct(
        protected SendRepositoryInterface $sendRepository,
        protected PreparesSendPivotData $prepareSendPivotData
    ) {}

    public function createSend(array $data): Send
    {
        $sendData = $this->buildSendData($data, (string) Str::uuid7());

        $viewerIds = $this->resolveViewerIds($data['viewers'] ?? []);
        $pivotData = $this->prepareSendPivotData->execute($sendData->id, $viewerIds->values()->all());

        return $this->sendRepository->create($sendData, $pivotData);
    }

    public function deleteSend(string $id): bool
    {
        return $this->sendRepository->delete($id);
    }

    private function calculateExpiration(string $expire_after): CarbonImmutable
    {
        $period = TimePeriod::tryFrom($expire_after) ?? TimePeriod::ONE_DAY;

        return $period->toCarbon();
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, int>
     */
    private function resolveViewerIds(array $names): Collection
    {
        if (empty($names)) {
            return collect();
        }

        return User::whereIn('name', $names)->pluck('id');
    }

    /**
     * Build a SendData DTO from the validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildSendData(array $data, ?string $id = null): SendData
    {
        return new SendData(
            userId: (int) auth()->id(),
            message: $data['message'],
            name: $data['name'] ?? 'Send-'.time().'-'.Str::random(5),
            validTo: $this->calculateExpiration($data['expire_after'] ?? TimePeriod::ONE_DAY->value),
            id: $id,
        );
    }
}
