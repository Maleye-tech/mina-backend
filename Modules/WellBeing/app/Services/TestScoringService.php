<?php

namespace Modules\WellBeing\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\WellBeing\Enums\AssessmentTestType;
use Modules\WellBeing\Models\AssessmentQuestion;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Models\UserTestResult;

/**
 * Calcul du score d'un test d'auto-évaluation et génération du retour
 * immédiat, strictement non médical.
 */
class TestScoringService
{
    /**
     * Avertissement obligatoire renvoyé avec chaque résultat.
     */
    public const DISCLAIMER = "Ce n'est pas un diagnostic médical.";

    /**
     * Enregistrer les réponses d'un utilisateur et calculer son score.
     *
     * Toutes les questions du test doivent être répondues exactement une
     * fois. Les points sont lus dans la colonne JSON `options` côté serveur :
     * le client ne peut donc pas manipuler son score.
     *
     * @param  array<int, array{question_id: int|string, option_key: string}>  $answers
     *
     * @throws ValidationException
     */
    public function submit(User $user, AssessmentTest $test, array $answers): UserTestResult
    {
        /** @var Collection<int, AssessmentQuestion> $questions */
        $questions = $test->questions()->get()->keyBy('id');

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'answers' => ['Ce test ne contient aucune question.'],
            ]);
        }

        $answersByQuestion = collect($answers)->keyBy(
            fn (array $answer): int => (int) $answer['question_id'],
        );

        if ($answersByQuestion->count() !== count($answers)) {
            throw ValidationException::withMessages([
                'answers' => ['Une question ne peut être répondue qu’une seule fois.'],
            ]);
        }

        $unknownQuestionIds = $answersByQuestion->keys()->diff($questions->keys());

        if ($unknownQuestionIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => ['Une des questions ne fait pas partie de ce test.'],
            ]);
        }

        $missingQuestionIds = $questions->keys()->diff($answersByQuestion->keys());

        if ($missingQuestionIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => ['Toutes les questions du test doivent être répondues.'],
            ]);
        }

        $score = 0;
        $maxScore = 0;
        $normalizedAnswers = [];

        foreach ($questions as $question) {
            $options = collect($question->options ?? []);
            $maxScore += max(0, (int) $options->max(fn (array $option): int => (int) ($option['points'] ?? 0)));

            $answer = $answersByQuestion->get($question->id);
            $selectedOption = $options->firstWhere('key', $answer['option_key']);

            if (! is_array($selectedOption)) {
                throw ValidationException::withMessages([
                    'answers' => ['Une des options sélectionnées est invalide.'],
                ]);
            }

            $points = max(0, (int) ($selectedOption['points'] ?? 0));
            $score += $points;

            $normalizedAnswers[] = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'option_key' => (string) $selectedOption['key'],
                'option_label' => (string) $selectedOption['label'],
                'points' => $points,
            ];
        }

        $percentage = $this->percentage($score, $maxScore);
        $feedback = $this->feedbackFor($test->type, $score, $maxScore);

        return DB::transaction(fn (): UserTestResult => UserTestResult::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'score' => $score,
            'response_data' => [
                'answers' => $normalizedAnswers,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'level' => $feedback['level'],
            ],
            'feedback_given' => $feedback['message'],
        ])->load('test'));
    }

    /**
     * Retour immédiat correspondant au pourcentage de score obtenu.
     *
     * @return array{level: string, message: string}
     */
    public function feedbackFor(AssessmentTestType $type, int $score, int $maxScore): array
    {
        $percentage = $this->percentage($score, $maxScore);
        $ranges = config("wellbeing.scoring.{$type->value}", []);

        foreach ($ranges as $range) {
            if ($percentage <= (int) $range['max_percentage']) {
                return [
                    'level' => (string) $range['level'],
                    'message' => (string) $range['feedback'],
                ];
            }
        }

        return [
            'level' => 'unknown',
            'message' => 'Votre résultat a bien été enregistré. Prenez un moment pour observer ce que vous ressentez et parlez-en à une personne de confiance si besoin.',
        ];
    }

    /**
     * Pourcentage arrondi du score maximal possible.
     */
    public function percentage(int $score, int $maxScore): int
    {
        if ($maxScore <= 0) {
            return 0;
        }

        return (int) round(($score / $maxScore) * 100);
    }
}
