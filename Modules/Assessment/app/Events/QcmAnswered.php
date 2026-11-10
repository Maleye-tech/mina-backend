<?php

namespace Modules\Assessment\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Models\AttemptAnswer;

/**
 * Une réponse de QCM vient d'être soumise et corrigée.
 *
 * Consommé par le module Progress pour agréger les résultats par matière
 * (l'attempt porte subject_id/level_id, la réponse porte question_id/is_correct).
 */
class QcmAnswered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Attempt $attempt,
        public readonly AttemptAnswer $answer,
    ) {}
}
