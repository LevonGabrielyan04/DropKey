<?php

use App\Http\Middleware\RequirePasswordForIdentityKeyOverwrite;
use App\Models\User;
use App\Models\UserIdentityKey;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('skips password confirmation when the user has no identity key', function () {
    $user = User::factory()->create();
    $request = Request::create('/api/identity/public-key', 'POST');
    $request->setUserResolver(fn () => $user);

    $requirePassword = Mockery::mock(RequirePassword::class);
    $requirePassword->shouldNotReceive('handle');

    $middleware = app(RequirePasswordForIdentityKeyOverwrite::class, [
        'requirePassword' => $requirePassword,
    ]);

    $nextCalled = false;

    $response = $middleware->handle($request, function () use (&$nextCalled): Response {
        $nextCalled = true;

        return response('ok');
    });

    expect($nextCalled)->toBeTrue()
        ->and($response->getContent())->toBe('ok');
});

it('delegates to RequirePassword when the user already has an identity key', function () {
    $user = User::factory()->create();

    UserIdentityKey::query()->create([
        'user_id' => $user->id,
        'public_key_jwk' => validPublicKeyPayload()['public_key_jwk'],
        'fingerprint' => validPublicKeyPayload()['fingerprint'],
    ]);

    $request = Request::create('/api/identity/public-key', 'POST');
    $request->setUserResolver(fn () => $user);

    $requirePassword = Mockery::mock(RequirePassword::class);
    $requirePassword->shouldReceive('handle')
        ->once()
        ->with($request, Mockery::type(Closure::class))
        ->andReturn(response('password-required', 423));

    $middleware = app(RequirePasswordForIdentityKeyOverwrite::class, [
        'requirePassword' => $requirePassword,
    ]);

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(423)
        ->and($response->getContent())->toBe('password-required');
});
