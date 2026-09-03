<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TimePeriod;
use App\Models\Conversation;
use App\Models\User;

class ChatShowData
{
    public TimePeriod $autoDelete {
        get => $this->calculateAutoDelete();
    }

    /**
     * @var list<TimePeriod>
     */
    public array $timePeriods {
        get => $this->calculateTimePeriods();
    }

    private function __construct(
        public readonly User $recipient,
        public readonly ?Conversation $conversation,
    ) {}

    /**
     * Build view data for the chat settings page.
     */
    public static function from(User $recipient, ?Conversation $conversation): self
    {
        return new self($recipient, $conversation);
    }

    /**
     * Convert the DTO into an array suitable for the chat settings view.
     *
     * @return array{recipient: User, conversation: Conversation|null, autoDelete: TimePeriod, timePeriods: list<TimePeriod>}
     */
    public function toArray(): array
    {
        return [
            'recipient' => $this->recipient,
            'conversation' => $this->conversation,
            'autoDelete' => $this->autoDelete,
            'timePeriods' => $this->timePeriods,
        ];
    }

    private function calculateAutoDelete(): TimePeriod
    {
        if ($this->conversation === null) {
            return TimePeriod::SEVEN_DAYS;
        }

        return $this->conversation->auto_delete;
    }

    /**
     * @return list<TimePeriod>
     */
    private function calculateTimePeriods(): array
    {
        return TimePeriod::cases();
    }
}
