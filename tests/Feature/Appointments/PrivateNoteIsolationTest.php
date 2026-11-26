<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Models\PrivateNote;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class PrivateNoteIsolationTest extends TestCase
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
        $token = $user->createToken('pro-note-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_cannot_read_another_professional_note(): void
    {
        $owner = $this->actingAsProfessional();
        $note = PrivateNote::factory()->create([
            'user_id' => $owner->id,
            'body' => 'Note confidentielle.',
        ]);

        $intruder = User::factory()->create(['role' => UserRole::Professional]);
        ProfessionalProfile::query()->create([
            'user_id' => $intruder->id,
            'display_name' => $intruder->name,
            'specialty' => 'psychologue',
            'languages' => ['Français'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ]);
        $token = $intruder->createToken('intruder-pro-test', ['full-access']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);

        $this->patchJson("/api/v1/appointments/private-notes/{$note->id}", [
            'body' => 'Tentative de modification.',
        ])->assertNotFound();
        $this->deleteJson("/api/v1/appointments/private-notes/{$note->id}")->assertNotFound();
    }

    public function test_private_note_body_is_encrypted(): void
    {
        $user = $this->actingAsProfessional();
        $note = PrivateNote::factory()->create([
            'user_id' => $user->id,
            'body' => 'Contenu sensible.',
        ]);

        $this->assertDatabaseHas('private_notes', [
            'id' => $note->id,
        ]);

        // Le corps doit être stocké chiffré
        $stored = \DB::table('private_notes')->where('id', $note->id)->value('body');
        $this->assertNotSame('Contenu sensible.', $stored);
    }

    public function test_professional_can_crud_own_notes(): void
    {
        $user = $this->actingAsProfessional();

        $this->postJson('/api/v1/appointments/private-notes', [
            'body' => 'Note de séance.',
        ])->assertCreated()
            ->assertJsonPath('note.body', 'Note de séance.');

        $noteId = PrivateNote::query()->first()->id;

        $this->getJson('/api/v1/appointments/private-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/appointments/private-notes/{$noteId}", [
            'body' => 'Note mise à jour.',
        ])->assertOk()
            ->assertJsonPath('note.body', 'Note mise à jour.');

        $this->deleteJson("/api/v1/appointments/private-notes/{$noteId}")
            ->assertOk();

        $this->assertDatabaseMissing('private_notes', ['id' => $noteId]);
    }
}
