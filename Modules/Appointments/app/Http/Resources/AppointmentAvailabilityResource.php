<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Models\AppointmentAvailability;

/**
 * @mixin AppointmentAvailability
 */
class AppointmentAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => $this->weekday,
            'start_time' => $this->start_time instanceof \DateTimeInterface ? $this->start_time->format('H:i') : substr((string) $this->start_time, 0, 5),
            'end_time' => $this->end_time instanceof \DateTimeInterface ? $this->end_time->format('H:i') : substr((string) $this->end_time, 0, 5),
            'is_active' => $this->is_active,
        ];
    }
}
