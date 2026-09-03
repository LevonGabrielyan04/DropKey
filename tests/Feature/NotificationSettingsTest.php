<?php

use App\Models\User;

test('notification settings page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee(__('Notifications'), false)
        ->assertSee(__('Enable notifications'), false)
        ->assertSee(__('Message alerts'), false)
        ->assertSee(__('Install the app for notifications'), false)
        ->assertSee(__('On iPhone and iPad, open Share, then Add to Home Screen. After you install :app, return here to enable push notifications.', [
            'app' => config('app.name'),
        ]), false)
        ->assertSee(route('api.push.vapid-public-key'), false)
        ->assertSee(route('api.push-subscriptions.store'), false);
});

test('notification settings require authentication', function () {
    $this->get(route('notifications.edit'))
        ->assertRedirect();
});

test('notification settings require a verified email', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get(route('notifications.edit'))
        ->assertRedirect();
});
