<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\Flashcard;

/**
 * @extends Factory<Flashcard>
 */
#[UseModel(Flashcard::class)]
class FlashcardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deck_id' => FlashcardDeckFactory::new(),
            'front' => fake()->sentence(6),
            'back' => fake()->paragraph(),
            'hint' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
