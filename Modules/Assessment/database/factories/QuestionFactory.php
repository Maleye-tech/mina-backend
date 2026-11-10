<?php

namespace Modules\Assessment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Enums\QuestionType;
use Modules\Assessment\Models\Question;
use Modules\Assessment\Models\QuestionOption;
use Modules\Library\Database\Factories\LevelFactory;
use Modules\Library\Database\Factories\SubjectFactory;

/**
 * @extends Factory<Question>
 */
#[UseModel(Question::class)]
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => SubjectFactory::new(),
            'level_id' => LevelFactory::new(),
            'question' => fake()->paragraph(),
            'question_type' => QuestionType::Single,
            'difficulty' => Difficulty::Medium,
            'status' => ContentStatus::Draft,
            'explanation' => fake()->optional()->paragraph(),
            'source_reference' => null,
            'fingerprint' => null,
        ];
    }

    /**
     * Question publiée.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
        ]);
    }

    /**
     * Question d'une difficulté donnée.
     */
    public function ofDifficulty(Difficulty $difficulty): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => $difficulty,
        ]);
    }

    /**
     * Créer des options après la création de la question, dont $correct bonnes réponses.
     *
     * Le type de question est automatiquement ajusté : au moins deux bonnes
     * réponses produisent une question à réponses multiples.
     */
    public function withOptions(int $total = 4, int $correct = 1): static
    {
        return $this->afterCreating(function (Question $question) use ($total, $correct): void {
            $correct = max(1, min($correct, $total));

            if ($correct > 1 && $question->question_type === QuestionType::Single) {
                $question->forceFill(['question_type' => QuestionType::Multiple])->save();
            }

            QuestionOption::factory()
                ->count($total)
                ->sequence(fn (Sequence $sequence) => ['sort_order' => $sequence->index])
                ->for($question)
                ->create();

            $correctOptionIds = $question->options()
                ->orderBy('sort_order')
                ->limit($correct)
                ->pluck('id');

            QuestionOption::query()
                ->whereIn('id', $correctOptionIds)
                ->update(['is_correct' => true]);
        });
    }
}
