<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConsultationRequest
 */
class ConsultationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'professional_id' => $this->professional_id,
            'topic' => $this->topic,
            'priority' => $this->priority,
            'status' => $this->status,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'reason' => $this->reason,
            'student' => $this->whenLoaded('student', fn () => $this->student
                ? AnonymousStudentResource::make($this->student)
                : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
