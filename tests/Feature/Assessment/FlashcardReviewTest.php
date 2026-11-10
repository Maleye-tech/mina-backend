<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Assessment\Events\FlashcardReviewed;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Assessment\Models\FlashcardState;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class FlashcardReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authentifier un utilisateur via Sanctum avec l'accès complet.
     *
     * Les gardes sont réinitialisées pour que les requêtes précédentes du
     * même test ne gardent pas un autre utilisateur en cache.
     */
    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('flashcard-review-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    /**
     * Créer un paquet publié avec des cartes explicitement ordonnées.
     *
     * @param  array<int, string>  $fronts
     * @return array<int, Flashcard>
     */
    private function publishedDeckWithCards(array $fronts = ['Recto 1', 'Recto 2']): array
    {
        $deck = FlashcardDeck::factory()->published()->create();

        $cards = collect($fronts)->map(fn (string $front, int $index) => Flashcard::factory()->create([
            'deck_id' => $deck->id,
            'front' => $front,
            'sort_order' => $index + 1,
        ]));

        return [$deck, $cards->all()];
    }

    public function test_today_lists_due_cards_first_then_new_cards(): void
    {
        $user = $this->actingAsUser();

        [$deck, [$dueCard, $futureCard, $newCard]] = $this->publishedDeckWithCards(['Recto dû', 'Recto futur', 'Recto neuf']);

        // Carte due depuis hier.
        FlashcardState::factory()->create([
            'user_id' => $user->id,
            'flashcard_id' => $dueCard->id,
            'due_at' => now()->subDay(),
        ]);

        // Carte non échue : exclue de la file du jour.
        FlashcardState::factory()->dueInDays(3)->create([
            'user_id' => $user->id,
            'flashcard_id' => $futureCard->id,
        ]);

        $response = $this->getJson('/api/v1/flashcards/reviews/today')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // La carte due précède la carte nouvelle.
        $this->assertSame($dueCard->id, $response->json('data.0.id'));
        $this->assertSame($newCard->id, $response->json('data.1.id'));

        // La carte due expose son état ; la carte nouvelle n'en a pas.
        $response
            ->assertJsonPath('data.0.deck.id', $deck->id)
            ->assertJsonPath('data.0.state.repetitions', 0)
            ->assertJsonPath('data.1.state', null);

        $this->assertArrayHasKey('state', $response->json('data.1'));
    }

    public function test_today_respects_the_deck_filter(): void
    {
        $user = $this->actingAsUser();

        [$deckA, [$cardA]] = $this->publishedDeckWithCards(['Recto A']);
        [$deckB, [$cardB]] = $this->publishedDeckWithCards(['Recto B']);

        FlashcardState::factory()->create(['user_id' => $user->id, 'flashcard_id' => $cardA->id]);
        FlashcardState::factory()->create(['user_id' => $user->id, 'flashcard_id' => $cardB->id]);

        $this->getJson("/api/v1/flashcards/reviews/today?deck_id={$deckB->id}&new_limit=0")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.deck.id', $deckB->id);
    }

    public function test_new_cards_can_be_disabled_with_a_zero_limit(): void
    {
        $this->actingAsUser();

        $this->publishedDeckWithCards(['Recto 1', 'Recto 2']);

        $this->getJson('/api/v1/flashcards/reviews/today?new_limit=0')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_today_excludes_cards_from_draft_decks(): void
    {
        $user = $this->actingAsUser();

        $draftDeck = FlashcardDeck::factory()->has(Flashcard::factory()->count(2))->create(); // Brouillon.

        FlashcardState::factory()->create([
            'user_id' => $user->id,
            'flashcard_id' => $draftDeck->flashcards()->first()->id,
        ]);

        $this->getJson('/api/v1/flashcards/reviews/today')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_today_only_considers_the_current_user_states(): void
    {
        $user = $this->actingAsUser();
        $other = User::factory()->create(['role' => UserRole::Student]);

        [, [$firstCard, $secondCard]] = $this->publishedDeckWithCards(['Recto 1', 'Recto 2']);

        // État échu appartenant à un autre utilisateur : la carte reste
        // nouvelle pour l'utilisateur courant.
        FlashcardState::factory()->create([
            'user_id' => $other->id,
            'flashcard_id' => $firstCard->id,
        ]);

        $response = $this->getJson('/api/v1/flashcards/reviews/today')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($firstCard->id));
        $this->assertTrue($ids->contains($secondCard->id));
        $this->assertDatabaseHas('assessment_flashcard_states', [
            'user_id' => $other->id,
            'flashcard_id' => $firstCard->id,
        ]);
    }

    public function test_reviewing_a_new_card_creates_the_state_and_the_first_review(): void
    {
        $user = $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 4])
            ->assertOk()
            ->assertJsonPath('message', 'Révision enregistrée.')
            ->assertJsonPath('state.easiness_factor', 2.5)
            ->assertJsonPath('state.interval_days', 1)
            ->assertJsonPath('state.repetitions', 1)
            ->assertJsonPath('state.lapses', 0)
            ->assertJsonPath('state.last_quality', 4);

        $this->assertDatabaseHas('assessment_flashcard_states', [
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
            'easiness_factor' => 2.50,
            'interval_days' => 1,
            'repetitions' => 1,
        ]);
        $this->assertDatabaseHas('assessment_flashcard_reviews', [
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
            'quality' => 4,
            'interval_days' => 1,
        ]);
    }

    public function test_reviewing_an_existing_card_applies_sm2_and_appends_history(): void
    {
        $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);

        // Première révision réussie : intervalle d'un jour.
        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 4])
            ->assertOk()
            ->assertJsonPath('state.interval_days', 1);

        // Deuxième révision réussie : intervalle SM-2 de six jours.
        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 4])
            ->assertOk()
            ->assertJsonPath('state.interval_days', 6)
            ->assertJsonPath('state.repetitions', 2);

        $this->assertDatabaseCount('assessment_flashcard_reviews', 2);
    }

    public function test_a_failed_review_resets_the_streak(): void
    {
        $user = $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);

        FlashcardState::factory()->create([
            'user_id' => $user->id,
            'flashcard_id' => $card->id,
            'easiness_factor' => 2.50,
            'interval_days' => 6,
            'repetitions' => 2,
            'lapses' => 1,
        ]);

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 2])
            ->assertOk()
            ->assertJsonPath('state.repetitions', 0)
            ->assertJsonPath('state.lapses', 2)
            ->assertJsonPath('state.interval_days', 1)
            ->assertJsonPath('state.easiness_factor', 2.18);
    }

    public function test_review_dispatches_the_flashcard_reviewed_event(): void
    {
        Event::fake([FlashcardReviewed::class]);

        $user = $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 5])->assertOk();

        Event::assertDispatched(FlashcardReviewed::class, 1);
        Event::assertDispatched(function (FlashcardReviewed $event) use ($user, $card): bool {
            return $event->user->is($user)
                && $event->flashcard->is($card)
                && $event->review->quality === 5;
        });
    }

    public function test_reviewing_a_card_from_a_draft_deck_returns_404(): void
    {
        $this->actingAsUser();

        $draftDeck = FlashcardDeck::factory()->create(); // Brouillon par défaut.
        $card = Flashcard::factory()->create(['deck_id' => $draftDeck->id]);

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 4])
            ->assertNotFound()
            ->assertJsonPath('message', 'Cette carte est introuvable.');
    }

    public function test_deleted_flashcard_returns_404(): void
    {
        $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);
        $card->delete();

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 4])->assertNotFound();
    }

    public function test_invalid_quality_is_rejected(): void
    {
        $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", ['quality' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quality']);
    }

    public function test_unknown_deck_filter_is_rejected(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/flashcards/reviews/today?deck_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deck_id']);
    }

    public function test_reviews_are_rate_limited(): void
    {
        // Limiteur resserré pour le test, avec la même réponse française que
        // le RouteServiceProvider du module.
        RateLimiter::for('flashcard-reviews', fn (Request $request): Limit => Limit::perMinute(2)
            ->by($request->user()->id)
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => 'Trop de révisions envoyées. Patientez un instant avant de réessayer.',
            ], 429, $headers)));

        $this->actingAsUser();

        [, [$card]] = $this->publishedDeckWithCards(['Recto 1']);
        $payload = ['quality' => 4];

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", $payload)->assertOk();
        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", $payload)->assertOk();

        $this->postJson("/api/v1/flashcards/{$card->id}/reviews", $payload)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de révisions envoyées. Patientez un instant avant de réessayer.');
    }
}
