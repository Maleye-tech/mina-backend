<?php

namespace Modules\Assessment\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\Enums\ReviewQuality;
use Modules\Assessment\Events\FlashcardReviewed;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Models\FlashcardReview;
use Modules\Assessment\Models\FlashcardState;

class FlashcardReviewService
{
    public function __construct(private readonly Sm2Service $sm2) {}

    /**
     * Cartes à réviser aujourd'hui pour un utilisateur.
     *
     * Les cartes dues (échéance SM-2 atteinte ou dépassée) arrivent en premier,
     * de la plus en retardée à la plus récente ; puis les cartes nouvelles
     * (jamais révisées par cet utilisateur), dans l'ordre des paquets.
     *
     * @param  int  $newLimit  Nombre maximal de cartes nouvelles (0 pour les exclure).
     * @return Collection<int, Flashcard>
     */
    public function cardsDueToday(User $user, ?int $deckId, int $newLimit): Collection
    {
        $todayEnd = now()->endOfDay();

        $dueStates = FlashcardState::query()
            ->where('user_id', $user->id)
            ->where('due_at', '<=', $todayEnd)
            ->whereHas('flashcard.deck', fn (Builder $deck): Builder => $this->scopeToPublishedDeck($deck, $deckId))
            ->with('flashcard.deck:id,name')
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $dueCards = $dueStates->map(function (FlashcardState $state): Flashcard {
            $card = $state->flashcard;

            // L'état unique de l'utilisateur est posé comme relation chargée
            // pour éviter toute requête supplémentaire à la sérialisation.
            return $card->setRelation('states', collect([$state]));
        });

        if ($newLimit < 1) {
            return $dueCards;
        }

        $newCards = Flashcard::query()
            ->whereHas('deck', fn (Builder $deck): Builder => $this->scopeToPublishedDeck($deck, $deckId))
            ->whereDoesntHave('states', fn (Builder $state): Builder => $state->where('user_id', $user->id))
            ->with('deck:id,name')
            ->orderBy('deck_id')
            ->orderBy('sort_order')
            ->limit($newLimit)
            ->get();

        // Une carte nouvelle n'a aucun état pour cet utilisateur : la relation
        // est posée vide pour que la ressource expose explicitement `state: null`.
        $newCards->each(fn (Flashcard $card) => $card->setRelation('states', collect()));

        return $dueCards->concat($newCards);
    }

    /**
     * Enregistrer une révision et recalculer l'état SM-2 de la carte.
     *
     * L'état de la carte est créé à la première révision ; l'historique des
     * révisions est alimenté à chaque notation.
     *
     * @return array{state: FlashcardState, review: FlashcardReview}
     */
    public function review(User $user, Flashcard $flashcard, ReviewQuality $quality): array
    {
        $stateId = $this->firstOrCreateState($user, $flashcard)->id;

        [$state, $review] = DB::transaction(function () use ($user, $flashcard, $quality, $stateId): array {
            /** @var FlashcardState $lockedState */
            $lockedState = FlashcardState::query()
                ->whereKey($stateId)
                ->lockForUpdate()
                ->firstOrFail();

            $attributes = $this->sm2->calculate($lockedState, $quality);

            $lockedState->fill($attributes)->save();

            $review = FlashcardReview::create([
                'user_id' => $user->id,
                'flashcard_id' => $flashcard->id,
                'quality' => $quality->value,
                'easiness_factor' => $attributes['easiness_factor'],
                'interval_days' => $attributes['interval_days'],
                'due_at' => $attributes['due_at'],
            ]);

            return [$lockedState, $review];
        });

        FlashcardReviewed::dispatch($user, $flashcard, $review);

        return ['state' => $state, 'review' => $review];
    }

    /**
     * Restreindre une requête aux paquets publiés, avec un paquet précis en option.
     */
    private function scopeToPublishedDeck(Builder $deck, ?int $deckId): Builder
    {
        return $deck
            ->published()
            ->when($deckId !== null, fn (Builder $query): Builder => $query->whereKey($deckId));
    }

    /**
     * Récupérer l'état d'une carte pour un utilisateur, en le créant à la première révision.
     *
     * L'index unique (user_id, flashcard_id) garantit l'unicité : en cas
     * d'insertion concurrente, on relit simplement la ligne existante.
     */
    private function firstOrCreateState(User $user, Flashcard $flashcard): FlashcardState
    {
        try {
            return FlashcardState::query()->firstOrCreate(
                ['user_id' => $user->id, 'flashcard_id' => $flashcard->id],
                ['due_at' => now()],
            );
        } catch (QueryException) {
            return FlashcardState::query()
                ->where('user_id', $user->id)
                ->where('flashcard_id', $flashcard->id)
                ->firstOrFail();
        }
    }
}
