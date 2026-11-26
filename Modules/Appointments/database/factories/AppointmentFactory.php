<?php

namespace Modules\Appointments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentSlot;
use Modules\Auth\Enums\UserRole;

#[UseModel(Appointment::class)]
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $slot = AppointmentSlot::factory()->create();

        return [
            'student_id' => User::factory()->create(['role' => UserRole::Student]),
            'professional_id' => $slot->professional_id,
            'slot_id' => $slot->id,
            'scheduled_at' => $slot->starts_at,
            'duration_minutes' => 45,
            'status' => AppointmentStatus::Confirmed,
            'consultation_type' => 'video',
            'meeting_link' => 'https://meet.mina.sn/care-demo',
            'meeting_url' => 'https://meet.mina.sn/care-demo',
            'student_notes' => 'Difficultés de gestion du stress avant les examens.',
            'confidential_notes' => 'Premier contact d\'écoute bienveillante.',
        ];
    }
}
