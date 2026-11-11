<?php

namespace Modules\WellBeing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WellBeing\Models\AssessmentQuestion;
use Modules\WellBeing\Models\AssessmentTest;

/**
 * @extends Factory<AssessmentQuestion>
 */
#[UseModel(AssessmentQuestion::class)]
class AssessmentQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_id' => AssessmentTest::factory(),
            'question_text' => fake()->sentence().' ?',
            'options' => self::defaultOptions(),
        ];
    }

    /**
     * Options avec des points croissants, de 0 à 3.
     *
     * @return array<int, array{key: string, label: string, points: int}>
     */
    public static function defaultOptions(): array
    {
        return [
            ['key' => 'a', 'label' => 'Jamais', 'points' => 0],
            ['key' => 'b', 'label' => 'Rarement', 'points' => 1],
            ['key' => 'c', 'label' => 'Parfois', 'points' => 2],
            ['key' => 'd', 'label' => 'Souvent', 'points' => 3],
        ];
    }

    /**
     * Remplacer les options par un jeu personnalisé.
     *
     * @param  array<int, array{key: string, label: string, points: int}>  $options
     */
    public function withOptions(array $options): static
    {
        return $this->state(fn (array $attributes): array => [
            'options' => $options,
        ]);
    }
}
