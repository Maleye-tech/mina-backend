<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\ClinicalCase;

/**
 * Cas clinique publié, sans jamais exposer le contenu des étapes ni les
 * corrigés : ceux-ci ne sont révélés qu'au fil d'une tentative.
 *
 * @mixin ClinicalCase
 */
class ClinicalCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'presentation' => $this->presentation,
            'difficulty' => $this->difficulty->value,
            'duration_minutes' => $this->duration_minutes,
            'learning_goals' => $this->learning_goals,
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
            'steps_count' => $this->whenCounted('steps'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
