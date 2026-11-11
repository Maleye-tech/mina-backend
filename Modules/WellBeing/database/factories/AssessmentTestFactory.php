<?php

namespace Modules\WellBeing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WellBeing\Enums\AssessmentTestType;
use Modules\WellBeing\Models\AssessmentTest;

/**
 * @extends Factory<AssessmentTest>
 */
#[UseModel(AssessmentTest::class)]
class AssessmentTestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(AssessmentTestType::cases()),
        ];
    }

    /**
     * Test d'un type donné.
     */
    public function ofType(AssessmentTestType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }
}
