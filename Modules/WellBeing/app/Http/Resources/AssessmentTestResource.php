<?php

namespace Modules\WellBeing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Services\TestScoringService;

/**
 * @mixin AssessmentTest
 */
class AssessmentTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'questions_count' => $this->whenCounted('questions'),
            'questions' => AssessmentQuestionResource::collection($this->whenLoaded('questions')),
            'disclaimer' => TestScoringService::DISCLAIMER,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
