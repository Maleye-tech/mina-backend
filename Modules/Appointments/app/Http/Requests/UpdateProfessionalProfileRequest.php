<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Appointments\Enums\ProfessionalSpecialty;

class UpdateProfessionalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'specialty' => ['required', Rule::enum(ProfessionalSpecialty::class)],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'photo_path' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'display_name.required' => 'Le nom d’affichage est obligatoire.',
            'specialty.required' => 'Veuillez sélectionner une spécialité.',
            'languages.required' => 'Au moins une langue de consultation doit être renseignée.',
        ];
    }
}
