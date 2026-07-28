<?php

declare(strict_types=1);

namespace App\Support\Cryptography;

use JsonException;

class PublicKeyJwkFingerprint
{
    /**
     * Compute SHA-256(canonical JWK) matching the browser client algorithm.
     *
     * @param  array<string, mixed>  $publicKeyJwk
     *
     * @throws JsonException
     */
    public static function fromPublicKeyJwk(array $publicKeyJwk): string
    {
        $canonical = json_encode([
            'crv' => $publicKeyJwk['crv'],
            'kty' => $publicKeyJwk['kty'],
            'x' => $publicKeyJwk['x'],
            'y' => $publicKeyJwk['y'],
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $canonical);
    }
}
