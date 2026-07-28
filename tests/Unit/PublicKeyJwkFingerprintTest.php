<?php

use App\Support\Cryptography\PublicKeyJwkFingerprint;

it('computes a stable fingerprint from a public JWK', function () {
    $jwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'abc',
        'y' => 'def',
    ];

    $first = PublicKeyJwkFingerprint::fromPublicKeyJwk($jwk);
    $second = PublicKeyJwkFingerprint::fromPublicKeyJwk($jwk);

    expect($first)->toBe($second)
        ->and($first)->toMatch('/^[a-f0-9]{64}$/');
});

it('changes the fingerprint when the public key changes', function () {
    $base = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'abc',
        'y' => 'def',
    ];

    $original = PublicKeyJwkFingerprint::fromPublicKeyJwk($base);
    $rotated = PublicKeyJwkFingerprint::fromPublicKeyJwk([...$base, 'x' => 'xyz']);

    expect($original)->not->toBe($rotated);
});

it('matches the browser client canonical JSON ordering', function () {
    $jwk = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'test-public-x',
        'y' => 'test-public-y',
    ];

    expect(PublicKeyJwkFingerprint::fromPublicKeyJwk($jwk))
        ->toBe('64e1c338ebecfb1d765129309f5242bee6ad1d0b93246643e639a70d3ec90f4e');
});
