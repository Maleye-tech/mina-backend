<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Models\AppointmentProfessional;

/**
 * @mixin AppointmentProfessional
 */
class AppointmentProfessionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'display_name' => $this->display_name,
            'name' => $this->user?->name,
            'specialty' => $this->specialty?->value,
            'specialty_label' => $this->specialty?->getLabel(),
            'languages' => $this->languages,
            'bio' => $this->bio,
            'photo_path' => $this->photo_path,
            'phone' => $this->phone,
            'consultation_duration_minutes' => $this->consultation_duration_minutes,
            'is_available' => $this->is_available,
            'is_verified' => $this->is_verified,
            'charter_accepted_at' => $this->charter_accepted_at?->toIso8601String(),
            'can_open_slots' => $this->canOpenSlots(),
        ];
    }
}
