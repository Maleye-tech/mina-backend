<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Library\Database\Factories\LevelFactory;
use Modules\Library\Database\Factories\SubjectFactory;

/**
 * @extends Factory<FlashcardDeck>
 */
#[UseModel(FlashcardDeck::class)]
class FlashcardDeckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'subject_id' => SubjectFactory::new(),
            'level_id' => LevelFactory::new(),
            'status' => ContentStatus::Draft,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Deck publié.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
        ]);
    }
}
