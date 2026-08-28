<?php

use App\Enums\TimePeriod;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('assigns a unique uuid v4 public key when creating a user', function () {
    $user = User::factory()->create();

    expect($user->public_key)
        ->toBeString()
        ->and(Str::isUuid($user->public_key, version: 4))->toBeTrue();
});

it('stores user public keys as binary uuids in the database', function () {
    $user = User::factory()->create();

    $rawPublicKey = DB::table('users')
        ->where('id', $user->id)
        ->value('public_key');

    expect($rawPublicKey)
        ->toBeString()
        ->and(strlen($rawPublicKey))->toBe(16)
        ->and(BinaryCodec::decode($rawPublicKey, 'uuid'))->toBe($user->public_key);
});

it('assigns unique uuid identifiers when creating a conversation', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $conversation = createConversation($alice, $bob);

    expect($conversation->id)->toBeString()
        ->and(Str::isUuid($conversation->id, version: 7))->toBeTrue()
        ->and($conversation->public_key)->toBeString()
        ->and(Str::isUuid($conversation->public_key, version: 4))->toBeTrue();
});

it('stores conversation public keys as binary uuids in the database', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $rawPublicKey = DB::table('conversations')
        ->where('id', $conversation->id)
        ->value('public_key');

    expect($rawPublicKey)
        ->toBeString()
        ->and(strlen($rawPublicKey))->toBe(16)
        ->and(BinaryCodec::decode($rawPublicKey, 'uuid'))->toBe($conversation->public_key);
});

it('resolves conversation route model bindings by public key', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    expect($conversation->getRouteKeyName())->toBe('public_key')
        ->and($conversation->getRouteKey())->toBe($conversation->public_key);

    $resolved = (new Conversation)->resolveRouteBinding($conversation->public_key);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($conversation))->toBeTrue();
});

it('does not resolve conversation route model bindings by numeric id', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    expect((new Conversation)->resolveRouteBinding((string) $conversation->id))->toBeNull();
});

it('resolves user route model bindings by public key', function () {
    $user = User::factory()->create();

    expect($user->getRouteKeyName())->toBe('public_key')
        ->and($user->getRouteKey())->toBe($user->public_key);

    $resolved = (new User)->resolveRouteBinding($user->public_key);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($user))->toBeTrue();
});

it('does not resolve user route model bindings by numeric id', function () {
    $user = User::factory()->create();

    expect((new User)->resolveRouteBinding((string) $user->id))->toBeNull();
});

it('enforces unique public keys across users', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->make();

    $secondUser->public_key = $firstUser->public_key;

    expect(fn () => $secondUser->save())->toThrow(UniqueConstraintViolationException::class);
});

it('enforces unique public keys across conversations', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();
    $firstConversation = createConversation($alice, $bob);

    $secondConversation = new Conversation([
        'user_one_id' => min($alice->id, $carol->id),
        'user_two_id' => max($alice->id, $carol->id),
        'auto_delete' => TimePeriod::SEVEN_DAYS->value,
    ]);
    $secondConversation->public_key = $firstConversation->public_key;

    expect(fn () => $secondConversation->save())->toThrow(UniqueConstraintViolationException::class);
});
