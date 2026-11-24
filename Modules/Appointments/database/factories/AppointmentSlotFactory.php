<?php

namespace Modules\Appointments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AppointmentSlot;

class AppointmentSlotFactory extends Factory
{
    protected $model = AppointmentSlot::class;

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
