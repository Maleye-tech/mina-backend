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
            'name' => $this->user?->name,
            'title' => $this->title,
            'bio' => $this->bio,
            'consultation_duration_minutes' => $this->consultation_duration_minutes,
            'is_available' => $this->is_available,
        ];
    }
}
