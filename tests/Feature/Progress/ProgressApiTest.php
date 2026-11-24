<?php

namespace Tests\Feature\Progress;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Models\AttemptAnswer;
use Modules\Assessment\Models\Question;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class ProgressApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('progress-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_progress_dashboard_returns_aggregate_data(): void
    {
        $user = $this->actingAsUser();
        $subject = Subject::factory()->create(['name' => 'Cardiologie']);

        Attempt::factory()->completed()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'total_questions' => 10,
            'correct_count' => 8,
            'score' => 80.0,
            'time_spent_seconds' => 120,
        ]);

        Attempt::factory()->completed()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'total_questions' => 10,
            'correct_count' => 6,
            'score' => 60.0,
            'time_spent_seconds' => 180,
        ]);

        $response = $this->getJson('/api/v1/progress')
            ->assertOk()
            ->assertJsonStructure([
                'sessions_count',
                'average_score',
                'best_score',
                'total_questions',
                'correct_answers',
                'success_rate',
                'total_time_spent_seconds',
                'recent_history',
                'weak_points',
                'well_being',
            ]);

        $this->assertSame(2, $response->json('sessions_count'));
        $this->assertEquals(70.0, $response->json('average_score'));
        $this->assertEquals(80.0, $response->json('best_score'));
        $this->assertSame(20, $response->json('total_questions'));
        $this->assertSame(14, $response->json('correct_answers'));
        $this->assertEquals(70.0, $response->json('success_rate'));
    }

    public function test_progress_stats_can_be_filtered_by_subject(): void
    {
        $user = $this->actingAsUser();
        $subject1 = Subject::factory()->create(['name' => 'Cardiologie']);
        $subject2 = Subject::factory()->create(['name' => 'Neurologie']);

        Attempt::factory()->completed()->create([
            'user_id' => $user->id,
            'subject_id' => $subject1->id,
            'total_questions' => 10,
            'correct_count' => 8,
            'score' => 80.0,
        ]);

        Attempt::factory()->completed()->create([
            'user_id' => $user->id,
            'subject_id' => $subject2->id,
            'total_questions' => 10,
            'correct_count' => 5,
            'score' => 50.0,
        ]);

        $response = $this->getJson('/api/v1/progress/stats?subject_id=' . $subject1->id)
            ->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame($subject1->id, $response->json('0.subject_id'));
    }

    public function test_progress_history_returns_paginated_attempts(): void
    {
        $user = $this->actingAsUser();
        $subject = Subject::factory()->create(['name' => 'Cardiologie']);

        Attempt::factory()->count(3)->completed()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);

        $this->getJson('/api/v1/progress/history?per_page=2')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_progress_weak_points_detects_frequently_failed_questions(): void
    {
        $user = $this->actingAsUser();
        $subject = Subject::factory()->create();
        $question = Question::factory()->create(['subject_id' => $subject->id]);

        $attempt = Attempt::factory()->completed()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'is_correct' => false,
        ]);

        $response = $this->getJson('/api/v1/progress/weak-points')
            ->assertOk();

        $this->assertNotEmpty($response->json());
        $this->assertSame($question->id, $response->json('0.question.id'));
        $this->assertSame(1, $response->json('0.failure_count'));
    }
}
