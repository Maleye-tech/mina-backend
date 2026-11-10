<?php

namespace Modules\Assessment\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Models\FlashcardReview;

/**
 * Une carte mémoire vient d'être révisée et notée.
 *
 * Consommé par le module Progress pour agréger l'assiduité des révisions
 * (la carte porte son deck, donc sa matière et son niveau ; la révision
 * porte la qualité, le facteur de facilité, l'intervalle et la prochaine échéance).
 */
class FlashcardReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Flashcard $flashcard,
        public readonly FlashcardReview $review,
    ) {}
}
