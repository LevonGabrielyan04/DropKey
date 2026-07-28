<?php

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Support\Cryptography\PublicKeyJwkFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function fakeUncompromisedPasswordChecks(): void
{
    Http::fake([
        'api.pwnedpasswords.com/*' => Http::response(''),
    ]);
}

function fakeTurnstileVerification(bool $success = true): void
{
    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => $success,
        ]),
    ]);
}

function enableTurnstileForTests(): void
{
    config([
        'turnstile.site_key' => '1x00000000000000000AA',
        'turnstile.secret_key' => '1x0000000000000000000000000000000AA',
        'turnstile.enabled' => true,
    ]);
}

function withTurnstileToken(array $payload, string $token = 'valid-token'): array
{
    return [...$payload, 'cf-turnstile-response' => $token];
}

/**
 * Build a client-side encrypted message payload for HTTP tests.
 */
function fakeEncryptedMessage(int $ciphertextBytes = 32): string
{
    return json_encode([
        'ciphertext' => base64_encode(random_bytes($ciphertextBytes)),
        'salt' => base64_encode(random_bytes(16)),
        'iv' => base64_encode(random_bytes(12)),
    ], JSON_THROW_ON_ERROR);
}

/**
 * Build a client-side encrypted chat payload for HTTP tests.
 */
function fakeChatPayload(int $ciphertextBytes = 32): string
{
    return json_encode([
        'v' => 1,
        'ciphertext' => base64_encode(random_bytes($ciphertextBytes)),
        'iv' => base64_encode(random_bytes(12)),
    ], JSON_THROW_ON_ERROR);
}

function createConversation(User $first, User $second): Conversation
{
    return app(ChatMessageRepositoryInterface::class)
        ->findOrCreateConversation($first, $second);
}

/**
 * @param  array<string, mixed>  $publicKeyJwk
 */
function publicKeyJwkFingerprint(array $publicKeyJwk): string
{
    return PublicKeyJwkFingerprint::fromPublicKeyJwk($publicKeyJwk);
}

/**
 * @return array<string, mixed>
 */
function validPublicKeyPayload(): array
{
    $publicKeyJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'test-public-x',
        'y' => 'test-public-y',
    ];

    return [
        'public_key_jwk' => $publicKeyJwk,
        'fingerprint' => publicKeyJwkFingerprint($publicKeyJwk),
    ];
}
