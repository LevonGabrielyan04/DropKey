<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Cryptography\PublicKeyJwkFingerprint;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use JsonException;

class MatchesPublicKeyJwkFingerprint implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $publicKeyJwk = $this->data['public_key_jwk'] ?? null;

        if (! is_array($publicKeyJwk)) {
            return;
        }

        foreach (['crv', 'kty', 'x', 'y'] as $key) {
            if (! isset($publicKeyJwk[$key]) || ! is_string($publicKeyJwk[$key])) {
                return;
            }
        }

        try {
            $expected = PublicKeyJwkFingerprint::fromPublicKeyJwk($publicKeyJwk);
        } catch (JsonException) {
            return;
        }

        if (! hash_equals($expected, $value)) {
            $fail('The fingerprint does not match the public key.');
        }
    }
}
