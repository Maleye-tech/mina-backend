<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Appointments\Models\Alert;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status,
            'subject' => $this->subject_type && $this->subject_id
                ? ['type' => $this->subject_type, 'id' => $this->subject_id]
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
