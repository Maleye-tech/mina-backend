<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeclineAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
