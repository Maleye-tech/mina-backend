<?php

namespace Modules\Progress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\AttemptAnswer;

/**
 * Question peu maîtrisée : nombre d'échecs et contexte de la question.
 *
 * @mixin AttemptAnswer
 */
class WeakPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $question = $this->whenLoaded('question');

        return [
            'question_id' => $this->question_id,
            'failure_count' => (int) $this->failure_count,
            'last_failed_at' => $this->last_failed_at?->toIso8601String(),
            'question' => $question ? [
                'id' => $question->id,
                'text' => $question->question,
                'difficulty' => $question->difficulty?->value,
                'subject' => $question->subject ? [
                    'id' => $question->subject->id,
                    'name' => $question->subject->name,
                    'slug' => $question->subject->slug,
                ] : null,
            ] : null,
        ];
    }
}
