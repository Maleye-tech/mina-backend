<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AppointmentSlot;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class StudentAppointmentsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsStudent(): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('student-booking-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_student_can_book_available_slot(): void
    {
        $student = $this->actingAsStudent();

        $proUser = User::factory()->create(['role' => UserRole::Professional]);
        $pro = AppointmentProfessional::factory()->create([
            'user_id' => $proUser->id,
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ]);
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
            'consultation_type' => 'video',
            'student_notes' => 'Motif de consultation.',
        ])->assertCreated()
            ->assertJsonPath('appointment.status', 'confirmed');

        $this->assertTrue($slot->fresh()->is_booked);
    }

    public function test_double_booking_is_rejected(): void
    {
        $this->actingAsStudent();

        $pro = AppointmentProfessional::factory()->create();
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        // Deuxième réservation sur le même créneau
        $anotherStudent = User::factory()->create(['role' => UserRole::Student]);
        $token = $anotherStudent->createToken('double-booking-test', ['full-access']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);

        $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertStatus(422);
    }

    public function test_student_can_cancel_own_appointment(): void
    {
        $student = $this->actingAsStudent();

        $pro = AppointmentProfessional::factory()->create();
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $booking = $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        $appointmentId = $booking->json('appointment.id');

        $this->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
            'reason' => 'Empêchement.',
        ])->assertOk()
            ->assertJsonPath('appointment.status', 'cancelled');
    }

    public function test_student_cannot_access_another_student_appointment(): void
    {
        $this->actingAsStudent();

        $pro = AppointmentProfessional::factory()->create();
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $booking = $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        $appointmentId = $booking->json('appointment.id');

        $anotherStudent = User::factory()->create(['role' => UserRole::Student]);
        $token = $anotherStudent->createToken('cross-student-test', ['full-access']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);

        $this->getJson("/api/v1/appointments/{$appointmentId}")->assertNotFound();
    }

    public function test_chat_refused_if_appointment_not_confirmed(): void
    {
        $student = $this->actingAsStudent();

        $pro = AppointmentProfessional::factory()->create();
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $booking = $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        $appointmentId = $booking->json('appointment.id');

        // Créer une conversation liée (simulé)
        $appointment = Appointment::find($appointmentId);
        $appointment->update(['status' => AppointmentStatus::Requested]);

        $this->postJson("/api/appointments/{$appointmentId}/messages", [
            'body' => 'Bonjour.',
        ])->assertStatus(403);
    }
}
