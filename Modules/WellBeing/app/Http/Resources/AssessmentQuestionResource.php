<?php

namespace Modules\WellBeing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\WellBeing\Models\AssessmentQuestion;

/**
 * Question exposée au client : les points restent strictement côté serveur.
 *
 * @mixin AssessmentQuestion
 */
class AssessmentQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'options' => collect($this->options ?? [])
                ->map(fn (array $option): array => [
                    'key' => (string) $option['key'],
                    'label' => (string) $option['label'],
                ])
                ->values()
                ->all(),
        ];
    }
}
