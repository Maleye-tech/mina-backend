<?php

namespace Modules\Assessment\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Assessment\Models\ClinicalCaseAttempt;

/**
 * Une tentative de cas clinique vient d'être terminée.
 *
 * Consommé par le module Progress pour agréger la complétion des cas
 * cliniques par matière (la tentative porte le cas, donc sa matière et son
 * niveau, ainsi que le statut et la date de complétion).
 */
class ClinicalCaseCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly ClinicalCaseAttempt $attempt,
    ) {}
}
