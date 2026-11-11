<?php

namespace Tests\Feature\WellBeing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\WellBeing\Enums\AssessmentTestType;
use Modules\WellBeing\Models\AssessmentQuestion;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Models\UserTestResult;
use Modules\WellBeing\Services\TestScoringService;
use Tests\Feature\WellBeing\Concerns\InteractsWithWellBeing;
use Tests\TestCase;

class AssessmentTestApiTest extends TestCase
{
    use InteractsWithWellBeing;
    use RefreshDatabase;

    /**
     * @var array<int, array{key: string, label: string, points: int}>
     */
    private array $options = [
        ['key' => 'a', 'label' => 'Jamais', 'points' => 0],
        ['key' => 'b', 'label' => 'Rarement', 'points' => 1],
        ['key' => 'c', 'label' => 'Parfois', 'points' => 2],
        ['key' => 'd', 'label' => 'Souvent', 'points' => 3],
    ];

    public function test_index_lists_tests_with_their_question_count(): void
    {
        $test = $this->makeTest();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/tests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $test->id)
            ->assertJsonPath('data.0.questions_count', 2)
            ->assertJsonPath('data.0.disclaimer', TestScoringService::DISCLAIMER);
    }

    public function test_index_can_be_filtered_by_type(): void
    {
        $anxiety = $this->makeTest(AssessmentTestType::Anxiety);
        $this->makeTest(AssessmentTestType::Stress);

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/tests?type=anxiety')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anxiety->id);
    }

    public function test_show_returns_questions_without_exposing_points(): void
    {
        $test = $this->makeTest();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/wellbeing/tests/{$test->id}")
            ->assertOk()
            ->assertJsonCount(2, 'test.questions')
            ->assertJsonMissingPath('test.questions.0.options.0.points')
            ->assertJsonPath('test.questions.0.options.0.key', 'a')
            ->assertJsonPath('test.questions.0.options.0.label', 'Jamais')
            ->assertJsonPath('test.disclaimer', TestScoringService::DISCLAIMER);
    }

    public function test_submitting_answers_computes_the_score_and_returns_the_disclaimer(): void
    {
        $test = $this->makeTest();
        [$first, $second] = $test->questions()->get()->all();

        $user = $this->userWithConsent();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'd'],
                    ['question_id' => $second->id, 'option_key' => 'd'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('disclaimer', TestScoringService::DISCLAIMER)
            ->assertJsonPath('result.score', 6)
            ->assertJsonPath('result.max_score', 6)
            ->assertJsonPath('result.percentage', 100)
            ->assertJsonPath('result.level', 'high')
            ->assertJsonPath('result.disclaimer', TestScoringService::DISCLAIMER);

        $result = UserTestResult::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(config('wellbeing.scoring.anxiety.2.feedback'), $result->feedback_given);
        $this->assertCount(2, $result->response_data['answers']);
    }

    public function test_submitting_a_moderate_score_returns_the_moderate_feedback(): void
    {
        $test = $this->makeTest();
        [$first, $second] = $test->questions()->get()->all();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'b'],
                    ['question_id' => $second->id, 'option_key' => 'c'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('result.score', 3)
            ->assertJsonPath('result.percentage', 50)
            ->assertJsonPath('result.level', 'moderate');
    }

    public function test_response_data_is_encrypted_at_rest_and_decrypts_through_the_model(): void
    {
        $test = $this->makeTest();
        [$first, $second] = $test->questions()->get()->all();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'c'],
                    ['question_id' => $second->id, 'option_key' => 'a'],
                ],
            ])
            ->assertCreated();

        $result = UserTestResult::query()->where('user_id', $user->id)->firstOrFail();

        $raw = DB::table('user_test_results')->where('id', $result->id)->value('response_data');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('Jamais', $raw);
        $this->assertNull(json_decode($raw, true));

        $this->assertSame(6, $result->response_data['max_score']);
        $this->assertSame(33, $result->response_data['percentage']);
        $this->assertSame('low', $result->response_data['level']);
        $this->assertCount(2, $result->response_data['answers']);
    }

    public function test_all_questions_must_be_answered(): void
    {
        $test = $this->makeTest();
        [$first] = $test->questions()->get()->all();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'a'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_a_question_cannot_be_answered_twice(): void
    {
        $test = $this->makeTest();
        [$first] = $test->questions()->get()->all();

        $user = $this->userWithConsent();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'a'],
                    ['question_id' => $first->id, 'option_key' => 'b'],
                ],
            ]);

        $response->assertUnprocessable();

        $this->assertTrue(
            collect($response->json('errors'))
                ->keys()
                ->contains(fn (string $key): bool => str_starts_with($key, 'answers')),
        );
    }

    public function test_questions_from_another_test_are_rejected(): void
    {
        $test = $this->makeTest();
        [$first, $second] = $test->questions()->get()->all();
        $foreignQuestion = $this->makeTest()->questions()->firstOrFail();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'a'],
                    ['question_id' => $foreignQuestion->id, 'option_key' => 'b'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_an_unknown_option_key_is_rejected(): void
    {
        $test = $this->makeTest();
        [$first, $second] = $test->questions()->get()->all();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", [
                'answers' => [
                    ['question_id' => $first->id, 'option_key' => 'z'],
                    ['question_id' => $second->id, 'option_key' => 'a'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_test_submissions_are_rate_limited_per_user(): void
    {
        $test = $this->makeTest();
        [$first, $second] = $test->questions()->get()->all();

        $user = $this->userWithConsent();
        $token = $this->tokenFor($user);

        $payload = [
            'answers' => [
                ['question_id' => $first->id, 'option_key' => 'a'],
                ['question_id' => $second->id, 'option_key' => 'a'],
            ],
        ];

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withToken($token)
                ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", $payload)
                ->assertCreated();
        }

        $this->withToken($token)
            ->postJson("/api/v1/wellbeing/tests/{$test->id}/results", $payload)
            ->assertStatus(429);
    }

    private function makeTest(AssessmentTestType $type = AssessmentTestType::Anxiety): AssessmentTest
    {
        $test = AssessmentTest::factory()->ofType($type)->create();

        foreach (['Question une ?', 'Question deux ?'] as $questionText) {
            AssessmentQuestion::factory()->create([
                'test_id' => $test->id,
                'question_text' => $questionText,
                'options' => $this->options,
            ]);
        }

        return $test;
    }
}
