<?php

namespace Modules\Library\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Library\Enums\ContentType;
use Modules\Library\Models\Content;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;

class ContentFactory extends Factory
{
    protected $model = Content::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'level_id' => Level::factory(),
            'type' => fake()->randomElement(ContentType::cases()),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
