<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\PrivateNote;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class PrivateNoteApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsProfessional(): User
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Professional]);
        ProfessionalProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => $user->name,
            'specialty' => 'psychologue',
            'languages' => ['Français'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ]);
        $token = $user->createToken('private-note-pro-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_can_crud_private_notes(): void
    {
        $user = $this->actingAsProfessional();

        // Créer
        $this->postJson('/api/v1/appointments/private-notes', [
            'body' => 'Note clinique confidentielle.',
        ])->assertCreated()
            ->assertJsonPath('message', 'Note privée créée.')
            ->assertJsonPath('note.body', 'Note clinique confidentielle.');

        $noteId = PrivateNote::query()->first()->id;

        // Lire
        $this->getJson('/api/v1/appointments/private-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Mettre à jour
        $this->patchJson("/api/v1/appointments/private-notes/{$noteId}", [
            'body' => 'Note mise à jour.',
        ])->assertOk()
            ->assertJsonPath('note.body', 'Note mise à jour.');

        // Supprimer
        $this->deleteJson("/api/v1/appointments/private-notes/{$noteId}")
            ->assertOk()
            ->assertJsonPath('message', 'Note privée supprimée.');

        $this->assertDatabaseMissing('private_notes', ['id' => $noteId]);
    }

    public function test_professional_cannot_attach_a_note_to_another_appointment(): void
    {
        $this->actingAsProfessional();
        $student = User::factory()->create(['role' => UserRole::Student]);
        $otherProfessional = AppointmentProfessional::factory()->create();
        $appointment = Appointment::factory()->create([
            'student_id' => $student->id,
            'professional_id' => $otherProfessional->id,
        ]);

        $this->postJson('/api/v1/appointments/private-notes', [
            'appointment_id' => $appointment->id,
            'body' => 'Tentative de rattachement non autorisé.',
        ])->assertNotFound();

        $this->assertDatabaseCount('private_notes', 0);
    }

    public function test_private_notes_are_isolated_by_user(): void
    {
        $owner = $this->actingAsProfessional();
        PrivateNote::factory()->create(['user_id' => $owner->id, 'body' => 'Note du owner']);

        $intruder = User::factory()->create(['role' => UserRole::Professional]);
        ProfessionalProfile::query()->create([
            'user_id' => $intruder->id,
            'display_name' => $intruder->name,
            'specialty' => 'psychologue',
            'languages' => ['Français'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ]);
        $intruderToken = $intruder->createToken('private-note-intruder', ['full-access']);

        $this->app['auth']->forgetGuards();
        $this->withToken($intruderToken->plainTextToken);

        $this->getJson('/api/v1/appointments/private-notes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->deleteJson('/api/v1/appointments/private-notes/1')->assertNotFound();
    }

    public function test_private_notes_require_auth(): void
    {
        $this->getJson('/api/v1/appointments/private-notes')->assertUnauthorized();
        $this->postJson('/api/v1/appointments/private-notes', [])->assertUnauthorized();
    }
}
