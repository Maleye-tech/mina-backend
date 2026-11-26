<?php

namespace Modules\Appointments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Auth\Enums\UserRole;

#[UseModel(AppointmentProfessional::class)]
class AppointmentProfessionalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => UserRole::Professional]),
            'display_name' => 'Dr. '.fake()->name(),
            'specialty' => ProfessionalSpecialty::Psychologue,
            'languages' => ['fr', 'wolof'],
            'bio' => $this->faker->paragraph(),
            'phone' => '+221 77 123 45 67',
            'consultation_duration_minutes' => 45,
            'is_available' => true,
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ];
    }
}
