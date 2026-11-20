<?php

namespace Modules\Library\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Library\Enums\CompressionStatus;
use Modules\Library\Models\Level;
use Modules\Library\Models\Podcast;
use Modules\Library\Models\Subject;

class PodcastFactory extends Factory
{
    protected $model = Podcast::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'level_id' => Level::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'original_path' => 'podcasts/originals/'.fake()->uuid().'.mp3',
            'compressed_path' => null,
            'compression_status' => CompressionStatus::Pending,
            'mime_type' => 'audio/mpeg',
            'size_bytes' => fake()->numberBetween(100_000, 50_000_000),
            'duration_seconds' => fake()->numberBetween(60, 3600),
            'uploaded_by' => null,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
