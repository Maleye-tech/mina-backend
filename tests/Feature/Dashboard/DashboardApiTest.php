<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('dashboard-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_dashboard_returns_json_indicators(): void
    {
        $user = $this->actingAsUser(UserRole::Professional);
        $pro = AppointmentProfessional::factory()->create(['user_id' => $user->id]);

        Appointment::factory()->count(3)->create([
            'professional_id' => $pro->id,
            'status' => AppointmentStatus::Pending,
        ]);
        Appointment::factory()->count(2)->create([
            'professional_id' => $pro->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->getJson('/api/v1/dashboards?role=professional')
            ->assertOk();
    }

    public function test_professional_without_appointments_returns_zeros(): void
    {
        $this->actingAsUser(UserRole::Professional);

        $this->getJson('/api/v1/dashboards?role=professional')
            ->assertOk()
            ->assertJsonPath('data.pending_demands_count', 0)
            ->assertJsonPath('data.appointments_count', 0)
            ->assertJsonPath('data.recent_activities', []);
    }

    public function test_student_receives_default_indicators(): void
    {
        $this->actingAsUser(UserRole::Student);

        $this->getJson('/api/v1/dashboards?role=student')
            ->assertOk()
            ->assertJsonPath('data.appointments_count', 0)
            ->assertJsonPath('data.recent_activities', []);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboards?role=professional')
            ->assertUnauthorized();
    }
}
