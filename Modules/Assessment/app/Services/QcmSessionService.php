<?php

namespace Modules\Assessment\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Assessment\Enums\AttemptStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Enums\QuestionType;
use Modules\Assessment\Events\AttemptCompleted;
use Modules\Assessment\Events\QcmAnswered;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Models\AttemptAnswer;
use Modules\Assessment\Models\Question;

class QcmSessionService
{
    /**
     * Nombre de questions tirées par défaut au démarrage d'une session.
     */
    private const DEFAULT_QUESTION_COUNT = 10;

    public function __construct(private readonly QcmGradingService $grading) {}

    /**
     * Démarrer une session de QCM.
     *
     * Tire au hasard des questions publiées correspondant aux critères,
     * crée la tentative et son plan figé de réponses vides, puis renvoie la
     * tentative chargée de ses relations d'affichage.
     *
     * @param  array{subject_id?: int|null, level_id?: int|null, difficulty?: string|Difficulty|null, question_count?: int|null}  $attributes
     *
     * @throws ValidationException Aucune question publiée ne correspond aux critères.
     */
    public function startSession(User $user, array $attributes): Attempt
    {
        $questionCount = (int) ($attributes['question_count'] ?? self::DEFAULT_QUESTION_COUNT);

        // La difficulté peut arriver sous forme de chaîne validée par la requête
        // ou d'instance d'enum : on normalise pour filtrer la requête.
        $difficulty = $attributes['difficulty'] ?? null;

        if (is_string($difficulty)) {
            $difficulty = Difficulty::tryFrom($difficulty);
        }

        $questionIds = Question::query()
            ->published()
            ->when(
                $attributes['subject_id'] ?? null,
                fn (Builder $query, int $subjectId): Builder => $query->where('subject_id', $subjectId),
            )
            ->when(
                $attributes['level_id'] ?? null,
                fn (Builder $query, int $levelId): Builder => $query->where('level_id', $levelId),
            )
            ->when(
                $difficulty,
                fn (Builder $query, Difficulty $difficulty): Builder => $query->where('difficulty', $difficulty->value),
            )
            ->inRandomOrder()
            ->limit($questionCount)
            ->pluck('id');

        if ($questionIds->isEmpty()) {
            throw ValidationException::withMessages([
                'question_count' => ['Aucune question publiée ne correspond à ces critères.'],
            ]);
        }

        $attempt = DB::transaction(function () use ($user, $attributes, $questionIds): Attempt {
            $attempt = Attempt::create([
                'user_id' => $user->id,
                'subject_id' => $attributes['subject_id'] ?? null,
                'level_id' => $attributes['level_id'] ?? null,
                'total_questions' => $questionIds->count(),
            ]);

            $attempt->answers()->createMany(
                $questionIds
                    ->map(fn (int $questionId): array => ['question_id' => $questionId])
                    ->all()
            );

            return $attempt;
        });

        return $this->loadForDisplay($attempt);
    }

    /**
     * Historique paginé des sessions d'un utilisateur, sans le détail des réponses.
     *
     * Les sessions les plus récentes apparaissent en premier.
     */
    public function userAttempts(User $user, int $perPage): LengthAwarePaginator
    {
        return Attempt::query()
            ->forUser($user)
            ->with(['subject:id,name,slug', 'level:id,name,slug'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Soumettre la réponse à une question de la session.
     *
     * Une réponse déjà soumise est immuable ; la correction et l'explication
     * ne sont révélées qu'après la soumission.
     *
     * @param  array<int, int|string>  $optionIds
     *
     * @throws ValidationException La question ne fait pas partie de la session, la réponse est déjà soumise, ou plusieurs options sont interdites.
     */
    public function submitAnswer(User $user, Attempt $attempt, int $questionId, array $optionIds): AttemptAnswer
    {
        $selectedOptionIds = array_values(array_map('intval', $optionIds));

        $answer = DB::transaction(function () use ($attempt, $questionId, $selectedOptionIds): AttemptAnswer {
            $lockedAttempt = Attempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== AttemptStatus::InProgress) {
                abort(409, 'Cette session est terminée. Vous ne pouvez plus soumettre de réponses.');
            }

            /** @var AttemptAnswer|null $answer */
            $answer = $lockedAttempt->answers()
                ->where('question_id', $questionId)
                ->lockForUpdate()
                ->first();

            if ($answer === null) {
                throw ValidationException::withMessages([
                    'question_id' => ['Cette question ne fait pas partie de la session.'],
                ]);
            }

            if ($answer->answered_at !== null) {
                throw ValidationException::withMessages([
                    'question_id' => ['Cette réponse a déjà été soumise et ne peut plus être modifiée.'],
                ]);
            }

            $question = Question::query()->with('options')->findOrFail($questionId);

            if ($question->question_type === QuestionType::Single && count($selectedOptionIds) > 1) {
                throw ValidationException::withMessages([
                    'option_ids' => ['Une seule option est attendue pour cette question à réponse unique.'],
                ]);
            }

            $answer->fill([
                'selected_option_ids' => $selectedOptionIds,
                'is_correct' => $this->grading->gradeAnswer($question, $selectedOptionIds),
                'answered_at' => now(),
            ])->save();

            // La question est déjà chargée avec ses options : on la réutilise
            // pour éviter une requête supplémentaire après la transaction.
            $answer->setRelation('question', $question);

            return $answer;
        });

        $answer->loadMissing([
            'question.options',
            'question.subject:id,name,slug',
            'question.level:id,name,slug',
        ]);

        QcmAnswered::dispatch($user, $attempt, $answer);

        return $answer;
    }

    /**
     * Terminer la session et calculer le score.
     *
     * Les questions sans réponse sont comptées comme incorrectes. Le score est
     * le pourcentage de bonnes réponses, arrondi à deux décimales.
     */
    public function finish(User $user, Attempt $attempt): Attempt
    {
        $completed = DB::transaction(function () use ($attempt): Attempt {
            $lockedAttempt = Attempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== AttemptStatus::InProgress) {
                abort(409, 'Cette session est déjà terminée.');
            }

            $correctCount = (int) $lockedAttempt->answers()->where('is_correct', true)->count();

            $lockedAttempt->fill([
                'status' => AttemptStatus::Completed,
                'score' => $this->grading->calculateScore($correctCount, $lockedAttempt->total_questions),
                'correct_count' => $correctCount,
                'time_spent_seconds' => (int) $lockedAttempt->created_at->diffInSeconds(now()),
                'completed_at' => now(),
            ])->save();

            return $lockedAttempt;
        });

        $this->loadForDisplay($completed);

        AttemptCompleted::dispatch($user, $completed);

        return $completed;
    }

    /**
     * Charger les relations d'affichage d'une session.
     *
     * Les options sont chargées avec leur colonne is_correct pour calculer les
     * identifiants de bonnes réponses côté resource ; elles ne sont jamais
     * sérialisées telles quelles vers les étudiantes et étudiants.
     */
    public function loadForDisplay(Attempt $attempt): Attempt
    {
        $attempt->loadMissing([
            'subject:id,name,slug',
            'level:id,name,slug',
            'answers.question.options',
            'answers.question.subject:id,name,slug',
            'answers.question.level:id,name,slug',
        ]);

        return $attempt;
    }
}
