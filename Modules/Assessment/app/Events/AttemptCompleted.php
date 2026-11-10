<?php

namespace Modules\Assessment\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Assessment\Models\Attempt;

/**
 * Une session de QCM vient d'être terminée et scorée.
 *
 * Consommé par le module Progress pour agréger les taux de réussite par matière
 * (l'attempt porte subject_id/level_id, score, correct_count, total_questions).
 */
class AttemptCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Attempt $attempt,
    ) {}
}
