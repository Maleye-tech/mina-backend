<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\AttemptAnswer;

/**
 * @extends Factory<AttemptAnswer>
 */
#[UseModel(AttemptAnswer::class)]
class AttemptAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => AttemptFactory::new(),
            'question_id' => QuestionFactory::new(),
            'selected_option_ids' => null,
            'is_correct' => false,
            'answered_at' => null,
        ];
    }

    /**
     * Réponse soumise et marquée correcte.
     */
    public function answered(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_option_ids' => $attributes['selected_option_ids'] ?? [],
            'is_correct' => $attributes['is_correct'] ?? true,
            'answered_at' => now(),
        ]);
    }
}
