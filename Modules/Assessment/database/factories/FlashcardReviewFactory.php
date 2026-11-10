<?php

namespace Modules\Assessment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\FlashcardReview;

/**
 * @extends Factory<FlashcardReview>
 */
#[UseModel(FlashcardReview::class)]
class FlashcardReviewFactory extends Factory
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
            'flashcard_id' => FlashcardFactory::new(),
            'quality' => fake()->numberBetween(0, 5),
            'easiness_factor' => 2.50,
            'interval_days' => fake()->numberBetween(1, 30),
            'due_at' => now()->addDays(fake()->numberBetween(1, 30)),
        ];
    }

    /**
     * Révision réussie (qualité supérieure ou égale à 3).
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality' => fake()->numberBetween(3, 5),
        ]);
    }

    /**
     * Révision échouée (qualité inférieure à 3).
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality' => fake()->numberBetween(0, 2),
        ]);
    }
}
