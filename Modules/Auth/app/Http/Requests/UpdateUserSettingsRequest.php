<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pseudonym' => [
                'sometimes',
                'nullable',
                'string',
                'min:3',
                'max:30',
                'regex:/^[\pL\pN_.-]+$/u',
                Rule::unique('users', 'pseudonym')->ignore($this->user()?->getKey()),
            ],
            'preferred_language' => ['sometimes', 'required', Rule::in(['fr', 'en'])],
            'notifications_enabled' => ['sometimes', 'required', 'boolean'],
            'consents' => ['sometimes', 'required', 'array:personal_data,mental_health_data'],
            'consents.personal_data' => ['sometimes', 'required', 'boolean'],
            'consents.mental_health_data' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
