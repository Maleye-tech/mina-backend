<?php

namespace Modules\Events\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Events\Enums\EventType;
use Modules\Events\Models\Event;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        $startsAt = now()->addDays(5)->setHour(16)->setMinute(0);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'type' => EventType::Workshop,
            'description' => $this->faker->paragraph(),
            'presenter_name' => $this->faker->name(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'location_or_link' => 'https://meet.mina.sn/webinar-demo',
            'max_participants' => 100,
            'current_participants' => 0,
            'replay_url' => null,
            'is_published' => true,
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(5)->addHours(2),
            'replay_url' => 'https://videos.mina.sn/replays/event-1.mp4',
        ]);
    }
}
