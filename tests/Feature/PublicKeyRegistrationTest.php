<?php

use App\Models\User;
use App\Models\UserIdentityKey;
use Illuminate\Support\Facades\Hash;

it('registers a user public key', function () {
    $user = User::factory()->create();
    $payload = validPublicKeyPayload();

    $response = $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), $payload)
        ->assertSuccessful()
        ->assertJson(['status' => 'ok']);

    $identityKey = UserIdentityKey::query()->where('user_id', $user->id)->first();

    expect($identityKey)->not->toBeNull()
        ->and($identityKey->public_key_jwk['x'])->toBe('test-public-x')
        ->and($identityKey->fingerprint)->toBe($payload['fingerprint'])
        ->and($identityKey->browser_db_id)->not->toBeEmpty();

    $response->assertJsonPath('browser_db_id', $identityKey->browser_db_id);
});

it('updates an existing public key registration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertSuccessful();

    $originalBrowserDbId = UserIdentityKey::query()
        ->where('user_id', $user->id)
        ->firstOrFail()
        ->browser_db_id;

    $rotatedJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'rotated-x',
        'y' => 'rotated-y',
    ];

    $rotatedPayload = [
        'public_key_jwk' => $rotatedJwk,
        'fingerprint' => publicKeyJwkFingerprint($rotatedJwk),
    ];

    $this->actingAs($user)
        ->withSession(withPasswordConfirmed())
        ->postJson(route('api.identity.public-key.store'), $rotatedPayload)
        ->assertSuccessful()
        ->assertJsonPath('browser_db_id', $originalBrowserDbId);

    $identityKey = UserIdentityKey::query()->where('user_id', $user->id)->first();

    expect($identityKey)->not->toBeNull()
        ->and($identityKey->public_key_jwk['x'])->toBe('rotated-x')
        ->and($identityKey->fingerprint)->toBe($rotatedPayload['fingerprint'])
        ->and($identityKey->browser_db_id)->toBe($originalBrowserDbId);
});

it('requires recent password confirmation when overwriting an existing public key', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertSuccessful();

    $rotatedJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'rotated-x',
        'y' => 'rotated-y',
    ];

    $rotatedPayload = [
        'public_key_jwk' => $rotatedJwk,
        'fingerprint' => publicKeyJwkFingerprint($rotatedJwk),
    ];

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), $rotatedPayload)
        ->assertStatus(423)
        ->assertJson([
            'message' => 'Password confirmation required.',
        ]);

    expect(UserIdentityKey::query()->where('user_id', $user->id)->firstOrFail()->public_key_jwk['x'])
        ->toBe('test-public-x');
});

it('allows overwriting an existing public key when password was recently confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertSuccessful();

    $rotatedJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'confirmed-rotated-x',
        'y' => 'confirmed-rotated-y',
    ];

    $rotatedPayload = [
        'public_key_jwk' => $rotatedJwk,
        'fingerprint' => publicKeyJwkFingerprint($rotatedJwk),
    ];

    $this->actingAs($user)
        ->withSession(withPasswordConfirmed())
        ->postJson(route('api.identity.public-key.store'), $rotatedPayload)
        ->assertSuccessful();

    expect(UserIdentityKey::query()->where('user_id', $user->id)->firstOrFail()->public_key_jwk['x'])
        ->toBe('confirmed-rotated-x');
});

it('rejects an identity key overwrite when password confirmation fails', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertSuccessful();

    $rotatedJwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'blocked-rotated-x',
        'y' => 'blocked-rotated-y',
    ];

    $rotatedPayload = [
        'public_key_jwk' => $rotatedJwk,
        'fingerprint' => publicKeyJwkFingerprint($rotatedJwk),
    ];

    $this->actingAs($user)
        ->from(route('password.confirm'))
        ->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('password.confirm'))
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), $rotatedPayload)
        ->assertStatus(423)
        ->assertJson([
            'message' => 'Password confirmation required.',
        ]);

    expect(UserIdentityKey::query()->where('user_id', $user->id)->firstOrFail()->public_key_jwk['x'])
        ->toBe('test-public-x');
});

it('exposes a partner public key to authenticated users', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $payload = validPublicKeyPayload();

    UserIdentityKey::query()->create([
        'user_id' => $owner->id,
        ...$payload,
    ]);

    $this->actingAs($partner)
        ->getJson(route('api.users.public-key.show', $owner))
        ->assertSuccessful()
        ->assertJsonPath('public_id', $owner->public_key)
        ->assertJsonPath('fingerprint', $payload['fingerprint']);
});

it('returns not found when a partner has not registered a public key', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();

    $this->actingAs($partner)
        ->getJson(route('api.users.public-key.show', $owner))
        ->assertNotFound();
});

it('rejects invalid public key payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), [
            'public_key_jwk' => [
                'kty' => 'RSA',
                'crv' => 'P-256',
                'x' => 'bad',
                'y' => 'bad',
            ],
            'fingerprint' => 'not-a-valid-fingerprint',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['public_key_jwk.kty', 'fingerprint']);
});

it('rejects a fingerprint that does not match the public key', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), [
            'public_key_jwk' => validPublicKeyPayload()['public_key_jwk'],
            'fingerprint' => str_repeat('b', 64),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fingerprint');

    expect(UserIdentityKey::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('reports whether the current user has registered a key', function () {
    $user = User::factory()->create();
    $payload = validPublicKeyPayload();

    $this->actingAs($user)
        ->getJson(route('api.identity.public-key.mine'))
        ->assertSuccessful()
        ->assertJson(['registered' => false]);

    UserIdentityKey::query()->create([
        'user_id' => $user->id,
        ...$payload,
    ]);

    $identityKey = UserIdentityKey::query()->where('user_id', $user->id)->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('api.identity.public-key.mine'))
        ->assertSuccessful()
        ->assertJsonPath('registered', true)
        ->assertJsonPath('fingerprint', $payload['fingerprint'])
        ->assertJsonPath('browser_db_id', $identityKey->browser_db_id);
});

it('rejects private key material in the public key payload', function () {
    $user = User::factory()->create();
    $payload = validPublicKeyPayload();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), [
            'public_key_jwk' => [
                ...$payload['public_key_jwk'],
                'd' => 'private-key-material-must-not-be-stored',
            ],
            'fingerprint' => $payload['fingerprint'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('public_key_jwk.d');

    expect(UserIdentityKey::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('requires authentication for identity key endpoints', function () {
    $owner = User::factory()->create();

    $this->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertUnauthorized();

    $this->getJson(route('api.identity.public-key.mine'))
        ->assertUnauthorized();

    $this->getJson(route('api.users.public-key.show', $owner))
        ->assertUnauthorized();
});

it('never persists private key fields from rejected payloads', function () {
    $user = User::factory()->create();
    $payload = validPublicKeyPayload();

    $this->actingAs($user)
        ->postJson(route('api.identity.public-key.store'), [
            'public_key_jwk' => [
                ...$payload['public_key_jwk'],
                'd' => 'must-not-persist',
            ],
            'fingerprint' => str_repeat('c', 64),
        ])
        ->assertUnprocessable();

    expect(UserIdentityKey::query()->count())->toBe(0);
});
