<?php

namespace Modules\Assessment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Enums\AttemptStatus;
use Modules\Assessment\Models\ClinicalCaseAttempt;

/**
 * @extends Factory<ClinicalCaseAttempt>
 */
#[UseModel(ClinicalCaseAttempt::class)]
class ClinicalCaseAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'clinical_case_id' => ClinicalCaseFactory::new(),
            'status' => AttemptStatus::InProgress,
            'completed_at' => null,
        ];
    }

    /**
     * Tentative terminée.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
