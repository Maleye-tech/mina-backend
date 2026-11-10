<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Models\Question;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class QcmQuestionTest extends TestCase
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
        $token = $user->createToken('qcm-question-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_lists_only_published_questions_with_filters(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $otherSubject = Subject::factory()->create();

        Question::factory()->published()->ofDifficulty(Difficulty::Hard)->count(2)->withOptions()->create(['subject_id' => $subject->id]);
        Question::factory()->published()->ofDifficulty(Difficulty::Easy)->withOptions()->create(['subject_id' => $subject->id]);
        Question::factory()->withOptions()->create(['subject_id' => $subject->id]); // Brouillon : jamais listé.
        Question::factory()->published()->withOptions()->create(['subject_id' => $otherSubject->id]);

        $this->getJson("/api/v1/qcm/questions?subject_id={$subject->id}&difficulty=hard")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subject.id', $subject->id);
    }

    public function test_index_does_not_leak_correction_data(): void
    {
        $this->actingAsUser();

        Question::factory()->published()->withOptions()->create(['explanation' => 'Explication confidentielle.']);

        $this->getJson('/api/v1/qcm/questions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.explanation')
            ->assertJsonMissingPath('data.0.options');
    }

    public function test_show_returns_question_without_correction(): void
    {
        $this->actingAsUser();

        $question = Question::factory()->published()->withOptions()->create([
            'explanation' => 'Explication confidentielle.',
        ]);

        $this->getJson("/api/v1/qcm/questions/{$question->id}")
            ->assertOk()
            ->assertJsonPath('question.id', $question->id)
            ->assertJsonCount(4, 'question.options')
            ->assertJsonMissingPath('question.explanation')
            ->assertJsonMissingPath('question.options.0.is_correct');
    }

    public function test_show_serves_an_etag_and_answers_304_to_if_none_match(): void
    {
        $this->actingAsUser();

        $question = Question::factory()->published()->withOptions()->create();

        $etag = $this->getJson("/api/v1/qcm/questions/{$question->id}")
            ->assertOk()
            ->headers->get('ETag');

        $this->assertNotNull($etag);

        $this->getJson("/api/v1/qcm/questions/{$question->id}", ['If-None-Match' => $etag])
            ->assertNoContent(304);
    }

    public function test_draft_question_is_hidden_from_students_and_visible_to_admins(): void
    {
        $question = Question::factory()->withOptions()->create(); // Brouillon par défaut.

        $this->actingAsUser(UserRole::Student);
        $this->getJson("/api/v1/qcm/questions/{$question->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Cette question est introuvable.');

        $this->actingAsUser(UserRole::Admin);
        $this->getJson("/api/v1/qcm/questions/{$question->id}")
            ->assertOk();
    }

    public function test_deleted_question_returns_404(): void
    {
        $this->actingAsUser();

        $question = Question::factory()->published()->withOptions()->create();
        $question->delete();

        $this->getJson("/api/v1/qcm/questions/{$question->id}")->assertNotFound();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/qcm/questions?difficulty=impossible&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['difficulty', 'per_page']);
    }

    public function test_unknown_subject_filter_is_rejected(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/qcm/questions?subject_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id']);
    }
}
