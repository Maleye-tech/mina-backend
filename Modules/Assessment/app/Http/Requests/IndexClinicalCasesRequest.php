<?php

namespace Modules\Assessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assessment\Enums\Difficulty;

class IndexClinicalCasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'level_id' => ['nullable', 'integer', Rule::exists('levels', 'id')],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
