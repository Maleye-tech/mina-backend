<?php

namespace Modules\Appointments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AppointmentSlot;

#[UseModel(AppointmentSlot::class)]
class AppointmentSlotFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDays(2)->setHour(10)->setMinute(0);

        return [
            'professional_id' => AppointmentProfessional::factory(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addMinutes(45),
            'is_booked' => false,
        ];
    }
}
