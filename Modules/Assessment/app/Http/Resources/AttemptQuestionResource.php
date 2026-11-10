<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\AttemptAnswer;

/**
 * État d'une question dans une session en cours.
 *
 * La correction (bonne réponse, explication) n'est révélée qu'une fois la
 * réponse soumise ; elle reste absente tant que la question n'est pas répondue.
 *
 * @mixin AttemptAnswer
 */
class AttemptQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answered = $this->answered_at !== null;

        return [
            'question' => QuestionResource::make($this->question),
            'answered' => $answered,
            'selected_option_ids' => $this->when($answered, fn () => $this->selected_option_ids ?? []),
            'is_correct' => $this->when($answered, fn () => (bool) $this->is_correct),
            'correct_option_ids' => $this->when($answered, fn () => $this->correctOptionIds()),
            'explanation' => $this->when($answered, fn () => $this->question?->explanation),
        ];
    }
}
