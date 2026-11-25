<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Models\Alert;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class AlertApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsProfessional(): User
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $token = $user->createToken('alert-pro-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_can_list_alerts(): void
    {
        $user = $this->actingAsProfessional();

        Alert::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'open']);
        Alert::factory()->create(['user_id' => $user->id, 'status' => 'resolved']);

        $this->getJson('/api/v1/appointments/alerts?status=open')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_professional_can_update_alert_status(): void
    {
        $user = $this->actingAsProfessional();
        $alert = Alert::factory()->create(['user_id' => $user->id, 'status' => 'open']);

        $this->patchJson("/api/v1/appointments/alerts/{$alert->id}", [
            'status' => 'acknowledged',
        ])->assertOk()
            ->assertJsonPath('message', 'Alerte mise à jour.')
            ->assertJsonPath('alert.status', 'acknowledged');

        $this->assertDatabaseHas('alerts', ['id' => $alert->id, 'status' => 'acknowledged']);
    }

    public function test_alerts_are_isolated_by_user(): void
    {
        $owner = $this->actingAsProfessional();
        Alert::factory()->create(['user_id' => $owner->id, 'title' => 'Alerte owner', 'type' => 'system', 'severity' => 'low', 'message' => 'msg', 'status' => 'open']);

        $intruder = User::factory()->create(['role' => UserRole::Professional]);
        $intruderToken = $intruder->createToken('alert-intruder', ['full-access']);

        $this->app['auth']->forgetGuards();
        $this->withToken($intruderToken->plainTextToken);

        $this->patchJson('/api/v1/appointments/alerts/1', ['status' => 'resolved'])->assertNotFound();
    }

    public function test_alerts_require_auth(): void
    {
        $this->getJson('/api/v1/appointments/alerts')->assertUnauthorized();
        $this->patchJson('/api/v1/appointments/alerts/1', [])->assertUnauthorized();
    }
}
