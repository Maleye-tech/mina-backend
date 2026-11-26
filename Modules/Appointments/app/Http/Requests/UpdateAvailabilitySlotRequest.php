<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Appointments\Enums\SlotMode;

class UpdateAvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['sometimes', 'required', 'date', 'after:now'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'mode' => ['sometimes', 'required', Rule::enum(SlotMode::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after' => 'Le créneau doit commencer dans le futur.',
            'ends_at.required' => 'L’heure de fin est requise.',
        ];
    }
}
