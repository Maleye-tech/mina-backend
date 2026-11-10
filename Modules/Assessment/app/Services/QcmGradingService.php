<?php

namespace Modules\Assessment\Services;

use Modules\Assessment\Models\Question;
use Modules\Assessment\Models\QuestionOption;

class QcmGradingService
{
    /**
     * Corriger une réponse à une question.
     *
     * Une réponse est correcte si et seulement si l'ensemble des options
     * sélectionnées correspond exactement à l'ensemble des bonnes options,
     * indépendamment de l'ordre (tout-ou-rien par question).
     *
     * @param  array<int, int|string>  $selectedOptionIds
     */
    public function gradeAnswer(Question $question, array $selectedOptionIds): bool
    {
        if (! $question->relationLoaded('options')) {
            $question->loadMissing('options');
        }

        $correctOptionIds = $question->options
            ->filter(fn (QuestionOption $option): bool => $option->is_correct)
            ->pluck('id')
            ->sort()
            ->values()
            ->map(fn (int $id): int => (int) $id)
            ->all();

        $selectedIds = collect($selectedOptionIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $selectedIds === $correctOptionIds;
    }

    /**
     * Calculer le score d'une session : pourcentage de bonnes réponses.
     */
    public function calculateScore(int $correctCount, int $totalQuestions): float
    {
        if ($totalQuestions <= 0) {
            return 0.0;
        }

        return round(($correctCount / $totalQuestions) * 100, 2);
    }
}
