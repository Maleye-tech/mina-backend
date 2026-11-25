<?php

namespace Modules\Appointments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\AppointmentAvailability;
use Modules\Appointments\Models\AppointmentProfessional;

/**
 * @extends Factory<AppointmentAvailability>
 */
#[UseModel(AppointmentAvailability::class)]
class AppointmentAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professional_id' => AppointmentProfessional::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => fake()->randomElement(['08:00', '09:00', '10:00', '14:00', '15:00']),
            'end_time' => '12:00',
            'is_active' => true,
        ];
    }
}
