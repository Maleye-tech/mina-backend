<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Enums\ProfessionalSpecialty;

class ProfessionalProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'profile_id' => $this->id,
            'user_id' => $this->user_id,
            'display_name' => $this->display_name,
            'specialty' => $this->specialty instanceof ProfessionalSpecialty
                ? $this->specialty->value
                : $this->specialty,
            'specialty_label' => $this->specialty instanceof ProfessionalSpecialty
                ? $this->specialty->label()
                : 'Psychologue clinicien',
            'languages' => $this->languages ?: ['Français'],
            'bio' => $this->bio,
            'photo_path' => $this->photo_path,
            'is_verified' => (bool) $this->is_verified,
            'has_accepted_charter' => $this->charter_accepted_at !== null,
            'charter_accepted_at' => $this->charter_accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
