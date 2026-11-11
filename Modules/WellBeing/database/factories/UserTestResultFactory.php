<?php

namespace Modules\WellBeing\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Models\UserTestResult;

/**
 * @extends Factory<UserTestResult>
 */
#[UseModel(UserTestResult::class)]
class UserTestResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'test_id' => AssessmentTest::factory(),
            'score' => fake()->numberBetween(0, 15),
            'response_data' => [
                'answers' => [],
                'max_score' => 15,
                'percentage' => 0,
                'level' => 'low',
            ],
            'feedback_given' => 'Retour de démonstration.',
        ];
    }
}
