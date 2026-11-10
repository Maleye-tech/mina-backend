<?php

namespace Modules\Assessment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessment\Enums\AttemptStatus;
use Modules\Assessment\Models\Attempt;

/**
 * @extends Factory<Attempt>
 */
#[UseModel(Attempt::class)]
class AttemptFactory extends Factory
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
            'subject_id' => null,
            'level_id' => null,
            'status' => AttemptStatus::InProgress,
            'score' => null,
            'correct_count' => 0,
            'total_questions' => 0,
            'time_spent_seconds' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Tentative terminée avec score et statistiques cohérents.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Completed,
            'score' => $attributes['score'] ?? 75.00,
            'correct_count' => $attributes['correct_count'] ?? 15,
            'total_questions' => $attributes['total_questions'] ?? 20,
            'time_spent_seconds' => $attributes['time_spent_seconds'] ?? 600,
            'completed_at' => now(),
        ]);
    }

    /**
     * Tentative abandonnée.
     */
    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Abandoned,
        ]);
    }
}
