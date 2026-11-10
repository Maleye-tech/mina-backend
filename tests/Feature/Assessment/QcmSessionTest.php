<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Assessment\Events\AttemptCompleted;
use Modules\Assessment\Events\QcmAnswered;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Models\Question;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class QcmSessionTest extends TestCase
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
        $token = $user->createToken('qcm-session-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    /**
     * Démarrer une session de QCM via l'API et renvoyer la tentative fraîche de la base.
     */
    private function startQcmSession(array $payload = []): Attempt
    {
        $response = $this->postJson('/api/v1/qcm/sessions', $payload)->assertCreated();

        return Attempt::query()->findOrFail($response->json('attempt.id'));
    }

    public function test_student_can_start_a_session(): void
    {
        $user = $this->actingAsUser();

        $subject = Subject::factory()->create();
        Question::factory()->count(5)->published()->withOptions()->create(['subject_id' => $subject->id]);

        $this->postJson('/api/v1/qcm/sessions', [
            'subject_id' => $subject->id,
            'question_count' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Session de QCM démarrée.')
            ->assertJsonPath('attempt.status', 'in_progress')
            ->assertJsonPath('attempt.total_questions', 3)
            ->assertJsonCount(3, 'attempt.questions')
            ->assertJsonPath('attempt.questions.0.answered', false)
            ->assertJsonMissingPath('attempt.questions.0.is_correct')
            ->assertJsonMissingPath('attempt.questions.0.correct_option_ids')
            ->assertJsonMissingPath('attempt.questions.0.explanation');

        $this->assertDatabaseHas('assessment_attempts', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'total_questions' => 3,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseCount('assessment_attempt_answers', 3);
    }

    public function test_start_session_rejects_invalid_payload(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/qcm/sessions', ['subject_id' => 999999, 'question_count' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id', 'question_count']);
    }

    public function test_start_session_fails_when_no_published_question_matches(): void
    {
        $subject = Subject::factory()->create();
        Question::factory()->count(3)->withOptions()->create(['subject_id' => $subject->id]); // Brouillons.

        $this->actingAsUser();

        $this->postJson('/api/v1/qcm/sessions', ['subject_id' => $subject->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.question_count.0', 'Aucune question publiée ne correspond à ces critères.');
    }

    public function test_correction_is_revealed_only_after_submission(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create([
            'subject_id' => $subject->id,
            'explanation' => 'Le paludisme est transmis par l’anophèle.',
        ]);
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        // Avant soumission : ni correction, ni explication.
        $this->getJson("/api/v1/qcm/sessions/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('attempt.questions.0.answered', false)
            ->assertJsonMissingPath('attempt.questions.0.is_correct')
            ->assertJsonMissingPath('attempt.questions.0.correct_option_ids')
            ->assertJsonMissingPath('attempt.questions.0.explanation');

        // Après soumission : la correction et l'explication sont révélées.
        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$correctOptionId],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Réponse enregistrée.')
            ->assertJsonPath('answer.answered', true)
            ->assertJsonPath('answer.is_correct', true)
            ->assertJsonPath('answer.selected_option_ids', [$correctOptionId])
            ->assertJsonPath('answer.correct_option_ids', [$correctOptionId])
            ->assertJsonPath('answer.explanation', 'Le paludisme est transmis par l’anophèle.');

        $this->assertDatabaseHas('assessment_attempt_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'is_correct' => true,
        ]);
    }

    public function test_wrong_answer_is_marked_incorrect_but_still_reveals_the_correction(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;
        $wrongOptionId = $question->options->firstWhere('is_correct', false)->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$wrongOptionId],
        ])
            ->assertOk()
            ->assertJsonPath('answer.is_correct', false)
            ->assertJsonPath('answer.correct_option_ids', [$correctOptionId]);
    }

    public function test_submitted_answer_is_immutable(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$correctOptionId],
        ])->assertOk();

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$correctOptionId],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.question_id.0', 'Cette réponse a déjà été soumise et ne peut plus être modifiée.');

        $this->assertDatabaseCount('assessment_attempt_answers', 1);
    }

    public function test_single_choice_question_rejects_multiple_options(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;
        $wrongOptionId = $question->options->firstWhere('is_correct', false)->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$correctOptionId, $wrongOptionId],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.option_ids.0', 'Une seule option est attendue pour cette question à réponse unique.');
    }

    public function test_answer_cannot_use_options_from_another_question(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $otherQuestion = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $foreignOptionId = $otherQuestion->options->first()->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$foreignOptionId],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['option_ids.0']);
    }

    public function test_question_outside_the_session_is_rejected(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $outsideQuestion = Question::factory()->withOptions()->create(['subject_id' => $subject->id]); // Brouillon : jamais tiré.
        $outsideOptionId = $outsideQuestion->options->first()->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $outsideQuestion->id,
            'option_ids' => [$outsideOptionId],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.question_id.0', 'Cette question ne fait pas partie de la session.');
    }

    public function test_cannot_answer_after_the_session_is_finished(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/finish")->assertOk();

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'option_ids' => [$correctOptionId],
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette session est terminée. Vous ne pouvez plus soumettre de réponses.');
    }

    public function test_finish_computes_the_score_and_dispatches_events(): void
    {
        Event::fake([QcmAnswered::class, AttemptCompleted::class]);

        $user = $this->actingAsUser();

        $subject = Subject::factory()->create();
        Question::factory()->count(2)->published()->withOptions()->create(['subject_id' => $subject->id]);

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 2]);

        // Répondre correctement à la première question du plan figé.
        $firstQuestionId = $attempt->answers()->first()->question_id;
        $firstQuestion = Question::query()->with('options')->findOrFail($firstQuestionId);
        $correctOptionId = $firstQuestion->options->firstWhere('is_correct', true)->id;

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", [
            'question_id' => $firstQuestionId,
            'option_ids' => [$correctOptionId],
        ])->assertOk();

        Event::assertDispatched(QcmAnswered::class, 1);
        Event::assertDispatched(function (QcmAnswered $event) use ($user): bool {
            return $event->user->is($user) && $event->answer->is_correct;
        });

        // Terminer : la question sans réponse est comptée incorrecte (1/2 = 50 %).
        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/finish")
            ->assertOk()
            ->assertJsonPath('message', 'Session terminée.')
            ->assertJsonPath('attempt.status', 'completed')
            ->assertJsonPath('attempt.score', 50)
            ->assertJsonPath('attempt.correct_count', 1)
            ->assertJsonPath('attempt.total_questions', 2);

        Event::assertDispatched(AttemptCompleted::class, 1);
        Event::assertDispatched(function (AttemptCompleted $event) use ($user, $attempt): bool {
            return $event->user->is($user)
                && $event->attempt->id === $attempt->id
                && (float) $event->attempt->score === 50.0;
        });
    }

    public function test_finishing_twice_returns_409(): void
    {
        $this->actingAsUser();

        $subject = Subject::factory()->create();
        Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/finish")->assertOk();
        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/finish")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette session est déjà terminée.');
    }

    public function test_sessions_of_other_users_are_hidden(): void
    {
        $owner = $this->actingAsUser();

        $subject = Subject::factory()->create();
        Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attempt->id, 'user_id' => $owner->id]);

        $intruder = User::factory()->create(['role' => UserRole::Student]);
        $intruderToken = $intruder->createToken('qcm-intruder', ['full-access']);

        // Réinitialise le garde pour que la requête soit bien authentifiée comme intruse.
        $this->app['auth']->forgetGuards();

        $this->withToken($intruderToken->plainTextToken)
            ->getJson("/api/v1/qcm/sessions/{$attempt->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Cette session est introuvable.');

        $this->withToken($intruderToken->plainTextToken)
            ->postJson("/api/v1/qcm/sessions/{$attempt->id}/finish")
            ->assertNotFound()
            ->assertJsonPath('message', 'Cette session est introuvable.');
    }

    public function test_admin_can_view_any_session(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $attempt = Attempt::factory()->create(['user_id' => $student->id]);

        $this->actingAsUser(UserRole::Admin);

        $this->getJson("/api/v1/qcm/sessions/{$attempt->id}")
            ->assertOk()
            ->assertJsonPath('attempt.id', $attempt->id);
    }

    public function test_history_lists_only_the_current_user_sessions(): void
    {
        $user = $this->actingAsUser();

        Attempt::factory()->count(3)->create(['user_id' => $user->id]);
        Attempt::factory()->create(); // Une autre utilisatrice ou un autre utilisateur.

        $this->getJson('/api/v1/qcm/sessions')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_history_validates_the_per_page_parameter(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/qcm/sessions?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/v1/qcm/sessions?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_admin_can_finish_any_session(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $attempt = Attempt::factory()->create(['user_id' => $student->id]);

        $this->actingAsUser(UserRole::Admin);

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/finish")
            ->assertOk()
            ->assertJsonPath('attempt.status', 'completed');
    }

    public function test_answer_submissions_are_rate_limited(): void
    {
        // Limiteur resserré pour le test, avec la même réponse française que
        // le RouteServiceProvider du module.
        RateLimiter::for('qcm-submissions', fn (Request $request): Limit => Limit::perMinute(2)
            ->by($request->user()->id)
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => 'Trop de réponses envoyées. Patientez un instant avant de réessayer.',
            ], 429, $headers)));

        $this->actingAsUser();

        $subject = Subject::factory()->create();
        $question = Question::factory()->published()->withOptions()->create(['subject_id' => $subject->id]);
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;

        $attempt = $this->startQcmSession(['subject_id' => $subject->id, 'question_count' => 1]);
        $payload = ['question_id' => $question->id, 'option_ids' => [$correctOptionId]];

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", $payload)->assertOk();
        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", $payload)->assertUnprocessable();

        $this->postJson("/api/v1/qcm/sessions/{$attempt->id}/answers", $payload)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de réponses envoyées. Patientez un instant avant de réessayer.');
    }
}
