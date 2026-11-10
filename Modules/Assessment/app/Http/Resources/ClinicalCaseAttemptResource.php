<?php

namespace Modules\Assessment\Http\Resources;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Assessment\Enums\AttemptStatus;
use Modules\Assessment\Models\ClinicalCaseAttempt;
use Modules\Assessment\Models\ClinicalCaseStep;
use Modules\Assessment\Models\ClinicalCaseStepAnswer;

/**
 * Tentative de cas clinique avec révélation progressive.
 *
 * En cours de tentative, seules les étapes déjà répondues (avec leur corrigé)
 * et l'étape courante sont exposées ; les étapes suivantes sont masquées et
 * comptées dans remaining_steps. Une fois la tentative terminée, toutes les
 * étapes et tous les corrigés sont révélés, y compris ceux des étapes sans
 * réponse.
 *
 * @mixin ClinicalCaseAttempt
 */
class ClinicalCaseAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stepsLoaded = $this->stepsAreLoaded();

        $visibleSteps = $stepsLoaded
            ? $this->visibleSteps()->values()
            : collect();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'clinical_case' => $this->whenLoaded(
                'clinicalCase',
                fn () => ClinicalCaseResource::make($this->clinicalCase),
            ),
            'total_steps' => $this->when(
                $stepsLoaded,
                fn () => $this->clinicalCase->steps->count(),
            ),
            'steps' => $this->when($stepsLoaded, fn () => $visibleSteps->all()),
            'remaining_steps' => $this->when(
                $stepsLoaded,
                fn () => $this->clinicalCase->steps->count() - $visibleSteps->count(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Les étapes ne sont sérialisées que si le cas, ses étapes et les
     * réponses de la tentative sont tous chargés (historique léger sinon).
     */
    private function stepsAreLoaded(): bool
    {
        return $this->relationLoaded('clinicalCase')
            && $this->clinicalCase->relationLoaded('steps')
            && $this->relationLoaded('stepAnswers');
    }

    /**
     * Étapes visibles : les étapes répondues, l'étape courante, et toutes
     * les étapes une fois la tentative terminée.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleSteps(): Collection
    {
        /** @var EloquentCollection<int, ClinicalCaseStepAnswer> $answers */
        $answers = $this->stepAnswers->keyBy('clinical_case_step_id');

        /** @var EloquentCollection<int, ClinicalCaseStep> $steps */
        $steps = $this->clinicalCase->steps;

        $completed = $this->status === AttemptStatus::Completed;

        return $steps
            ->when(! $completed, fn (EloquentCollection $collection): EloquentCollection => $collection->take($answers->count() + 1))
            ->map(fn (ClinicalCaseStep $step): array => $this->stepPayload($step, $answers, $completed))
            ->values();
    }

    /**
     * @param  EloquentCollection<int, ClinicalCaseStepAnswer>  $answers
     * @return array<string, mixed>
     */
    private function stepPayload(ClinicalCaseStep $step, EloquentCollection $answers, bool $completed): array
    {
        $answer = $answers->get($step->id);

        return [
            'id' => $step->id,
            'sort_order' => $step->sort_order,
            'prompt' => $step->prompt,
            'question' => $step->question,
            'answer' => $answer?->answer,
            'expected_answer' => $completed || $answer !== null
                ? $step->expected_answer
                : null,
            'answered' => $answer !== null,
            'answered_at' => $answer?->created_at?->toIso8601String(),
        ];
    }
}
