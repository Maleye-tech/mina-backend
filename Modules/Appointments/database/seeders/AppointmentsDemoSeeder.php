<?php

namespace Modules\Appointments\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AppointmentSlot;
use Modules\Auth\Enums\UserRole;

class AppointmentsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proUser = User::firstOrCreate(
            ['email' => 'dr.ndiaye@mina.sn'],
            [
                'name' => 'Dr. Cheikh Ndiaye',
                'role' => UserRole::Professional,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        $pro = AppointmentProfessional::updateOrCreate(
            ['user_id' => $proUser->id],
            [
                'title' => 'Psychologue clinicien & Spécialiste du stress étudiant',
                'bio' => 'Plus de 10 ans d\'expérience dans l\'accompagnement des étudiants en médecine et filières scientifiques à Dakar.',
                'phone' => '+221 77 500 12 34',
                'consultation_duration_minutes' => 45,
                'is_available' => true,
            ]
        );

        // Generate slots for upcoming 5 days
        for ($i = 1; $i <= 5; $i++) {
            $date = now()->addDays($i)->setHour(9)->setMinute(0)->setSecond(0);

            for ($slot = 0; $slot < 4; $slot++) {
                $start = (clone $date)->addMinutes($slot * 60);
                $end = (clone $start)->addMinutes(45);

                AppointmentSlot::firstOrCreate(
                    [
                        'professional_id' => $pro->id,
                        'starts_at' => $start,
                    ],
                    [
                        'ends_at' => $end,
                        'is_booked' => false,
                    ]
                );
            }
        }
    }
}
