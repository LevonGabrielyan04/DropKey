<?php

use App\Enums\TimePeriod;
use App\Models\Conversation;
use App\Models\User;

it('requires authentication to update conversation auto delete', function () {
    $bob = User::factory()->create();

    $this->patchJson(route('conversations.auto-delete.update', $bob), [
        'auto_delete' => TimePeriod::ONE_DAY->value,
    ])->assertUnauthorized();
});

it('updates auto delete for an existing conversation', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $this->actingAs($alice)
        ->patchJson(route('conversations.auto-delete.update', $bob), [
            'auto_delete' => TimePeriod::ONE_HOUR->value,
        ])
        ->assertSuccessful()
        ->assertJson([
            'auto_delete' => TimePeriod::ONE_HOUR->value,
        ]);

    expect($conversation->fresh()->auto_delete)->toBe(TimePeriod::ONE_HOUR);
});

it('creates a conversation when updating auto delete before the first message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    expect(Conversation::query()->count())->toBe(0);

    $this->actingAs($alice)
        ->patchJson(route('conversations.auto-delete.update', $bob), [
            'auto_delete' => TimePeriod::THIRTY_DAYS->value,
        ])
        ->assertSuccessful()
        ->assertJson([
            'auto_delete' => TimePeriod::THIRTY_DAYS->value,
        ]);

    $conversation = Conversation::query()->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->auto_delete)->toBe(TimePeriod::THIRTY_DAYS);
});

it('rejects invalid auto delete values', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->patchJson(route('conversations.auto-delete.update', $bob), [
            'auto_delete' => 'forever',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['auto_delete']);
});

it('rejects auto delete updates when targeting yourself', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice)
        ->patchJson(route('conversations.auto-delete.update', $alice), [
            'auto_delete' => TimePeriod::ONE_DAY->value,
        ])
        ->assertNotFound();
});

it('renders the auto delete dropdown on the chat settings page', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    Conversation::query()
        ->whereKey($conversation->id)
        ->update(['auto_delete' => TimePeriod::ONE_DAY->value]);

    $this->actingAs($alice)
        ->get(route('chat.settings', $bob))
        ->assertSuccessful()
        ->assertSee('id="auto-delete"', false)
        ->assertSee('data-auto-delete="'.TimePeriod::ONE_DAY->value.'"', false)
        ->assertSee(route('conversations.auto-delete.update', $bob), false)
        ->assertSee(__('Auto-delete messages after'));
});
