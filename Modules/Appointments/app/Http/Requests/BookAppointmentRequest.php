<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'professional_id' => ['required', 'exists:appointment_professionals,id'],
            'slot_id' => ['required', 'exists:appointment_slots,id'],
            'consultation_type' => ['nullable', 'string', 'in:video,in_person,chat'],
            'student_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
