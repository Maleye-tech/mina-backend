<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Auth\Enums\UserRole;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isProfessional = $user && $this->professional && $this->professional->user_id === $user->id;
        $isStudent = $user && $this->student_id === $user->id;
        $isOwner = $isProfessional || $isStudent || $user?->hasRole(UserRole::Admin);
        $canAccessMeeting = in_array($this->status, [AppointmentStatus::Confirmed, AppointmentStatus::Completed], true);
        $meetingUrl = $canAccessMeeting ? ($this->meeting_url ?: $this->meeting_link) : null;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'consultation_type' => $this->consultation_type,
            'meeting_link' => $meetingUrl,
            'meeting_url' => $meetingUrl,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'student_notes' => $isStudent ? $this->student_notes : null,
            'confidential_notes' => $isProfessional && $canAccessMeeting ? $this->confidential_notes : null,
            'professional' => $this->whenLoaded('professional', fn () => $this->professional ? [
                'id' => $this->professional->id,
                'display_name' => $this->professional->display_name,
                'name' => $isOwner ? $this->professional->user?->name : null,
                'specialty' => $this->professional->specialty?->value,
                'title' => $this->professional->title,
            ] : null),
            'student' => $this->whenLoaded('student', function () use ($isProfessional, $isOwner): ?array {
                if ($this->student === null) {
                    return null;
                }

                $student = [
                    'id' => $isProfessional
                        ? (new AnonymousStudentResource($this->student))->resolve()['id']
                        : ($isOwner ? $this->student->id : null),
                    'pseudonym' => $this->student->pseudonym ?: 'Étudiant',
                ];

                if ($isOwner && ! $isProfessional) {
                    $student['name'] = $this->student->name;
                }

                return $student;
            }),
            'slot' => $this->whenLoaded('slot', fn () => $this->slot ? [
                'id' => $this->slot->id,
                'starts_at' => $this->slot->starts_at?->toIso8601String(),
                'ends_at' => $this->slot->ends_at?->toIso8601String(),
                'is_booked' => $this->slot->is_booked,
            ] : null),
        ];
    }
}
