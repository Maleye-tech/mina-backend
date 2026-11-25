<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Models\AppointmentAvailability;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsProfessional(): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => UserRole::Professional]);
        $token = $user->createToken('availability-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    private function actingAsStudent(): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('availability-student-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_can_list_own_availabilities(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);

        AppointmentAvailability::factory()->count(3)->create(['professional_id' => $pro->id]);

        $this->getJson('/api/v1/appointments/availability')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_professional_without_availability_returns_empty(): void
    {
        $user = $this->actingAsProfessional();
        AppointmentProfessional::factory()->create(['user_id' => $user->id]);

        $this->getJson('/api/v1/appointments/availability')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_professional_can_create_availability(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/v1/appointments/availability', [
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ])->assertCreated()
            ->assertJsonPath('message', 'Disponibilité créée avec succès.')
            ->assertJsonPath('availability.weekday', 1)
            ->assertJsonPath('availability.start_time', '09:00')
            ->assertJsonPath('availability.end_time', '12:00')
            ->assertJsonPath('availability.is_active', true);

        $this->assertDatabaseHas('appointment_availabilities', [
            'professional_id' => $pro->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
    }

    public function test_create_availability_validates_payload(): void
    {
        $this->actingAsProfessional();

        $this->postJson('/api/v1/appointments/availability', [
            'weekday' => 7,
            'start_time' => '12:00',
            'end_time' => '09:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['weekday', 'end_time']);
    }

    public function test_student_cannot_create_availability(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/appointments/availability', [
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ])->assertForbidden();
    }

    public function test_professional_can_update_own_availability(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);
        $availability = AppointmentAvailability::factory()->create([
            'professional_id' => $pro->id,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->patchJson("/api/v1/appointments/availability/{$availability->id}", [
            'weekday' => $availability->weekday,
            'start_time' => $availability->start_time,
            'end_time' => '13:00',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('message', 'Disponibilité mise à jour.')
            ->assertJsonPath('availability.end_time', '13:00')
            ->assertJsonPath('availability.is_active', false);

        $this->assertDatabaseHas('appointment_availabilities', [
            'id' => $availability->id,
            'end_time' => '13:00',
            'is_active' => false,
        ]);
    }

    public function test_professional_cannot_update_another_professional_availability(): void
    {
        $user = $this->actingAsProfessional();
        AppointmentProfessional::factory()->create(['user_id' => $user->id]);
        $otherPro = AppointmentProfessional::factory()->create();
        $availability = AppointmentAvailability::factory()->create(['professional_id' => $otherPro->id]);

        $this->patchJson("/api/v1/appointments/availability/{$availability->id}", [
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ])->assertNotFound();
    }

    public function test_professional_can_delete_own_availability(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);
        $availability = AppointmentAvailability::factory()->create(['professional_id' => $pro->id]);

        $this->deleteJson("/api/v1/appointments/availability/{$availability->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Disponibilité supprimée.');

        $this->assertDatabaseMissing('appointment_availabilities', ['id' => $availability->id]);
    }

    public function test_availability_requires_authentication(): void
    {
        $this->getJson('/api/v1/appointments/availability')->assertUnauthorized();
        $this->postJson('/api/v1/appointments/availability', [])->assertUnauthorized();
    }
}
