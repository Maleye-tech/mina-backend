<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\ClinicalCaseStep;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class ClinicalCaseTest extends TestCase
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
        $token = $user->createToken('clinical-case-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_lists_only_published_cases_with_filters(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $otherSubject = Subject::factory()->create();

        ClinicalCase::factory()->published()->ofDifficulty(Difficulty::Hard)->count(2)
            ->create(['subject_id' => $subject->id]);
        ClinicalCase::factory()->published()->ofDifficulty(Difficulty::Easy)
            ->create(['subject_id' => $subject->id]);
        ClinicalCase::factory()->create(['subject_id' => $subject->id]); // Brouillon : jamais listé.
        ClinicalCase::factory()->published()->create(['subject_id' => $otherSubject->id]);

        $this->getJson("/api/v1/clinical-cases?subject_id={$subject->id}&difficulty=hard")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subject.id', $subject->id);
    }

    public function test_index_includes_the_steps_count(): void
    {
        $this->actingAsUser();

        $case = ClinicalCase::factory()->published()->create();

        ClinicalCaseStep::factory()->count(3)->create(['clinical_case_id' => $case->id]);

        $this->getJson('/api/v1/clinical-cases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.steps_count', 3);
    }

    public function test_show_returns_the_case_without_leaking_step_content(): void
    {
        $this->actingAsUser();

        $case = ClinicalCase::factory()->published()->create([
            'presentation' => 'Aïcha, 24 ans, se présente au centre de santé fictif de Niambara.',
        ]);

        ClinicalCaseStep::factory()->create([
            'clinical_case_id' => $case->id,
            'question' => 'Quelle est votre conduite à tenir ?',
            'expected_answer' => 'Corrigé fictif confidentiel.',
        ]);

        $this->getJson("/api/v1/clinical-cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('clinical_case.id', $case->id)
            ->assertJsonPath('clinical_case.steps_count', 1)
            ->assertJsonPath(
                'clinical_case.presentation',
                'Aïcha, 24 ans, se présente au centre de santé fictif de Niambara.',
            )
            ->assertJsonMissingPath('clinical_case.steps')
            ->assertJsonMissingPath('clinical_case.expected_answer');
    }

    public function test_show_serves_an_etag_and_answers_304_to_if_none_match(): void
    {
        $this->actingAsUser();

        $case = ClinicalCase::factory()->published()->create();

        $etag = $this->getJson("/api/v1/clinical-cases/{$case->id}")
            ->assertOk()
            ->headers->get('ETag');

        $this->assertNotNull($etag);

        $this->getJson("/api/v1/clinical-cases/{$case->id}", ['If-None-Match' => $etag])
            ->assertNoContent(304);
    }

    public function test_draft_case_is_hidden_from_students_and_visible_to_admins(): void
    {
        $case = ClinicalCase::factory()->create(); // Brouillon par défaut.

        $this->actingAsUser(UserRole::Student);
        $this->getJson("/api/v1/clinical-cases/{$case->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Ce cas clinique est introuvable.');

        $this->actingAsUser(UserRole::Admin);
        $this->getJson("/api/v1/clinical-cases/{$case->id}")->assertOk();
    }

    public function test_deleted_case_returns_404(): void
    {
        $this->actingAsUser();

        $case = ClinicalCase::factory()->published()->create();
        $case->delete();

        $this->getJson("/api/v1/clinical-cases/{$case->id}")->assertNotFound();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/clinical-cases?difficulty=impossible&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['difficulty', 'per_page']);
    }
}
