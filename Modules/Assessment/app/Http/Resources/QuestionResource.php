<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\Question;

/**
 * Question publiée, sans jamais exposer les bonnes réponses ni l'explication.
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'question_type' => $this->question_type->value,
            'difficulty' => $this->difficulty->value,
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
            'options' => $this->whenLoaded(
                'options',
                fn () => QuestionOptionResource::collection($this->options),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
