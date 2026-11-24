<?php

namespace Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Events\Models\Event;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'description' => $this->description,
            'presenter_name' => $this->presenter_name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'location_or_link' => $this->location_or_link,
            'max_participants' => $this->max_participants,
            'current_participants' => $this->current_participants,
            'is_full' => $this->isFull(),
            'replay_url' => $this->ends_at->isPast() ? $this->replay_url : null,
            'is_registered' => $user ? $this->isUserRegistered($user) : false,
        ];
    }
}
