<?php

namespace Modules\Appointments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\ConsultationRequest;
use Modules\Auth\Enums\UserRole;

/**
 * @extends Factory<ConsultationRequest>
 */
#[UseModel(ConsultationRequest::class)]
class ConsultationRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'professional_id' => AppointmentProfessional::factory(),
            'topic' => fake()->sentence(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'pending',
            'requested_at' => now(),
            'scheduled_for' => null,
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
