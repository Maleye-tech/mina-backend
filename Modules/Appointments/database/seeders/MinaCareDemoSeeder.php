<?php

namespace Modules\Appointments\Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Enums\SlotMode;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Models\SessionNote;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;

class MinaCareDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $students = [
                User::query()->updateOrCreate(
                    ['email' => 'etudiant.fatou@example.com'],
                    [
                        'name' => 'Fatou Ndiaye',
                        'pseudonym' => 'ÉtoileDuMatin',
                        'password' => 'password',
                        'role' => UserRole::Student,
                        'email_verified_at' => now(),
                    ],
                ),
                User::query()->updateOrCreate(
                    ['email' => 'etudiant.ibrahima@example.com'],
                    [
                        'name' => 'Ibrahima Fall',
                        'pseudonym' => 'PhoenixSante',
                        'password' => 'password',
                        'role' => UserRole::Student,
                        'email_verified_at' => now(),
                    ],
                ),
            ];

            $professionals = [];
            $definitions = [
                [
                    'email' => 'psy.aminata@example.com',
                    'name' => 'Dr. Aminata Touré',
                    'display_name' => 'Dr. Aminata Touré',
                    'specialty' => ProfessionalSpecialty::Psychologue,
                    'languages' => ['Français', 'Wolof'],
                    'bio' => 'Psychologue clinicienne spécialisée dans l’anxiété liée aux études médicales, le surmenage et la gestion du stress pré-concours.',
                ],
                [
                    'email' => 'psychiatre.cheikh@example.com',
                    'name' => 'Dr. Cheikh Tidiane Wade',
                    'display_name' => 'Dr. Cheikh Tidiane Wade',
                    'specialty' => ProfessionalSpecialty::Psychiatre,
                    'languages' => ['Français', 'Wolof', 'Anglais'],
                    'bio' => 'Médecin psychiatre hospitalier et universitaire. Prise en charge des troubles anxio-dépressifs et des urgences psychiatriques étudiantes.',
                ],
                [
                    'email' => 'conseiller.oumy@example.com',
                    'name' => 'Mme Oumy Diallo',
                    'display_name' => 'Mme Oumy Diallo',
                    'specialty' => ProfessionalSpecialty::Conseiller,
                    'languages' => ['Français', 'Pulaar', 'Wolof'],
                    'bio' => 'Conseillère psychosociale et d’orientation. Écoute bienveillante, prévention du décrochage et médiation universitaire.',
                ],
            ];

            foreach ($definitions as $definition) {
                $user = User::query()->updateOrCreate(
                    ['email' => $definition['email']],
                    [
                        'name' => $definition['name'],
                        'password' => 'password',
                        'role' => UserRole::Professional,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ],
                );

                $profile = ProfessionalProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'display_name' => $definition['display_name'],
                        'specialty' => $definition['specialty'],
                        'languages' => $definition['languages'],
                        'bio' => $definition['bio'],
                        'is_verified' => true,
                        'charter_accepted_at' => now()->subMonths(1),
                    ],
                );

                app(ProfessionalProfileService::class)->syncLegacyProfile($profile);
                $professionals[] = ['user' => $user, 'profile' => $profile];
            }

            foreach ($professionals as ['user' => $user]) {
                for ($day = 1; $day <= 7; $day++) {
                    $date = Carbon::today()->addDays($day);
                    $this->slot($user, $date->copy()->setTime(9, 0), $date->copy()->setTime(9, 45), SlotMode::Visio);
                    $this->slot(
                        $user,
                        $date->copy()->setTime(14, 30),
                        $date->copy()->setTime(15, 15),
                        $day % 2 === 0 ? SlotMode::Chat : SlotMode::Visio,
                    );
                }
            }

            $mainUser = $professionals[0]['user'];
            $legacyProfile = AppointmentProfessional::query()->where('user_id', $mainUser->id)->firstOrFail();
            $slots = AvailabilitySlot::query()
                ->where('professional_id', $mainUser->id)
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->get();

            $confirmedSlot = $slots[0];
            $confirmed = $this->appointment($confirmedSlot, $legacyProfile, $students[0], AppointmentStatus::Confirmed, [
                'reason' => 'Crises d’angoisse avant les examens de pharmacologie.',
                'meeting_url' => 'https://meet.jit.si/mina-care-demo-visio-room',
                'meeting_link' => 'https://meet.jit.si/mina-care-demo-visio-room',
                'responded_at' => now()->subHours(4),
            ]);
            $confirmedSlot->update(['is_booked' => true]);

            $conversation = Conversation::query()->firstOrCreate(
                ['appointment_id' => $confirmed->id],
                [
                    'participant_one_id' => min($students[0]->id, $mainUser->id),
                    'participant_two_id' => max($students[0]->id, $mainUser->id),
                    'status' => 'active',
                    'last_message_at' => now()->subMinutes(10),
                ],
            );

            if (! $conversation->messages()->exists()) {
                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $students[0]->id,
                    'body' => 'Bonjour Docteur, je suis très stressée par l’internat qui approche.',
                ]);
                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $mainUser->id,
                    'body' => 'Bonjour. Nous prendrons le temps d’en parler en détail lors de notre séance.',
                ]);
            }

            SessionNote::query()->updateOrCreate(
                ['appointment_id' => $confirmed->id, 'professional_id' => $mainUser->id],
                ['body' => 'Étudiante en 4e année. Syndrome anxieux réactionnel au concours. Proposer des exercices de cohérence cardiaque et de désensibilisation.'],
            );

            $requestedSlot = $slots[1];
            $this->appointment($requestedSlot, $legacyProfile, $students[1], AppointmentStatus::Requested, [
                'reason' => 'Difficultés de sommeil et sentiment d’épuisement persistant.',
            ]);
            $requestedSlot->update(['is_booked' => true]);

            $proposedSlot = $slots[2];
            $this->appointment($proposedSlot, $legacyProfile, $students[0], AppointmentStatus::Proposed, [
                'reason' => 'Demande d’accompagnement pour la gestion du stress.',
                'responded_at' => now()->subHour(),
            ]);
            $proposedSlot->update(['is_booked' => true]);

            $declinedSlot = $slots[3];
            $this->appointment($declinedSlot, $legacyProfile, $students[1], AppointmentStatus::Declined, [
                'reason' => 'Conflit d’agenda.',
                'responded_at' => now()->subHours(2),
            ]);
            $declinedSlot->update(['is_booked' => false]);

            $cancelledSlot = $slots[4];
            $this->appointment($cancelledSlot, $legacyProfile, $students[0], AppointmentStatus::Cancelled, [
                'reason' => 'Empêchement personnel.',
                'cancellation_reason' => 'Annulé par l’étudiant.',
                'cancelled_by' => $students[0]->id,
            ]);
            $cancelledSlot->update(['is_booked' => false]);

            $completedSlot = $this->slot(
                $mainUser,
                now()->subDays(2)->setTime(10, 0),
                now()->subDays(2)->setTime(10, 45),
                SlotMode::Visio,
                false,
            );
            $this->appointment($completedSlot, $legacyProfile, $students[1], AppointmentStatus::Completed, [
                'reason' => 'Première séance d’écoute et d’orientation.',
                'responded_at' => now()->subDays(3),
            ]);

            UrgentAlert::query()->firstOrCreate(
                ['student_id' => $students[1]->id],
                [
                    'message' => 'Épuisement complet, besoin d’un soutien immédiat.',
                    'status' => 'open',
                    'created_at' => now()->subMinutes(30),
                ],
            );
        });
    }

    private function slot(User $professional, Carbon $startsAt, Carbon $endsAt, SlotMode $mode, bool $booked = false): AvailabilitySlot
    {
        return AvailabilitySlot::query()->firstOrCreate(
            ['professional_id' => $professional->id, 'starts_at' => $startsAt],
            [
                'ends_at' => $endsAt,
                'mode' => $mode,
                'is_booked' => $booked,
            ],
        );
    }

    private function appointment(
        AvailabilitySlot $slot,
        AppointmentProfessional $legacyProfile,
        User $student,
        AppointmentStatus $status,
        array $attributes = [],
    ): Appointment {
        return Appointment::query()->firstOrCreate(
            [
                'availability_slot_id' => $slot->id,
                'student_id' => $student->id,
                'status' => $status,
            ],
            [
                'professional_id' => $legacyProfile->id,
                'professional_user_id' => $legacyProfile->user_id,
                'scheduled_at' => $slot->starts_at,
                'duration_minutes' => 45,
                'consultation_type' => $slot->mode->value,
                ...$attributes,
            ],
        );
    }
}
