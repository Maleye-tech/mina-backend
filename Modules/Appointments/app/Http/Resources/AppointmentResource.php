<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Models\Appointment;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isProfessional = $user && $this->professional && $this->professional->user_id === $user->id;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'consultation_type' => $this->consultation_type,
            'meeting_link' => $this->meeting_link,
            'cancellation_reason' => $this->cancellation_reason,
            'student_notes' => $this->student_notes,
            'confidential_notes' => $isProfessional ? $this->confidential_notes : null,
            'professional' => $this->whenLoaded('professional', fn () => $this->professional ? [
                'id' => $this->professional->id,
                'name' => $this->professional->user?->name,
                'title' => $this->professional->title,
            ] : null),
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'pseudonym' => $this->student->pseudonym ?: 'Étudiant',
                'name' => $isProfessional ? $this->student->name : null,
            ] : null),
        ];
    }
}
