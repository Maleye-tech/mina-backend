<?php

namespace Modules\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Chat\Models\Conversation;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $participant = $this->participant_one_id === $request->user()?->id
            ? $this->participantTwo
            : $this->participantOne;

        return [
            'id' => $this->id,
            'participant' => [
                'id' => $participant->id,
                'name' => $participant->name,
                'pseudonym' => $participant->pseudonym,
                'role' => $participant->role->value,
                'role_label' => $participant->role->getLabel(),
            ],
            'last_message' => MessageResource::make($this->whenLoaded('lastMessage')),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($this->unread_count ?? $this->unreadCountFor($request->user())),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
