<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessment\Models\Flashcard;

/**
 * Carte mémoire : recto, verso et indice, avec l'état SM-2 de l'utilisateur
 * courant quand il est chargé (null pour une carte jamais révisée).
 *
 * @mixin Flashcard
 */
class FlashcardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'front' => $this->front,
            'back' => $this->back,
            'hint' => $this->hint,
            'deck' => $this->whenLoaded('deck', fn () => $this->deck ? [
                'id' => $this->deck->id,
                'name' => $this->deck->name,
            ] : null),
            'state' => $this->whenLoaded('states', function () {
                $state = $this->states->first();

                return $state !== null ? FlashcardStateResource::make($state) : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
