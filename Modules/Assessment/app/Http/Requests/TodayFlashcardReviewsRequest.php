<?php

namespace Modules\Assessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TodayFlashcardReviewsRequest extends FormRequest
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
            'deck_id' => ['nullable', 'integer', Rule::exists('assessment_flashcard_decks', 'id')],
            'new_limit' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
