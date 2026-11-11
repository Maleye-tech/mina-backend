<?php

namespace Modules\WellBeing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\WellBeing\Enums\WellbeingCategory;
use Modules\WellBeing\Models\WellbeingArticle;

/**
 * @extends Factory<WellbeingArticle>
 */
#[UseModel(WellbeingArticle::class)]
class WellbeingArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'category' => fake()->randomElement(WellbeingCategory::cases()),
            'content' => fake()->paragraphs(3, true),
            'is_published' => true,
        ];
    }

    /**
     * Fiche publiée.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }

    /**
     * Fiche en brouillon.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }

    /**
     * Fiche d'une thématique donnée.
     */
    public function ofCategory(WellbeingCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
        ]);
    }
}
