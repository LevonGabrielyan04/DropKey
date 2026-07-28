<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\MatchesPublicKeyJwkFingerprint;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIdentityKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'public_key_jwk' => [
                'required',
                'array',
            ],
            'public_key_jwk.kty' => [
                'required',
                'string',
                'in:EC',
            ],
            'public_key_jwk.crv' => [
                'required',
                'string',
                'in:P-256',
            ],
            'public_key_jwk.x' => [
                'required',
                'string',
            ],
            'public_key_jwk.y' => [
                'required',
                'string',
            ],
            'public_key_jwk.d' => [
                'prohibited',
            ],
            'fingerprint' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
                new MatchesPublicKeyJwkFingerprint,
            ],
        ];
    }
}
