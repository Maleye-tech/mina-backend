<?php

namespace Tests\Feature\WellBeing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Models\UserTestResult;
use Modules\WellBeing\Services\TestScoringService;
use Tests\Feature\WellBeing\Concerns\InteractsWithWellBeing;
use Tests\TestCase;

class UserTestResultApiTest extends TestCase
{
    use InteractsWithWellBeing;
    use RefreshDatabase;

    public function test_history_only_contains_the_current_users_results(): void
    {
        $test = AssessmentTest::factory()->create();

        $user = $this->userWithConsent();
        $own = UserTestResult::factory()->create([
            'user_id' => $user->id,
            'test_id' => $test->id,
        ]);

        UserTestResult::factory()->create(['test_id' => $test->id]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/results')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.disclaimer', TestScoringService::DISCLAIMER)
            ->assertJsonPath('data.0.test.id', $test->id);
    }

    public function test_history_can_be_filtered_by_test(): void
    {
        $firstTest = AssessmentTest::factory()->create();
        $secondTest = AssessmentTest::factory()->create();

        $user = $this->userWithConsent();
        $expected = UserTestResult::factory()->create([
            'user_id' => $user->id,
            'test_id' => $firstTest->id,
        ]);

        UserTestResult::factory()->create([
            'user_id' => $user->id,
            'test_id' => $secondTest->id,
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/wellbeing/results?test_id={$firstTest->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expected->id);
    }

    public function test_a_user_can_view_his_own_result_with_its_answers(): void
    {
        $test = AssessmentTest::factory()->create();

        $user = $this->userWithConsent();
        $result = UserTestResult::factory()->create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'score' => 2,
            'response_data' => [
                'answers' => [
                    [
                        'question_id' => 1,
                        'question_text' => 'Question de test ?',
                        'option_key' => 'c',
                        'option_label' => 'Parfois',
                        'points' => 2,
                    ],
                ],
                'max_score' => 3,
                'percentage' => 67,
                'level' => 'moderate',
            ],
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/wellbeing/results/{$result->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $result->id)
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.percentage', 67)
            ->assertJsonPath('data.level', 'moderate')
            ->assertJsonPath('data.answers.0.option_label', 'Parfois')
            ->assertJsonPath('data.disclaimer', TestScoringService::DISCLAIMER);
    }

    public function test_a_user_cannot_view_another_users_result(): void
    {
        $test = AssessmentTest::factory()->create();
        $result = UserTestResult::factory()->create(['test_id' => $test->id]);

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/wellbeing/results/{$result->id}")
            ->assertNotFound();
    }

    public function test_an_admin_can_view_another_users_result(): void
    {
        $test = AssessmentTest::factory()->create();
        $result = UserTestResult::factory()->create(['test_id' => $test->id]);

        $admin = $this->userWithConsent(UserRole::Admin);

        $this->withToken($this->tokenFor($admin))
            ->getJson("/api/v1/wellbeing/results/{$result->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $result->id)
            ->assertJsonPath('data.user.id', $result->user_id);
    }

    public function test_history_rejects_an_unknown_test_filter(): void
    {
        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/results?test_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['test_id']);
    }
}
