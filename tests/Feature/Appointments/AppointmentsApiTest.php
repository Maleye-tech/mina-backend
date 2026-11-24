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

class AppointmentsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('appointments-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_list_professionals_and_their_slots(): void
    {
        $this->actingAsUser();

        $proUser = User::factory()->create(['name' => 'Dr. Aminata Diallo', 'role' => UserRole::Professional]);
        $pro = AppointmentProfessional::factory()->create(['user_id' => $proUser->id]);
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $this->getJson('/api/v1/appointments/professionals')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pro->id)
            ->assertJsonPath('data.0.name', 'Dr. Aminata Diallo');

        $this->getJson("/api/v1/appointments/professionals/{$pro->id}/slots")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $slot->id);
    }

    public function test_student_can_book_appointment(): void
    {
        $student = $this->actingAsUser(UserRole::Student);

        $proUser = User::factory()->create(['name' => 'Dr. Aminata Diallo', 'role' => UserRole::Professional]);
        $pro = AppointmentProfessional::factory()->create(['user_id' => $proUser->id]);
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $response = $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
            'consultation_type' => 'video',
            'student_notes' => 'Stress important avant les partiels',
        ])
            ->assertCreated()
            ->assertJsonPath('appointment.status', AppointmentStatus::Confirmed->value);

        $this->assertTrue($slot->fresh()->is_booked);
        $this->assertNotEmpty($response->json('appointment.meeting_link'));
    }

    public function test_booking_same_slot_twice_fails(): void
    {
        $this->actingAsUser();

        $pro = AppointmentProfessional::factory()->create();
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        $this->actingAsUser();
        $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertStatus(422);
    }

    public function test_student_can_cancel_appointment(): void
    {
        $student = $this->actingAsUser();
        $pro = AppointmentProfessional::factory()->create();
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $booking = $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        $appointmentId = $booking->json('appointment.id');

        $this->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
            'reason' => 'Empêchement de dernière minute',
        ])
            ->assertOk()
            ->assertJsonPath('appointment.status', AppointmentStatus::Cancelled->value);

        $this->assertFalse($slot->fresh()->is_booked);
    }

    public function test_student_can_reschedule_appointment(): void
    {
        $student = $this->actingAsUser();
        $pro = AppointmentProfessional::factory()->create();
        $slot1 = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);
        $slot2 = AppointmentSlot::factory()->create([
            'professional_id' => $pro->id,
            'starts_at' => now()->addDays(3)->setHour(14)->setMinute(0),
        ]);

        $booking = $this->postJson('/api/v1/appointments', [
            'professional_id' => $pro->id,
            'slot_id' => $slot1->id,
        ])->assertCreated();

        $appointmentId = $booking->json('appointment.id');

        $this->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
            'new_slot_id' => $slot2->id,
        ])
            ->assertOk()
            ->assertJsonPath('appointment.scheduled_at', $slot2->starts_at->toIso8601String());

        $this->assertFalse($slot1->fresh()->is_booked);
        $this->assertTrue($slot2->fresh()->is_booked);
    }

    public function test_confidential_notes_are_not_exposed_to_student(): void
    {
        $student = $this->actingAsUser(UserRole::Student);
        $proUser = User::factory()->create(['role' => UserRole::Professional]);
        $pro = AppointmentProfessional::factory()->create(['user_id' => $proUser->id]);
        $slot = AppointmentSlot::factory()->create(['professional_id' => $pro->id]);

        $appointment = Appointment::factory()->create([
            'student_id' => $student->id,
            'professional_id' => $pro->id,
            'slot_id' => $slot->id,
            'confidential_notes' => 'Note clinique strictement confidentielle',
        ]);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}")
            ->assertOk();

        $this->assertNull($response->json('data.confidential_notes'));
    }
}
