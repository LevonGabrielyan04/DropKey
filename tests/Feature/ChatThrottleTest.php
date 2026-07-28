<?php

use App\Models\User;

beforeEach(function () {
    $this->travel(61)->seconds();
});

it('throttles chat message writes after thirty per minute', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($sender)
            ->postJson(route('messages.store'), [
                'recipient_id' => $recipient->id,
                'payload' => fakeChatPayload(),
            ])
            ->assertCreated();
    }

    $this->actingAs($sender)
        ->postJson(route('messages.store'), [
            'recipient_id' => $recipient->id,
            'payload' => fakeChatPayload(),
        ])
        ->assertTooManyRequests();
});

it('throttles public key registration after ten per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->withSession(withPasswordConfirmed())
            ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
            ->assertSuccessful();
    }

    $this->actingAs($user)
        ->withSession(withPasswordConfirmed())
        ->postJson(route('api.identity.public-key.store'), validPublicKeyPayload())
        ->assertTooManyRequests();
});
