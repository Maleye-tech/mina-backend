<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Assessment\Events\ClinicalCaseAnswered;
use Modules\Assessment\Events\ClinicalCaseCompleted;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\ClinicalCaseAttempt;
use Modules\Assessment\Models\ClinicalCaseStep;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class ClinicalCaseAttemptTest extends TestCase
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
        $token = $user->createToken('clinical-case-attempt-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    /**
     * Créer un cas clinique publié (contexte ouest-africain fictif) avec des
     * étapes explicitement libellées.
     *
     * @return array{0: ClinicalCase, 1: array<int, ClinicalCaseStep>}
     */
    private function publishedCaseWithSteps(int $stepCount = 3): array
    {
        $case = ClinicalCase::factory()->published()->create([
            'title' => 'Fièvre prolongée à Niambara (cas fictif)',
            'presentation' => 'Moussa, 32 ans, cultivateur, se présente au centre de santé fictif de Niambara.',
        ]);

        $steps = collect(range(1, $stepCount))
            ->map(fn (int $index): ClinicalCaseStep => ClinicalCaseStep::factory()->create([
                'clinical_case_id' => $case->id,
                'sort_order' => $index,
                'prompt' => "Contexte fictif complémentaire de l’étape {$index}.",
                'question' => "Question fictive {$index} de l’étape ?",
                'expected_answer' => "Corrigé fictif {$index}.",
            ]))
            ->all();

        return [$case, $steps];
    }

    /**
     * Démarrer une tentative via l'API et renvoyer la tentative fraîche de la base.
     */
    private function startCaseAttempt(int $caseId): ClinicalCaseAttempt
    {
        $response = $this->postJson('/api/v1/clinical-cases/attempts', [
            'clinical_case_id' => $caseId,
        ])->assertCreated();

        return ClinicalCaseAttempt::query()->findOrFail($response->json('attempt.id'));
    }

    public function test_student_can_start_an_attempt_and_sees_only_the_first_step(): void
    {
        $user = $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 3);

        $this->postJson('/api/v1/clinical-cases/attempts', ['clinical_case_id' => $case->id])
            ->assertCreated()
            ->assertJsonPath('message', 'Tentative de cas clinique démarrée.')
            ->assertJsonPath('attempt.status', 'in_progress')
            ->assertJsonPath('attempt.total_steps', 3)
            ->assertJsonPath('attempt.remaining_steps', 2)
            ->assertJsonCount(1, 'attempt.steps')
            ->assertJsonPath('attempt.steps.0.id', $steps[0]->id)
            ->assertJsonPath('attempt.steps.0.answered', false)
            ->assertJsonPath('attempt.steps.0.expected_answer', null)
            ->assertJsonMissingPath('attempt.steps.1');

        $this->assertDatabaseHas('assessment_clinical_case_attempts', [
            'user_id' => $user->id,
            'clinical_case_id' => $case->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_start_rejects_an_unknown_case(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/clinical-cases/attempts', ['clinical_case_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clinical_case_id']);
    }

    public function test_start_rejects_a_draft_case(): void
    {
        $this->actingAsUser();

        $case = ClinicalCase::factory()->create(); // Brouillon par défaut.

        $this->postJson('/api/v1/clinical-cases/attempts', ['clinical_case_id' => $case->id])
            ->assertNotFound()
            ->assertJsonPath('message', 'Ce cas clinique est introuvable.');
    }

    public function test_corriges_are_revealed_step_by_step(): void
    {
        $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 3);

        $attempt = $this->startCaseAttempt($case->id);

        // Répondre à la première étape : son corrigé est révélé et l'étape
        // suivante devient visible, sans son corrigé.
        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => 'Je vérifie d’abord la température fictive du patient.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Réponse enregistrée.')
            ->assertJsonCount(2, 'attempt.steps')
            ->assertJsonPath('attempt.steps.0.answered', true)
            ->assertJsonPath('attempt.steps.0.answer', 'Je vérifie d’abord la température fictive du patient.')
            ->assertJsonPath('attempt.steps.0.expected_answer', 'Corrigé fictif 1.')
            ->assertJsonPath('attempt.steps.1.answered', false)
            ->assertJsonPath('attempt.steps.1.expected_answer', null)
            ->assertJsonPath('attempt.remaining_steps', 1);

        $this->assertDatabaseHas('assessment_clinical_case_step_answers', [
            'clinical_case_attempt_id' => $attempt->id,
            'clinical_case_step_id' => $steps[0]->id,
        ]);
    }

    public function test_answer_submissions_are_immutable(): void
    {
        $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => 'Première réponse fictive.',
        ])->assertOk();

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => 'Seconde réponse fictive.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.step_id.0', 'Cette réponse a déjà été soumise et ne peut plus être modifiée.');

        $this->assertDatabaseCount('assessment_clinical_case_step_answers', 1);
    }

    public function test_answers_must_follow_the_step_order(): void
    {
        $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 3);

        $attempt = $this->startCaseAttempt($case->id);

        // Sauter directement à la deuxième étape : refusé.
        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[1]->id,
            'answer' => 'Réponse fictive prématurée.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.step_id.0', 'Répondez d’abord aux étapes précédentes avant celle-ci.');

        $this->assertDatabaseCount('assessment_clinical_case_step_answers', 0);
    }

    public function test_step_from_another_case_is_rejected(): void
    {
        $this->actingAsUser();

        [$case] = $this->publishedCaseWithSteps(stepCount: 2);
        [, $otherSteps] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $otherSteps[0]->id,
            'answer' => 'Réponse d’une autre étape.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.step_id.0', 'Cette étape ne fait pas partie du cas clinique.');
    }

    public function test_empty_answer_is_rejected(): void
    {
        $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answer']);
    }

    public function test_cannot_answer_after_the_attempt_is_finished(): void
    {
        $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/finish")->assertOk();

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => 'Réponse tardive fictive.',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette tentative de cas clinique est terminée. Vous ne pouvez plus soumettre de réponses.');
    }

    public function test_finish_reveals_all_corriges_including_unanswered_steps(): void
    {
        $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 2);

        $attempt = $this->startCaseAttempt($case->id);

        // Répondre uniquement à la première étape.
        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => 'Réponse fictive de la première étape.',
        ])->assertOk();

        // Terminer : toutes les étapes et tous les corrigés sont révélés.
        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/finish")
            ->assertOk()
            ->assertJsonPath('message', 'Cas clinique terminé.')
            ->assertJsonPath('attempt.status', 'completed')
            ->assertJsonPath('attempt.remaining_steps', 0)
            ->assertJsonCount(2, 'attempt.steps')
            ->assertJsonPath('attempt.steps.0.answered', true)
            ->assertJsonPath('attempt.steps.0.expected_answer', 'Corrigé fictif 1.')
            ->assertJsonPath('attempt.steps.1.answered', false)
            ->assertJsonPath('attempt.steps.1.answer', null)
            ->assertJsonPath('attempt.steps.1.expected_answer', 'Corrigé fictif 2.');

        $this->assertDatabaseHas('assessment_clinical_case_attempts', [
            'id' => $attempt->id,
            'status' => 'completed',
        ]);
    }

    public function test_finishing_twice_returns_409(): void
    {
        $this->actingAsUser();

        [$case] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/finish")->assertOk();
        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/finish")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette tentative de cas clinique est déjà terminée.');
    }

    public function test_attempts_of_other_users_are_hidden(): void
    {
        $owner = $this->actingAsUser();

        [$case] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);
        $this->assertDatabaseHas('assessment_clinical_case_attempts', [
            'id' => $attempt->id,
            'user_id' => $owner->id,
        ]);

        $intruder = User::factory()->create(['role' => UserRole::Student]);
        $intruderToken = $intruder->createToken('clinical-case-intruder', ['full-access']);

        // Réinitialise le garde pour que la requête soit bien authentifiée comme intruse.
        $this->app['auth']->forgetGuards();

        $this->withToken($intruderToken->plainTextToken)
            ->getJson("/api/v1/clinical-cases/attempts/{$attempt->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Cette tentative de cas clinique est introuvable.');

        $this->withToken($intruderToken->plainTextToken)
            ->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/finish")
            ->assertNotFound()
            ->assertJsonPath('message', 'Cette tentative de cas clinique est introuvable.');
    }

    public function test_admin_can_view_any_attempt(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);

        [$case] = $this->publishedCaseWithSteps(stepCount: 1);
        $attempt = ClinicalCaseAttempt::factory()->create([
            'user_id' => $student->id,
            'clinical_case_id' => $case->id,
        ]);

        $this->actingAsUser(UserRole::Admin);

        $this->getJson("/api/v1/clinical-cases/attempts/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('attempt.id', $attempt->id);
    }

    public function test_history_lists_only_the_current_user_attempts(): void
    {
        $user = $this->actingAsUser();

        [$case] = $this->publishedCaseWithSteps(stepCount: 1);

        ClinicalCaseAttempt::factory()->count(3)->create([
            'user_id' => $user->id,
            'clinical_case_id' => $case->id,
        ]);
        ClinicalCaseAttempt::factory()->create(['clinical_case_id' => $case->id]);

        $this->getJson('/api/v1/clinical-cases/attempts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('data.0.steps');
    }

    public function test_history_validates_the_per_page_parameter(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/clinical-cases/attempts?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/v1/clinical-cases/attempts?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_answer_dispatches_the_clinical_case_answered_event(): void
    {
        Event::fake([ClinicalCaseAnswered::class, ClinicalCaseCompleted::class]);

        $user = $this->actingAsUser();

        [$case, $steps] = $this->publishedCaseWithSteps(stepCount: 2);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $steps[0]->id,
            'answer' => 'Réponse fictive.',
        ])->assertOk();

        Event::assertDispatched(ClinicalCaseAnswered::class, 1);
        Event::assertDispatched(function (ClinicalCaseAnswered $event) use ($user, $steps): bool {
            return $event->user->is($user)
                && $event->step->is($steps[0])
                && $event->stepAnswer->answer === 'Réponse fictive.';
        });
    }

    public function test_finish_dispatches_the_clinical_case_completed_event(): void
    {
        Event::fake([ClinicalCaseAnswered::class, ClinicalCaseCompleted::class]);

        $user = $this->actingAsUser();

        [$case] = $this->publishedCaseWithSteps(stepCount: 1);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/finish")->assertOk();

        Event::assertDispatched(ClinicalCaseCompleted::class, 1);
        Event::assertDispatched(function (ClinicalCaseCompleted $event) use ($user, $attempt): bool {
            return $event->user->is($user)
                && $event->attempt->id === $attempt->id
                && $event->attempt->status->value === 'completed';
        });
    }

    public function test_answers_are_rate_limited(): void
    {
        // Limiteur resserré pour le test, avec la même réponse française que
        // le RouteServiceProvider du module.
        RateLimiter::for('clinical-case-answers', fn (Request $request): Limit => Limit::perMinute(2)
            ->by($request->user()->id)
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => 'Trop de réponses envoyées. Patientez un instant avant de réessayer.',
            ], 429, $headers)));

        $this->actingAsUser();

        [$case] = $this->publishedCaseWithSteps(stepCount: 2);

        $attempt = $this->startCaseAttempt($case->id);

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $attempt->clinicalCase->steps->first()->id,
            'answer' => 'Réponse fictive 1.',
        ])->assertOk();

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $attempt->clinicalCase->steps->get(1)->id,
            'answer' => 'Réponse fictive 2.',
        ])->assertOk();

        $this->postJson("/api/v1/clinical-cases/attempts/{$attempt->id}/answers", [
            'step_id' => $attempt->clinicalCase->steps->get(1)->id,
            'answer' => 'Réponse fictive 3.',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de réponses envoyées. Patientez un instant avant de réessayer.');
    }
}
