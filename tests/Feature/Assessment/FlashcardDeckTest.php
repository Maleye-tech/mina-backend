<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class FlashcardDeckTest extends TestCase
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
        $token = $user->createToken('flashcard-deck-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_lists_only_published_decks_with_filters(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $otherSubject = Subject::factory()->create();

        FlashcardDeck::factory()->published()->count(2)->create(['subject_id' => $subject->id]);
        FlashcardDeck::factory()->published()->create(['subject_id' => $otherSubject->id]);
        FlashcardDeck::factory()->create(['subject_id' => $subject->id]); // Brouillon : jamais listé.

        $this->getJson("/api/v1/flashcards/decks?subject_id={$subject->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subject.id', $subject->id);
    }

    public function test_index_includes_the_cards_count(): void
    {
        $this->actingAsUser();

        FlashcardDeck::factory()
            ->published()
            ->has(Flashcard::factory()->count(3))
            ->create();

        $this->getJson('/api/v1/flashcards/decks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cards_count', 3);
    }

    public function test_show_returns_the_deck_with_its_cards_sorted(): void
    {
        $this->actingAsUser();

        $deck = FlashcardDeck::factory()->published()->create();

        collect([2, 1, 3])->each(fn (int $order) => Flashcard::factory()->create([
            'deck_id' => $deck->id,
            'sort_order' => $order,
            'front' => "Carte fictive {$order}",
        ]));

        $this->getJson("/api/v1/flashcards/decks/{$deck->id}")
            ->assertOk()
            ->assertJsonPath('deck.id', $deck->id)
            ->assertJsonCount(3, 'deck.cards')
            ->assertJsonPath('deck.cards.0.front', 'Carte fictive 1')
            ->assertJsonPath('deck.cards.1.front', 'Carte fictive 2')
            ->assertJsonPath('deck.cards.2.front', 'Carte fictive 3')
            ->assertJsonMissingPath('deck.cards.0.state');
    }

    public function test_show_serves_an_etag_and_answers_304_to_if_none_match(): void
    {
        $this->actingAsUser();

        $deck = FlashcardDeck::factory()
            ->published()
            ->has(Flashcard::factory()->count(2))
            ->create();

        $etag = $this->getJson("/api/v1/flashcards/decks/{$deck->id}")
            ->assertOk()
            ->headers->get('ETag');

        $this->assertNotNull($etag);

        $this->getJson("/api/v1/flashcards/decks/{$deck->id}", ['If-None-Match' => $etag])
            ->assertNoContent(304);
    }

    public function test_draft_deck_is_hidden_from_students_and_visible_to_admins(): void
    {
        $deck = FlashcardDeck::factory()->has(Flashcard::factory())->create(); // Brouillon par défaut.

        $this->actingAsUser(UserRole::Student);
        $this->getJson("/api/v1/flashcards/decks/{$deck->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Ce paquet de cartes est introuvable.');

        $this->actingAsUser(UserRole::Admin);
        $this->getJson("/api/v1/flashcards/decks/{$deck->id}")->assertOk();
    }

    public function test_deleted_deck_returns_404(): void
    {
        $this->actingAsUser();

        $deck = FlashcardDeck::factory()->published()->create();
        $deck->delete();

        $this->getJson("/api/v1/flashcards/decks/{$deck->id}")->assertNotFound();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/flashcards/decks?subject_id=999999&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id', 'per_page']);
    }
}
