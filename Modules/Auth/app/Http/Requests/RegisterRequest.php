<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['sometimes', 'string', 'in:student,ambassador,professional,partner'],
            'pseudonym' => ['nullable', 'string', 'max:255'],
            'partner_institution_id' => ['nullable', 'exists:partner_institutions,id'],
            'partner_position' => ['nullable', 'string', 'max:255'],
            'professional_title' => ['nullable', 'string', 'max:255'],
            'university_id' => ['nullable', 'exists:universities,id'],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'study_field' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:255'],
        ];
    }
}
