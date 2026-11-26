<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Enums\AlertStatus;

class UrgentAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'message' => $this->message,
            'status' => $this->status instanceof AlertStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof AlertStatus ? $this->status->label() : ucfirst((string) $this->status),
            'student' => $this->student
                ? AnonymousStudentResource::make($this->student)
                : null,
            'handled_by' => $this->handler?->name,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
