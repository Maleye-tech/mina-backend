<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class UrgentAlertApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['full-access']);
    }

    private function createVerifiedProfessional(): User
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $profile = ProfessionalProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Dr. '.$user->name,
            'specialty' => ProfessionalSpecialty::Psychologue,
            'languages' => ['Français'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ]);
        app(ProfessionalProfileService::class)->syncLegacyProfile($profile);

        return $user;
    }

    public function test_student_can_create_urgent_alert(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'pseudonym' => 'EtudiantAlerte',
        ]);
        $this->authenticate($student);

        $response = $this->postJson('/api/alerts', [
            'message' => 'Je vais très mal, aide-moi.',
        ])->assertCreated()
            ->assertJsonPath('alert.status', 'open')
            ->assertJsonPath('alert.student.pseudonym', 'EtudiantAlerte');

        $this->assertDatabaseHas('urgent_alerts', [
            'id' => $response->json('alert.id'),
            'student_id' => $student->id,
            'status' => 'open',
        ]);
    }

    public function test_professional_can_handle_alert(): void
    {
        $professional = $this->createVerifiedProfessional();
        $alert = UrgentAlert::create([
            'student_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'message' => 'Besoin d’une écoute.',
            'status' => 'open',
        ]);
        $this->authenticate($professional);

        $this->postJson("/api/pro/alerts/{$alert->id}/handle")
            ->assertOk()
            ->assertJsonPath('alert.status', 'handled')
            ->assertJsonPath('alert.handled_by', $professional->name);

        $this->assertDatabaseHas('urgent_alerts', [
            'id' => $alert->id,
            'handled_by' => $professional->id,
            'status' => 'handled',
        ]);
    }

    public function test_student_cannot_handle_alert(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $alert = UrgentAlert::create([
            'student_id' => $student->id,
            'status' => 'open',
        ]);
        $this->authenticate($student);

        $this->postJson("/api/pro/alerts/{$alert->id}/handle")->assertForbidden();

        $this->assertSame('open', $alert->fresh()->status->value);
    }
}
