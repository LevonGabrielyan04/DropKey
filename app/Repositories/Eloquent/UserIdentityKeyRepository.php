<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\UserIdentityKey;
use App\Repositories\Interfaces\UserIdentityKeyRepositoryInterface;
use App\Support\Cryptography\PublicKeyJwkFingerprint;

class UserIdentityKeyRepository implements UserIdentityKeyRepositoryInterface
{
    public function __construct(protected UserIdentityKey $model) {}

    public function findForUser(int $userId): ?UserIdentityKey
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function updateOrCreateForUser(int $userId, array $publicKeyJwk): UserIdentityKey
    {
        return $this->model->query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'public_key_jwk' => $publicKeyJwk,
                'fingerprint' => PublicKeyJwkFingerprint::fromPublicKeyJwk($publicKeyJwk),
            ],
        );
    }
}
