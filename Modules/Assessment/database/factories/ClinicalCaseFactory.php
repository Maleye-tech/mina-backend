<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\ClinicalCaseStep;
use Modules\Library\Database\Factories\LevelFactory;
use Modules\Library\Database\Factories\SubjectFactory;

/**
 * @extends Factory<ClinicalCase>
 */
#[UseModel(ClinicalCase::class)]
class ClinicalCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'presentation' => fake()->paragraphs(2, true),
            'subject_id' => SubjectFactory::new(),
            'level_id' => LevelFactory::new(),
            'difficulty' => Difficulty::Medium,
            'status' => ContentStatus::Draft,
            'duration_minutes' => fake()->numberBetween(10, 30),
            'learning_goals' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Cas clinique publié.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
        ]);
    }

    /**
     * Cas clinique d'une difficulté donnée.
     */
    public function ofDifficulty(Difficulty $difficulty): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => $difficulty,
        ]);
    }

    /**
     * Créer des étapes après la création du cas clinique.
     */
    public function withSteps(int $count = 3): static
    {
        return $this->afterCreating(function (ClinicalCase $clinicalCase) use ($count): void {
            ClinicalCaseStep::factory()
                ->count($count)
                ->sequence(fn (Sequence $sequence) => ['sort_order' => $sequence->index])
                ->for($clinicalCase)
                ->create();
        });
    }
}
