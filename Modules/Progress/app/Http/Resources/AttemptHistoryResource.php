<?php

namespace Modules\Progress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\Attempt;

/**
 * Tentative complétée : résumé pour l'historique de progression.
 *
 * @mixin Attempt
 */
class AttemptHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score !== null ? (float) $this->score : null,
            'correct_count' => $this->correct_count,
            'total_questions' => $this->total_questions,
            'time_spent_seconds' => $this->time_spent_seconds,
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'slug' => $this->subject->slug,
            ]),
            'level' => $this->whenLoaded('level', fn () => $this->level ? [
                'id' => $this->level->id,
                'name' => $this->level->name,
                'slug' => $this->level->slug,
            ] : null),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
