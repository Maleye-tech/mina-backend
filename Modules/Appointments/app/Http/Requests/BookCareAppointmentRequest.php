<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookCareAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'availability_slot_id' => ['required', 'integer', 'exists:availability_slots,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability_slot_id.required' => 'Le choix d’un créneau horaire est obligatoire.',
            'availability_slot_id.exists' => 'Ce créneau n’existe pas.',
            'reason.max' => 'Le motif ne doit pas dépasser 255 caractères.',
        ];
    }
}
