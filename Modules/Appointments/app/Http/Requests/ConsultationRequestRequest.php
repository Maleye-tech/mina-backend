<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'professional_id' => ['required', 'integer', 'exists:appointment_professionals,id'],
            'topic' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
