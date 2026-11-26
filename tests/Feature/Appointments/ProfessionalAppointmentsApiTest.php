<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Enums\SlotMode;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class ProfessionalAppointmentsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedProfessional(array $attributes = []): array
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $profile = ProfessionalProfile::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Dr. Test',
            'specialty' => ProfessionalSpecialty::Psychologue,
            'languages' => ['Français', 'Wolof'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ], $attributes));
        $legacy = app(ProfessionalProfileService::class)->syncLegacyProfile($profile);

        return compact('user', 'profile', 'legacy');
    }

    private function actingAsProfessional(): array
    {
        $this->app['auth']->forgetGuards();
        $professional = $this->createVerifiedProfessional();
        $token = $professional['user']->createToken('pro-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $professional;
    }

    private function actingAsStudent(): User
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('student-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    private function createAppointment(array $professional, AppointmentStatus $status, ?AvailabilitySlot $slot = null): Appointment
    {
        return Appointment::create([
            'student_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'professional_id' => $professional['legacy']->id,
            'professional_user_id' => $professional['user']->id,
            'availability_slot_id' => $slot?->id,
            'scheduled_at' => $slot?->starts_at ?? now()->addDays(2),
            'duration_minutes' => 45,
            'status' => $status,
            'consultation_type' => 'visio',
        ]);
    }

    private function createSlot(User $professional, array $attributes = []): AvailabilitySlot
    {
        $startsAt = now()->addDays(2)->setTime(10, 0);

        return AvailabilitySlot::create(array_merge([
            'professional_id' => $professional->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(45),
            'mode' => SlotMode::Visio,
            'is_booked' => false,
        ], $attributes));
    }

    public function test_professional_can_see_own_profile(): void
    {
        $professional = $this->actingAsProfessional();

        $this->getJson('/api/v1/pro/profile')
            ->assertOk()
            ->assertJsonPath('profile.id', $professional['user']->id)
            ->assertJsonPath('profile.profile_id', $professional['profile']->id)
            ->assertJsonPath('profile.has_accepted_charter', true);
    }

    public function test_professional_can_update_profile(): void
    {
        $this->actingAsProfessional();

        $this->putJson('/api/v1/pro/profile', [
            'display_name' => 'Dr. Awa Sow',
            'specialty' => 'psychiatre',
            'languages' => ['fr', 'wolof'],
            'bio' => 'Psychiatre spécialisée des troubles anxieux.',
        ])->assertOk()
            ->assertJsonPath('profile.display_name', 'Dr. Awa Sow')
            ->assertJsonPath('profile.specialty', 'psychiatre');
    }

    public function test_professional_can_accept_charter(): void
    {
        $professional = $this->actingAsProfessional();
        $professional['profile']->update(['charter_accepted_at' => null]);

        $this->postJson('/api/v1/pro/profile/accept-charter', [
            'accept' => true,
        ])->assertOk()
            ->assertJsonPath('message', 'La charte de confidentialité et de déontologie a été acceptée avec succès.')
            ->assertJsonPath('profile.has_accepted_charter', true);

        $this->assertNotNull($professional['profile']->fresh()->charter_accepted_at);
    }

    public function test_professional_must_explicitly_accept_charter(): void
    {
        $professional = $this->actingAsProfessional();
        $professional['profile']->update(['charter_accepted_at' => null]);

        $this->postJson('/api/v1/pro/profile/accept-charter', [
            'accept' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('accept');

        $this->assertNull($professional['profile']->fresh()->charter_accepted_at);
    }

    public function test_student_cannot_access_pro_routes(): void
    {
        $this->actingAsStudent();

        $this->getJson('/api/v1/pro/profile')->assertForbidden();
        $this->putJson('/api/v1/pro/profile', [])->assertForbidden();
    }

    public function test_professional_can_confirm_appointment(): void
    {
        $professional = $this->actingAsProfessional();
        $slot = $this->createSlot($professional['user'], ['is_booked' => true]);
        $appointment = $this->createAppointment($professional, AppointmentStatus::Requested, $slot);

        $this->postJson("/api/v1/pro/appointments/{$appointment->id}/confirm")
            ->assertOk()
            ->assertJsonPath('appointment.status', 'confirmed')
            ->assertJsonPath('appointment.can_chat', true)
            ->assertJsonPath('appointment.can_send_messages', true);

        $this->assertNotNull($appointment->fresh()->responded_at);
        $this->assertDatabaseHas('chat_conversations', [
            'appointment_id' => $appointment->id,
        ]);
    }

    public function test_professional_can_confirm_pending_appointment(): void
    {
        $professional = $this->actingAsProfessional();
        $appointment = $this->createAppointment($professional, AppointmentStatus::Pending);

        $this->postJson("/api/v1/pro/appointments/{$appointment->id}/confirm")
            ->assertOk()
            ->assertJsonPath('appointment.status', 'confirmed');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_professional_cannot_confirm_proposed_appointment(): void
    {
        $professional = $this->actingAsProfessional();
        $appointment = $this->createAppointment($professional, AppointmentStatus::Proposed);

        $this->postJson("/api/v1/pro/appointments/{$appointment->id}/confirm")
            ->assertUnprocessable();

        $this->assertSame(AppointmentStatus::Proposed, $appointment->fresh()->status);
        $this->assertDatabaseMissing('chat_conversations', ['appointment_id' => $appointment->id]);
    }

    public function test_professional_can_update_meeting_url_after_confirmation(): void
    {
        $professional = $this->actingAsProfessional();
        $appointment = $this->createAppointment($professional, AppointmentStatus::Confirmed);

        $this->putJson("/api/v1/pro/appointments/{$appointment->id}", [
            'meeting_url' => 'https://meet.mina.sn/care-xyz',
        ])->assertOk()
            ->assertJsonPath('appointment.status', 'confirmed')
            ->assertJsonPath('appointment.meeting_url', 'https://meet.mina.sn/care-xyz');
    }

    public function test_professional_can_decline_appointment_and_release_slot(): void
    {
        $professional = $this->actingAsProfessional();
        $slot = $this->createSlot($professional['user'], ['is_booked' => true]);
        $appointment = $this->createAppointment($professional, AppointmentStatus::Requested, $slot);

        $this->postJson("/api/v1/pro/appointments/{$appointment->id}/decline", [
            'reason' => 'Indisponible.',
        ])->assertOk()
            ->assertJsonPath('appointment.status', 'declined');

        $this->assertFalse($slot->fresh()->is_booked);
    }

    public function test_professional_can_complete_appointment(): void
    {
        $professional = $this->actingAsProfessional();
        $appointment = $this->createAppointment($professional, AppointmentStatus::Confirmed);

        $this->postJson("/api/v1/pro/appointments/{$appointment->id}/complete")
            ->assertOk()
            ->assertJsonPath('appointment.status', 'completed')
            ->assertJsonPath('appointment.can_chat', true)
            ->assertJsonPath('appointment.can_send_messages', false);
    }

    public function test_professional_cannot_access_another_professional_appointment(): void
    {
        $this->actingAsProfessional();
        $other = $this->createVerifiedProfessional();
        $appointment = $this->createAppointment($other, AppointmentStatus::Requested);

        $this->getJson("/api/v1/pro/appointments/{$appointment->id}")->assertNotFound();
    }

    public function test_student_is_anonymized_for_professional(): void
    {
        $professional = $this->actingAsProfessional();
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'name' => 'Vrai Nom',
            'email' => 'vrai@email.sn',
            'pseudonym' => 'etudiant_secret',
        ]);
        $appointment = $this->createAppointment($professional, AppointmentStatus::Requested);
        $appointment->update(['student_id' => $student->id]);

        $response = $this->getJson("/api/v1/pro/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.student.name')
            ->assertJsonMissingPath('data.student.email')
            ->assertJsonPath('data.student.pseudonym', 'etudiant_secret');

        $this->assertStringStartsWith('anon_', $response->json('data.student.id'));
        $this->assertStringNotContainsString('Vrai Nom', $response->getContent());
        $this->assertStringNotContainsString('vrai@email.sn', $response->getContent());
    }
}
