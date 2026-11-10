<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\ClinicalCaseStepAnswer;

/**
 * @extends Factory<ClinicalCaseStepAnswer>
 */
#[UseModel(ClinicalCaseStepAnswer::class)]
class ClinicalCaseStepAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_case_attempt_id' => ClinicalCaseAttemptFactory::new(),
            'clinical_case_step_id' => ClinicalCaseStepFactory::new(),
            'answer' => fake()->paragraph(),
        ];
    }
}
