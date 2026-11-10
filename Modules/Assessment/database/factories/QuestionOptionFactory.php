<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\QuestionOption;

/**
 * @extends Factory<QuestionOption>
 */
#[UseModel(QuestionOption::class)]
class QuestionOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => QuestionFactory::new(),
            'label' => fake()->sentence(4),
            'is_correct' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Option marquée comme bonne réponse.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
