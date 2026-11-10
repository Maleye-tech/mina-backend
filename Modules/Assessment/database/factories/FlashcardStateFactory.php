<?php

namespace Modules\Assessment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Models\FlashcardState;

/**
 * @extends Factory<FlashcardState>
 */
#[UseModel(FlashcardState::class)]
class FlashcardStateFactory extends Factory
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
            'easiness_factor' => 2.50,
            'interval_days' => 0,
            'repetitions' => 0,
            'lapses' => 0,
            'last_quality' => null,
            'due_at' => now(),
            'last_reviewed_at' => null,
        ];
    }

    /**
     * Carte à réviser aujourd'hui.
     */
    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => now()->startOfDay(),
        ]);
    }

    /**
     * Carte à réviser dans un nombre de jours donné.
     */
    public function dueInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => now()->addDays($days),
        ]);
    }

    /**
     * Carte déjà révisée plusieurs fois avec succès.
     */
    public function learned(): static
    {
        return $this->state(fn (array $attributes) => [
            'easiness_factor' => 2.50,
            'interval_days' => 6,
            'repetitions' => 2,
            'last_quality' => 4,
            'due_at' => now()->addDays(6),
            'last_reviewed_at' => now()->subDays(6),
        ]);
    }
}
