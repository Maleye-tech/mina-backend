<?php

namespace Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Events\Models\EventRegistration;

/**
 * @mixin EventRegistration
 */
class EventRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'registered_at' => $this->registered_at->toIso8601String(),
            'event' => EventResource::make($this->whenLoaded('event')),
        ];
    }
}
