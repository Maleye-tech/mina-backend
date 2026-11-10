<?php

namespace Modules\Assessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitClinicalCaseStepAnswerRequest extends FormRequest
{
    /**
     * Longueur maximale d'une réponse libre à une étape.
     */
    private const MAX_ANSWER_LENGTH = 5000;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'step_id' => ['required', 'integer'],
            'answer' => ['required', 'string', 'max:'.self::MAX_ANSWER_LENGTH],
        ];
    }
}
