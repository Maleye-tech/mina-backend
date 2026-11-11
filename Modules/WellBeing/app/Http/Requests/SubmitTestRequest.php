<?php

namespace Modules\WellBeing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTestRequest extends FormRequest
{
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
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.option_key' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Vous devez répondre aux questions du test.',
            'answers.min' => 'Vous devez répondre aux questions du test.',
            'answers.*.question_id.distinct' => 'Une question ne peut être répondue qu’une seule fois.',
            'answers.*.option_key.required' => 'Chaque réponse doit contenir une option.',
        ];
    }
}
