<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\FlashcardState;

/**
 * État SM-2 d'une carte mémoire pour un utilisateur donné.
 *
 * @mixin FlashcardState
 */
class FlashcardStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'easiness_factor' => (float) $this->easiness_factor,
            'interval_days' => $this->interval_days,
            'repetitions' => $this->repetitions,
            'lapses' => $this->lapses,
            'last_quality' => $this->last_quality,
            'due_at' => $this->due_at?->toIso8601String(),
            'last_reviewed_at' => $this->last_reviewed_at?->toIso8601String(),
        ];
    }
}
