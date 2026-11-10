<?php

namespace Modules\Assessment\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Assessment\Enums\AttemptStatus;
use Modules\Assessment\Events\ClinicalCaseAnswered;
use Modules\Assessment\Events\ClinicalCaseCompleted;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\ClinicalCaseAttempt;
use Modules\Assessment\Models\ClinicalCaseStep;

class ClinicalCaseService
{
    /**
     * Démarrer une tentative de cas clinique.
     *
     * Les étapes sont fixes : aucune photo n'est nécessaire, les réponses
     * sont créées au fil de la progression de l'étudiante ou de l'étudiant.
     */
    public function startAttempt(User $user, ClinicalCase $clinicalCase): ClinicalCaseAttempt
    {
        $attempt = ClinicalCaseAttempt::create([
            'user_id' => $user->id,
            'clinical_case_id' => $clinicalCase->id,
        ]);

        return $this->loadForDisplay($attempt);
    }

    /**
     * Historique paginé des tentatives d'un utilisateur, sans le détail des
     * étapes. Les tentatives les plus récentes apparaissent en premier.
     */
    public function userAttempts(User $user, int $perPage): LengthAwarePaginator
    {
        return ClinicalCaseAttempt::query()
            ->where('user_id', $user->id)
            ->with(['clinicalCase:id,title,difficulty,duration_minutes'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Soumettre la réponse à une étape du cas clinique.
     *
     * Le parcours est linéaire : seule la prochaine étape non répondue peut
     * l'être. Une réponse déjà soumise est immuable ; le corrigé d'une étape
     * n'est révélé qu'après sa réponse (ou à la fin de la tentative).
     *
     * @throws ValidationException L'étape ne fait pas partie du cas, la réponse est déjà soumise, ou l'ordre du parcours n'est pas respecté.
     */
    public function submitStepAnswer(User $user, ClinicalCaseAttempt $attempt, int $stepId, string $answer): ClinicalCaseAttempt
    {
        [$step, $stepAnswer] = DB::transaction(function () use ($attempt, $stepId, $answer): array {
            $lockedAttempt = ClinicalCaseAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== AttemptStatus::InProgress) {
                abort(409, 'Cette tentative de cas clinique est terminée. Vous ne pouvez plus soumettre de réponses.');
            }

            /** @var EloquentCollection<int, ClinicalCaseStep> $steps */
            $steps = ClinicalCaseStep::query()
                ->where('clinical_case_id', $lockedAttempt->clinical_case_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $step = $steps->firstWhere('id', $stepId);

            if ($step === null) {
                throw ValidationException::withMessages([
                    'step_id' => ['Cette étape ne fait pas partie du cas clinique.'],
                ]);
            }

            $answeredStepIds = $lockedAttempt->stepAnswers()->pluck('clinical_case_step_id');

            if ($answeredStepIds->contains($stepId)) {
                throw ValidationException::withMessages([
                    'step_id' => ['Cette réponse a déjà été soumise et ne peut plus être modifiée.'],
                ]);
            }

            $nextStep = $steps->first(
                fn (ClinicalCaseStep $candidate): bool => ! $answeredStepIds->contains($candidate->id),
            );

            if ($nextStep === null || $nextStep->id !== $stepId) {
                throw ValidationException::withMessages([
                    'step_id' => ['Répondez d’abord aux étapes précédentes avant celle-ci.'],
                ]);
            }

            $stepAnswer = $lockedAttempt->stepAnswers()->create([
                'clinical_case_step_id' => $stepId,
                'answer' => $answer,
            ]);

            return [$step, $stepAnswer];
        });

        $this->loadForDisplay($attempt);

        ClinicalCaseAnswered::dispatch($user, $attempt, $step, $stepAnswer);

        return $attempt;
    }

    /**
     * Terminer la tentative.
     *
     * Les corrigés de toutes les étapes — y compris celles sans réponse —
     * sont alors révélés.
     */
    public function finish(User $user, ClinicalCaseAttempt $attempt): ClinicalCaseAttempt
    {
        $completed = DB::transaction(function () use ($attempt): ClinicalCaseAttempt {
            $lockedAttempt = ClinicalCaseAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status !== AttemptStatus::InProgress) {
                abort(409, 'Cette tentative de cas clinique est déjà terminée.');
            }

            $lockedAttempt->fill([
                'status' => AttemptStatus::Completed,
                'completed_at' => now(),
            ])->save();

            return $lockedAttempt;
        });

        $this->loadForDisplay($completed);

        ClinicalCaseCompleted::dispatch($user, $completed);

        return $completed;
    }

    /**
     * Charger les relations d'affichage d'une tentative.
     *
     * Les étapes et leurs réponses portent les corrigés : la ressource ne
     * les sérialise que pour les étapes déjà répondues, ou une fois la
     * tentative terminée.
     */
    public function loadForDisplay(ClinicalCaseAttempt $attempt): ClinicalCaseAttempt
    {
        $attempt->loadMissing([
            'clinicalCase:id,title,presentation,difficulty,duration_minutes,learning_goals,subject_id,level_id',
            'clinicalCase.subject:id,name,slug',
            'clinicalCase.level:id,name,slug',
            'clinicalCase.steps',
            'stepAnswers',
        ]);

        return $attempt;
    }
}
