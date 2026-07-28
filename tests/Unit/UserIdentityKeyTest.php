<?php

use App\Models\User;
use App\Models\UserIdentityKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('assigns a unique browser database id when creating an identity key', function () {
    $user = User::factory()->create();
    $payload = validPublicKeyPayload();

    $identityKey = UserIdentityKey::query()->create([
        'user_id' => $user->id,
        ...$payload,
    ]);

    expect($identityKey->browser_db_id)
        ->not->toBeEmpty()
        ->and(Str::isUlid($identityKey->browser_db_id))->toBeTrue();
});

it('preserves an existing browser database id on update', function () {
    $user = User::factory()->create();
    $payload = validPublicKeyPayload();

    $identityKey = UserIdentityKey::query()->create([
        'user_id' => $user->id,
        ...$payload,
    ]);

    $browserDbId = $identityKey->browser_db_id;

    $rotatedJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'rotated-x',
        'y' => 'rotated-y',
    ];

    $identityKey->update([
        'public_key_jwk' => $rotatedJwk,
        'fingerprint' => publicKeyJwkFingerprint($rotatedJwk),
    ]);

    expect($identityKey->fresh()->browser_db_id)->toBe($browserDbId);
});

it('enforces unique browser database ids', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $payload = validPublicKeyPayload();

    $firstIdentityKey = UserIdentityKey::query()->create([
        'user_id' => $firstUser->id,
        ...$payload,
    ]);

    $secondJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'second-user-x',
        'y' => 'second-user-y',
    ];

    expect(fn () => UserIdentityKey::query()->create([
        'user_id' => $secondUser->id,
        'browser_db_id' => $firstIdentityKey->browser_db_id,
        'public_key_jwk' => $secondJwk,
        'fingerprint' => publicKeyJwkFingerprint($secondJwk),
    ]))->toThrow(UniqueConstraintViolationException::class);
});
