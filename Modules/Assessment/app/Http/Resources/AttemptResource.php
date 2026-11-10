<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\Attempt;

/**
 * Session de QCM : résumé, et état des questions si les réponses sont chargées.
 *
 * @mixin Attempt
 */
class AttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'score' => $this->score !== null ? (float) $this->score : null,
            'correct_count' => $this->correct_count,
            'total_questions' => $this->total_questions,
            'time_spent_seconds' => $this->time_spent_seconds,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'slug' => $this->subject->slug,
            ] : null),
            'level' => $this->whenLoaded('level', fn () => $this->level ? [
                'id' => $this->level->id,
                'name' => $this->level->name,
                'slug' => $this->level->slug,
            ] : null),
            'questions' => $this->whenLoaded(
                'answers',
                fn () => AttemptQuestionResource::collection($this->answers),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
