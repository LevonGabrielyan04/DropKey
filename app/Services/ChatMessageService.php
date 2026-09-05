<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Events\ChatMessagesViewedBroadcast;
use App\Events\ChatUnreadCountBroadcast;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Services\Interfaces\ChatMessageServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChatMessageService implements ChatMessageServiceInterface
{
    public function __construct(protected ChatMessageRepositoryInterface $chatMessages) {}

    /**
     * {@inheritDoc}
     */
    public function getMessagesForUsers(User $user, User $otherUser, ?string $afterPublicId = null): Collection
    {
        if ($user->is($otherUser)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot fetch messages with yourself.'),
            ]);
        }

        $conversation = $this->chatMessages->findConversationBetweenUsers($user, $otherUser);

        if ($conversation === null) {
            return new Collection;
        }

        $this->authorizeConversation($conversation);

        /** @var list<string> $viewedPublicIds */
        $viewedPublicIds = [];

        $messages = DB::transaction(function () use ($conversation, $otherUser, $afterPublicId, &$viewedPublicIds) {
            $viewedPublicIds = $this->chatMessages->markMessagesAsViewed($conversation, $otherUser);

            return $this->chatMessages->getMessagesForConversation($conversation, $afterPublicId);
        });

        if ($viewedPublicIds !== []) {
            broadcast(new ChatMessagesViewedBroadcast($conversation, $viewedPublicIds));
            $this->broadcastViewerUnreadCount($user, $conversation);
        }

        return $messages;
    }

    public function storeMessage(User $sender, User $recipient, string $payload): ChatMessage
    {
        if ($sender->is($recipient)) {
            throw ValidationException::withMessages([
                'recipient_id' => __('You cannot send a message to yourself.'),
            ]);
        }

        $conversation = $this->authorizeConversation(
            $this->chatMessages->findOrCreateConversation($sender, $recipient),
        );

        $message = $this->chatMessages->createMessage($conversation, $sender, $payload);

        ChatMessageSent::dispatch(
            $message->load(['conversation', 'sender:id,public_key']),
            $sender,
            $recipient,
        );

        return $message;
    }

    public function markMessageAsViewed(User $viewer, ChatMessage $message): void
    {
        $message->loadMissing('conversation');

        Gate::forUser($viewer)->authorize('markChatMessageAsViewed', $message);

        $viewedPublicId = $this->chatMessages->markMessageAsViewed($message);

        if ($viewedPublicId !== null) {
            broadcast(new ChatMessagesViewedBroadcast($message->conversation, [$viewedPublicId]));
            $this->broadcastViewerUnreadCount($viewer, $message->conversation);
        }
    }

    private function broadcastViewerUnreadCount(User $viewer, Conversation $conversation): void
    {
        $unreadMessagesCount = $conversation
            ->messages()
            ->where('sender_id', '!=', $viewer->id)
            ->where('is_viewed', false)
            ->count();

        broadcast(new ChatUnreadCountBroadcast(
            $viewer,
            $conversation,
            $unreadMessagesCount,
            $this->chatMessages->countUnreadMessagesFor($viewer),
        ));
    }

    private function authorizeConversation(Conversation $conversation): Conversation
    {
        Gate::authorize('view', $conversation);

        return $conversation;
    }
}
