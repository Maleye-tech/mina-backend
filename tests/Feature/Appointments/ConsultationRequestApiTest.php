<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\ConsultationRequest;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class ConsultationRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsProfessional(): User
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $token = $user->createToken('consultation-pro-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    private function actingAsStudent(): User
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('consultation-student-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_can_list_consultation_requests(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);

        ConsultationRequest::factory()->count(3)->create(['professional_id' => $pro->id, 'status' => 'pending']);
        ConsultationRequest::factory()->create(['professional_id' => $pro->id, 'status' => 'accepted']);

        $this->getJson('/api/v1/appointments/consultation-requests?status=pending')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_professional_can_accept_consultation_request(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);
        $request = ConsultationRequest::factory()->create(['professional_id' => $pro->id, 'status' => 'pending']);

        $this->postJson("/api/v1/appointments/consultation-requests/{$request->id}/accept", [
            'status' => 'accepted',
            'date' => '2026-12-01',
            'start_time' => '14:00',
        ])->assertOk()
            ->assertJsonPath('message', 'Demande acceptée.')
            ->assertJsonPath('consultation.status', 'accepted');

        $this->assertDatabaseHas('consultation_requests', [
            'id' => $request->id,
            'status' => 'accepted',
        ]);
    }

    public function test_professional_can_decline_consultation_request(): void
    {
        $user = $this->actingAsProfessional();
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);
        $request = ConsultationRequest::factory()->create(['professional_id' => $pro->id, 'status' => 'pending']);

        $this->postJson("/api/v1/appointments/consultation-requests/{$request->id}/decline", [
            'status' => 'declined',
            'reason' => 'Indisponible',
        ])->assertOk()
            ->assertJsonPath('message', 'Demande déclinée.')
            ->assertJsonPath('consultation.status', 'declined');
    }

    public function test_student_cannot_access_consultation_requests(): void
    {
        $this->actingAsStudent();

        $this->getJson('/api/v1/appointments/consultation-requests')->assertForbidden();
    }

    public function test_consultation_requests_require_auth(): void
    {
        $this->getJson('/api/v1/appointments/consultation-requests')->assertUnauthorized();
    }
}
