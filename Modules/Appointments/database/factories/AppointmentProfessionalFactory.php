<?php

namespace Modules\Appointments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Auth\Enums\UserRole;

class AppointmentProfessionalFactory extends Factory
{
    protected $model = AppointmentProfessional::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => UserRole::Professional]),
            'title' => 'Psychologue clinicien',
            'bio' => $this->faker->paragraph(),
            'phone' => '+221 77 123 45 67',
            'consultation_duration_minutes' => 45,
            'is_available' => true,
        ];
    }
}
