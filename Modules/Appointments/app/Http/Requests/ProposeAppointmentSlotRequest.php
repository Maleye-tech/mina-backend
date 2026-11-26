<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\UserRole;

class ProposeAppointmentSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Professional) ?? false;
    }

    public function rules(): array
    {
        return [
            'availability_slot_id' => ['required', 'integer', 'exists:availability_slots,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability_slot_id.required' => 'Le choix d’un nouveau créneau est obligatoire.',
            'availability_slot_id.exists' => 'Ce créneau n’existe pas.',
        ];
    }
}
