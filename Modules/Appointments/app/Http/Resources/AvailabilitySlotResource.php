<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilitySlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'professional_id' => $this->professional_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'mode' => is_string($this->mode) ? $this->mode : $this->mode?->value,
            'mode_label' => is_object($this->mode) && method_exists($this->mode, 'label')
                ? $this->mode->label()
                : ucfirst((string) $this->mode),
            'is_booked' => (bool) $this->is_booked,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
