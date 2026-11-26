<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\ProfessionalSpecialty;

class CareAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $professionalProfile = $this->professionalProfile
            ?? $this->professionalUser?->professionalCareProfile;
        $professionalUserId = $this->professional_user_id ?? $this->professionalUser?->id;
        $status = $this->status instanceof AppointmentStatus
            ? $this->status->value
            : $this->status;

        return [
            'id' => $this->id,
            'status' => $status,
            'status_label' => $this->status instanceof AppointmentStatus
                ? $this->status->label()
                : ucfirst((string) $status),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes ?: 45,
            'consultation_type' => $this->consultation_type ?: 'visio',
            'reason' => $this->reason ?: $this->student_notes,
            'meeting_url' => in_array($status, ['confirmed', 'completed'], true)
                ? ($this->meeting_url ?: $this->meeting_link)
                : null,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'student' => $this->student
                ? AnonymousStudentResource::make($this->student)
                : null,
            'professional' => [
                'id' => $professionalUserId,
                'display_name' => $professionalProfile?->display_name ?? $this->professionalUser?->name ?? 'Professionnel Mina Care',
                'specialty' => $professionalProfile?->specialty instanceof ProfessionalSpecialty
                    ? $professionalProfile->specialty->value
                    : ($professionalProfile?->specialty ?? 'psychologue'),
                'specialty_label' => $professionalProfile?->specialty instanceof ProfessionalSpecialty
                    ? $professionalProfile->specialty->label()
                    : 'Psychologue clinicien',
                'photo_path' => $professionalProfile?->photo_path,
            ],
            'slot' => $this->availabilitySlot
                ? AvailabilitySlotResource::make($this->availabilitySlot)
                : null,
            'has_private_note' => $this->sessionNote()->exists(),
            'can_chat' => in_array($status, ['confirmed', 'completed'], true),
            'can_send_messages' => $status === 'confirmed',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
