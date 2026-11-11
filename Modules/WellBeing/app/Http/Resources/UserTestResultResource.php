<?php

namespace Modules\WellBeing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\WellBeing\Models\UserTestResult;
use Modules\WellBeing\Services\TestScoringService;

/**
 * Résultat d'un test, déchiffré pour son propriétaire ou un administrateur.
 *
 * @mixin UserTestResult
 */
class UserTestResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $responseData = $this->response_data ?? [];

        return [
            'id' => $this->id,
            'score' => $this->score,
            'max_score' => $responseData['max_score'] ?? null,
            'percentage' => $responseData['percentage'] ?? null,
            'level' => $responseData['level'] ?? null,
            'feedback' => $this->feedback_given,
            'disclaimer' => TestScoringService::DISCLAIMER,
            'answers' => $responseData['answers'] ?? [],
            'test' => $this->whenLoaded('test', fn (): array => [
                'id' => $this->test->id,
                'title' => $this->test->title,
                'type' => $this->test->type->value,
                'type_label' => $this->test->type->getLabel(),
            ]),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
