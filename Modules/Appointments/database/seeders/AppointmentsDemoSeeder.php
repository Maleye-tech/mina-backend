<?php

namespace Modules\Appointments\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AppointmentSlot;
use Modules\Auth\Enums\UserRole;

class AppointmentsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 3 professionnels vérifiés avec charte acceptée
        $professionals = [];

        $professionals[] = $this->createProfessional(
            'dr.ndiaye@mina.sn',
            'Dr. Cheikh Ndiaye',
            'Psychologue clinicien spécialiste du stress étudiant',
            ProfessionalSpecialty::Psychologue,
            ['fr', 'wolof'],
            '+221 77 500 12 34',
        );

        $professionals[] = $this->createProfessional(
            'dr.sow@mina.sn',
            'Dr. Awa Sow',
            'Psychiatre spécialiste des troubles anxieux',
            ProfessionalSpecialty::Psychiatre,
            ['fr', 'anglais'],
            '+221 77 600 34 56',
        );

        $professionals[] = $this->createProfessional(
            'conseiller.diallo@mina.sn',
            'M. Amadou Diallo',
            'Conseiller d’écoute et accompagnement universitaire',
            ProfessionalSpecialty::Conseiller,
            ['fr', 'peul'],
            '+221 77 700 78 90',
        );

        // Créneaux sur les 7 prochains jours pour chaque pro
        foreach ($professionals as $pro) {
            for ($i = 1; $i <= 7; $i++) {
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

        // 2 étudiants de test
        $student1 = User::firstOrCreate(
            ['email' => 'etudiant1@test.sn'],
            [
                'name' => 'Aminata Diallo',
                'role' => UserRole::Student,
                'email_verified_at' => now(),
                'password' => 'password',
                'pseudonym' => 'amina_med',
            ]
        );

        $student2 = User::firstOrCreate(
            ['email' => 'etudiant2@test.sn'],
            [
                'name' => 'Moussa Sarr',
                'role' => UserRole::Student,
                'email_verified_at' => now(),
                'password' => 'password',
                'pseudonym' => 'moussa_pharma',
            ]
        );

        // Quelques rendez-vous dans différents statuts
        $pro1 = AppointmentProfessional::where('user_id', $professionals[0]->id)->first();
        $slot1 = AppointmentSlot::where('professional_id', $pro1->id)->where('is_booked', false)->first();

        if ($slot1) {
            $slot1->update(['is_booked' => true]);

            Appointment::firstOrCreate(
                ['id' => 1],
                [
                    'student_id' => $student1->id,
                    'professional_id' => $pro1->id,
                    'professional_user_id' => $pro1->user_id,
                    'slot_id' => $slot1->id,
                    'scheduled_at' => $slot1->starts_at,
                    'duration_minutes' => $pro1->consultation_duration_minutes,
                    'status' => AppointmentStatus::Confirmed,
                    'consultation_type' => 'video',
                    'meeting_url' => 'https://meet.mina.sn/care-abc123',
                    'student_notes' => 'Anxiété avant les partiels.',
                    'confidential_notes' => 'Suivre de près les crises de panique.',
                    'responded_at' => now(),
                ]
            );
        }

        $slot2 = AppointmentSlot::where('professional_id', $pro1->id)->where('is_booked', false)->first();
        if ($slot2) {
            $slot2->update(['is_booked' => true]);

            Appointment::firstOrCreate(
                ['id' => 2],
                [
                    'student_id' => $student2->id,
                    'professional_id' => $pro1->id,
                    'professional_user_id' => $pro1->user_id,
                    'slot_id' => $slot2->id,
                    'scheduled_at' => $slot2->starts_at,
                    'duration_minutes' => $pro1->consultation_duration_minutes,
                    'status' => AppointmentStatus::Requested,
                    'consultation_type' => 'chat',
                    'student_notes' => 'Demande de première consultation.',
                    'responded_at' => null,
                ]
            );
        }

        $pro2 = AppointmentProfessional::where('user_id', $professionals[1]->id)->first();
        $slot3 = AppointmentSlot::where('professional_id', $pro2->id)->where('is_booked', false)->first();
        if ($slot3) {
            $slot3->update(['is_booked' => true]);

            Appointment::firstOrCreate(
                ['id' => 3],
                [
                    'student_id' => $student1->id,
                    'professional_id' => $pro2->id,
                    'professional_user_id' => $pro2->user_id,
                    'slot_id' => $slot3->id,
                    'scheduled_at' => $slot3->starts_at,
                    'duration_minutes' => $pro2->consultation_duration_minutes,
                    'status' => AppointmentStatus::Declined,
                    'consultation_type' => 'video',
                    'student_notes' => 'Dépression saisonnière.',
                    'responded_at' => now(),
                    'cancellation_reason' => 'Indisponibilité du professionnel.',
                ]
            );
        }
    }

    private function createProfessional(
        string $email,
        string $name,
        string $bio,
        ProfessionalSpecialty $specialty,
        array $languages,
        string $phone
    ): User {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => UserRole::Professional,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        AppointmentProfessional::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $name,
                'specialty' => $specialty,
                'languages' => $languages,
                'bio' => $bio,
                'phone' => $phone,
                'consultation_duration_minutes' => 45,
                'is_available' => true,
                'is_verified' => true,
                'charter_accepted_at' => now(),
            ]
        );

        return $user;
    }
}
