<?php

namespace Tests\Feature\Chat\Concerns;

use App\Models\User;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;

trait InteractsWithChat
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function tokenFor(User $user, array $abilities = ['full-access']): string
    {
        return $user->createToken('chat-test', $abilities)->plainTextToken;
    }

    protected function verifiedProfessional(array $attributes = []): User
    {
        $professional = $this->userWithRole(UserRole::Professional, $attributes);

        ProfessionalProfile::create([
            'user_id' => $professional->id,
            'display_name' => 'Dr. '.$professional->name,
            'specialty' => ProfessionalSpecialty::Psychologue,
            'languages' => ['Français'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ]);

        return $professional;
    }

    /**
     * Conversation liée à un rendez-vous professionnel.
     *
     * @return array{0: User, 1: User, 2: Appointment, 3: Conversation}
     */
    protected function appointmentConversation(
        ?User $student = null,
        ?User $professional = null,
        AppointmentStatus $status = AppointmentStatus::Confirmed,
    ): array {
        $student ??= $this->userWithRole(UserRole::Student);
        $professional ??= $this->verifiedProfessional();
        $profile = $professional->professionalCareProfile;

        if ($profile === null) {
            $profile = ProfessionalProfile::create([
                'user_id' => $professional->id,
                'display_name' => 'Dr. '.$professional->name,
                'specialty' => ProfessionalSpecialty::Psychologue,
                'languages' => ['Français'],
                'is_verified' => true,
                'charter_accepted_at' => now(),
            ]);
        }

        $legacy = app(ProfessionalProfileService::class)->syncLegacyProfile($profile);
        $appointment = Appointment::create([
            'student_id' => $student->id,
            'professional_id' => $legacy->id,
            'professional_user_id' => $professional->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
            'status' => $status,
            'consultation_type' => 'chat',
        ]);
        $conversation = Conversation::create([
            'participant_one_id' => min($student->id, $professional->id),
            'participant_two_id' => max($student->id, $professional->id),
            'appointment_id' => $appointment->id,
            'status' => 'active',
        ]);

        return [$student, $professional, $appointment, $conversation];
    }

    /**
     * Conversation existante entre un étudiant et un professionnel.
     *
     * @return array{0: User, 1: User, 2: Conversation}
     */
    protected function existingConversation(?User $student = null, ?User $professional = null): array
    {
        $student ??= $this->userWithRole(UserRole::Student);
        $professional ??= $this->userWithRole(UserRole::Professional);

        $conversation = Conversation::factory()
            ->between([$student->id, $professional->id])
            ->create();

        return [$student, $professional, $conversation];
    }
}
