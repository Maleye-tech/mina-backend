<?php

namespace Modules\Assessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Les options doivent appartenir à la question soumise.
     *
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', Rule::exists('assessment_questions', 'id')],
            'option_ids' => ['required', 'array', 'min:1', 'distinct'],
            'option_ids.*' => [
                'integer',
                Rule::exists('assessment_question_options', 'id')
                    ->where('question_id', $this->integer('question_id')),
            ],
        ];
    }
}
