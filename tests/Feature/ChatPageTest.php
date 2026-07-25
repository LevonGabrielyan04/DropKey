<?php

use App\Enums\TimePeriod;
use App\Models\ChatMessage;
use App\Models\User;

it('requires authentication to view the chat inbox', function () {
    $this->get(route('chat.index'))
        ->assertRedirect(route('login'));
});

it('renders the chat inbox with the start conversation form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chat.index'))
        ->assertSuccessful()
        ->assertSee('data-chat-open-url', false)
        ->assertSee('data-conversations-url', false)
        ->assertSee('data-local-user-public-id="'.$user->public_key.'"', false)
        ->assertSee('data-initial-conversations', false)
        ->assertSee('e2eeChatInbox', false)
        ->assertSee(__('Start a conversation'))
        ->assertSee(__('Recipient user name'))
        ->assertSee(__('Open channel'));
});

it('renders the encrypted chat session shell for a recipient', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->get(route('chat.show', $bob))
        ->assertSuccessful()
        ->assertSee('x-data="e2eeChatSession"', false)
        ->assertSee('data-recipient-id="'.$bob->id.'"', false)
        ->assertSee('data-local-user-id="'.$alice->id.'"', false)
        ->assertSee('data-local-user-public-id="'.$alice->public_key.'"', false)
        ->assertSee(route('api.users.public-key.show', $bob), false)
        ->assertSee(route('messages.index', $bob), false)
        ->assertSee(route('messages.store'), false)
        ->assertSee(route('api.uploads.store'), false)
        ->assertSee('data-upload-max-file-bytes', false)
        ->assertSee('data-message-viewed-url-template', false)
        ->assertSee(route('messages.viewed', ['message' => '__PUBLIC_ID__']), false)
        ->assertSee('data-conversation-public-key=""', false)
        ->assertDontSee('data-poll-interval-ms', false)
        ->assertSee($bob->name)
        ->assertSee(__('Send encrypted message'))
        ->assertSee(__('Attach file'))
        ->assertSee('openFilePicker', false)
        ->assertSee('selectedFileName', false)
        ->assertSee('message.attachment', false)
        ->assertSee(':disabled="!canSendMessage"', false)
        ->assertSee('data-decryption-failed-message', false)
        ->assertSee(__('Unable to decrypt this message.'), false)
        ->assertSee('id="auto-delete"', false)
        ->assertSee('data-auto-delete="'.TimePeriod::SEVEN_DAYS->value.'"', false)
        ->assertSee('message.decryptionError', false)
        ->assertSee('message.isMine', false)
        ->assertSee('x-show="message.isMine"', false)
        ->assertSee('message.isViewed', false)
        ->assertSee('formatMessageTime(message.createdAt)', false)
        ->assertSee(':datetime="message.createdAt"', false)
        ->assertDontSee('${partnerFingerprint}', false);
});

it('exposes the conversation public key when a chat already exists', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $this->actingAs($alice)
        ->get(route('chat.show', $bob))
        ->assertSuccessful()
        ->assertSee('data-conversation-public-key="'.$conversation->public_key.'"', false);
});

it('includes the messages navigation link in the sidebar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee(route('chat.index'), false)
        ->assertSee(__('Messages'));
});

it('lists existing conversations on the inbox page', function () {
    $alice = User::factory()->create(['name' => 'Alice Chat']);
    $bob = User::factory()->create(['name' => 'Bob Chat']);
    $conversation = createConversation($alice, $bob);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $this->actingAs($alice)
        ->get(route('chat.index'))
        ->assertSuccessful()
        ->assertSee('Bob Chat')
        ->assertSee($bob->public_key, false);
});

it('shows the unread message count for conversations on the inbox page', function () {
    $alice = User::factory()->create(['name' => 'Alice Chat']);
    $bob = User::factory()->create(['name' => 'Bob Chat']);
    $conversation = createConversation($alice, $bob);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'payload' => fakeChatPayload(),
    ]);
    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'payload' => fakeChatPayload(),
    ]);
    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $this->actingAs($alice)
        ->get(route('chat.index'))
        ->assertSuccessful()
        ->assertSee('Bob Chat')
        ->assertSee('"public_key":"'.$conversation->public_key.'"', false)
        ->assertSee('"unread_messages_count":2', false);
});

it('hides the unread message count when a conversation has no unread messages', function () {
    $alice = User::factory()->create(['name' => 'Alice Chat']);
    $bob = User::factory()->create(['name' => 'Bob Chat']);
    $conversation = createConversation($alice, $bob);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'payload' => fakeChatPayload(),
    ])->forceFill(['is_viewed' => true])->save();

    $this->actingAs($alice)
        ->get(route('chat.index'))
        ->assertSuccessful()
        ->assertSee('Bob Chat')
        ->assertSee('"public_key":"'.$conversation->public_key.'"', false)
        ->assertSee('"unread_messages_count":0', false);
});

it('resolves chat routes by public id instead of name or numeric id', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->get('/chat/'.$bob->public_key)
        ->assertSuccessful()
        ->assertSee($bob->name);

    $this->actingAs($alice)
        ->get('/chat/'.$bob->name)
        ->assertNotFound();

    $this->actingAs($alice)
        ->get('/chat/'.$bob->id)
        ->assertNotFound();
});

it('redirects name-based chat opens to the public id url', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->get(route('chat.open', $bob->name))
        ->assertRedirect(route('chat.show', $bob));
});

it('shows a notifications tip when the user has no push subscription', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)
        ->get(route('chat.index'))
        ->assertSuccessful()
        ->assertSee(__('Enable notifications so you know when a new message arrives.'))
        ->assertSee(route('notifications.edit'), false)
        ->assertSee(__('Notification settings'));

    $this->actingAs($alice)
        ->get(route('chat.show', $bob))
        ->assertSuccessful()
        ->assertSee(__('Enable notifications so you know when a new encrypted message arrives.'))
        ->assertSee(route('notifications.edit'), false);
});

it('hides the notifications tip when the user has a push subscription', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $alice->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/chat-tip-endpoint',
        'p256dh-test-key',
        'auth-test-token',
        'aes128gcm',
    );

    $this->actingAs($alice)
        ->get(route('chat.index'))
        ->assertSuccessful()
        ->assertDontSee(__('Enable notifications so you know when a new message arrives.'));

    $this->actingAs($alice)
        ->get(route('chat.show', $bob))
        ->assertSuccessful()
        ->assertDontSee(__('Enable notifications so you know when a new encrypted message arrives.'));
});
