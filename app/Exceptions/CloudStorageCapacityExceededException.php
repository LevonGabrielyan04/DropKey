<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudStorageCapacityExceededException extends Exception implements ShouldntReport
{
    public function __construct(
        public readonly int $occupiedBytes,
        public readonly int $maxStorageBytes,
        public readonly int $requestedBytes,
    ) {
        parent::__construct('Cloud storage capacity limit exceeded.');
    }

    /**
     * @return array{occupied_bytes: int, max_storage_bytes: int, requested_bytes: int}
     */
    public function context(): array
    {
        return [
            'occupied_bytes' => $this->occupiedBytes,
            'max_storage_bytes' => $this->maxStorageBytes,
            'requested_bytes' => $this->requestedBytes,
        ];
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'occupied_bytes' => $this->occupiedBytes,
            'max_storage_bytes' => $this->maxStorageBytes,
            'requested_bytes' => $this->requestedBytes,
        ], 507);
    }
}
