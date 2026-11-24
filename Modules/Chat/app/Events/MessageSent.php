<?php

namespace Modules\Chat\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Chat\Models\Message;

class MessageSent implements ShouldBroadcastNow
{
    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_pseudonym' => $this->message->sender?->pseudonym ?: 'Participant',
            'body' => $this->message->body,
            'is_crisis' => $this->message->is_crisis,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
