<?php

namespace Modules\Assessment\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Assessment\Models\ClinicalCaseAttempt;
use Modules\Assessment\Models\ClinicalCaseStep;
use Modules\Assessment\Models\ClinicalCaseStepAnswer;

/**
 * La réponse d'une étape de cas clinique vient d'être soumise.
 *
 * Consommé par le module Progress pour agréger l'activité par matière
 * (la tentative porte le cas clinique, donc sa matière et son niveau ;
 * l'étape porte la question et la réponse de l'étudiante ou de l'étudiant).
 */
class ClinicalCaseAnswered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly ClinicalCaseAttempt $attempt,
        public readonly ClinicalCaseStep $step,
        public readonly ClinicalCaseStepAnswer $stepAnswer,
    ) {}
}
