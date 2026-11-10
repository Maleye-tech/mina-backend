<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\UserRole;

class UpdateOnboardingQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,65535'],
        ];
    }
}
