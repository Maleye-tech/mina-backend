<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\QuestionOption;

/**
 * Option de réponse, sans jamais exposer la colonne is_correct.
 *
 * @mixin QuestionOption
 */
class QuestionOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
        ];
    }
}
