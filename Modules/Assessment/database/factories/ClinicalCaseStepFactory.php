<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\ClinicalCaseStep;

/**
 * @extends Factory<ClinicalCaseStep>
 */
#[UseModel(ClinicalCaseStep::class)]
class ClinicalCaseStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_case_id' => ClinicalCaseFactory::new(),
            'prompt' => fake()->paragraph(),
            'question' => fake()->sentence(6).' ?',
            'expected_answer' => fake()->paragraph(),
            'sort_order' => 0,
        ];
    }
}
