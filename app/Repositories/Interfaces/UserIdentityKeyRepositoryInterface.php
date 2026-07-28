<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\UserIdentityKey;

interface UserIdentityKeyRepositoryInterface
{
    public function findForUser(int $userId): ?UserIdentityKey;

    /**
     * @param  array<string, mixed>  $publicKeyJwk
     */
    public function updateOrCreateForUser(int $userId, array $publicKeyJwk): UserIdentityKey;
}
