<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChatMessageSent;
use App\Notifications\NewChatMessageNotification;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRecipientOfNewChatMessage implements ShouldQueue
{
    public function __construct(protected ChatMessageRepositoryInterface $chatMessages) {}

    public function handle(ChatMessageSent $event): void
    {
        if (! $event->recipient->pushSubscriptions()->exists()) {
            return;
        }

        $event->recipient->notify(new NewChatMessageNotification(
            $event->sender,
            $this->chatMessages->countUnreadMessagesFor($event->recipient),
        ));
    }
}
