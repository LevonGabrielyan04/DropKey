<?php

use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a canonical conversation for two users', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $repository = app(ChatMessageRepositoryInterface::class);

    $conversation = $repository->findOrCreateConversation($alice, $bob);

    expect($conversation->id)->toBeString()
        ->and(Str::isUuid($conversation->id))->toBeTrue()
        ->and($conversation->user_one_id)->toBe(min($alice->id, $bob->id))
        ->and($conversation->user_two_id)->toBe(max($alice->id, $bob->id));
});

it('returns the same conversation regardless of user order', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $repository = app(ChatMessageRepositoryInterface::class);

    $first = $repository->findOrCreateConversation($alice, $bob);
    $second = $repository->findOrCreateConversation($bob, $alice);

    expect($first->id)->toBe($second->id);
});

it('finds an existing conversation between two users', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $repository = app(ChatMessageRepositoryInterface::class);
    $conversation = $repository->findOrCreateConversation($alice, $bob);

    expect($repository->findConversationBetweenUsers($bob, $alice)?->is($conversation))->toBeTrue();
});

it('returns null when looking up a self conversation', function () {
    $user = User::factory()->create();
    $repository = app(ChatMessageRepositoryInterface::class);

    expect($repository->findConversationBetweenUsers($user, $user))->toBeNull();
});

it('rejects self conversations', function () {
    $user = User::factory()->create();
    $repository = app(ChatMessageRepositoryInterface::class);

    $repository->findOrCreateConversation($user, $user);
})->throws(InvalidArgumentException::class);

it('polls only messages after the provided public id cursor', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);
    $repository = app(ChatMessageRepositoryInterface::class);

    $first = $repository->createMessage($conversation, $alice, fakeChatPayload());
    $second = $repository->createMessage($conversation, $bob, fakeChatPayload());

    $messages = $repository->getMessagesForConversation($conversation, $first->public_id);

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->public_id)->toBe($second->public_id);
});

it('counts unviewed messages sent to the user across conversations', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();
    $repository = app(ChatMessageRepositoryInterface::class);

    $withBob = createConversation($alice, $bob);
    $withCarol = createConversation($alice, $carol);

    $repository->createMessage($withBob, $bob, fakeChatPayload());
    $viewed = $repository->createMessage($withBob, $bob, fakeChatPayload());
    $viewed->forceFill(['is_viewed' => true])->save();
    $repository->createMessage($withCarol, $carol, fakeChatPayload());
    $repository->createMessage($withCarol, $alice, fakeChatPayload());

    expect($repository->countUnreadMessagesFor($alice))->toBe(2)
        ->and($repository->countUnreadMessagesFor($bob))->toBe(0);
});
