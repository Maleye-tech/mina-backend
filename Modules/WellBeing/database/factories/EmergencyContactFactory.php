<?php

namespace Modules\WellBeing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WellBeing\Models\EmergencyContact;

/**
 * @extends Factory<EmergencyContact>
 */
#[UseModel(EmergencyContact::class)]
class EmergencyContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'role_or_description' => fake()->sentence(4),
            'phone_number' => fake()->numerify('+221 ## ### ## ##'),
            'address' => fake()->address(),
            'availability' => '24h/24, 7j/7',
            'is_active' => true,
        ];
    }

    /**
     * Contact désactivé.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
