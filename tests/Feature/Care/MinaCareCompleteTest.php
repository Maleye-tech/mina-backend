<?php

namespace Tests\Feature\Care;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Appointments\Database\Seeders\MinaCareDemoSeeder;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Enums\SlotMode;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Models\SessionNote;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Appointments\Notifications\NewAppointmentRequestedNotification;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Message;
use Tests\TestCase;

class MinaCareCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_demo_seeder_creates_working_passwords_in_demo_environments(): void
    {
        $this->seed(MinaCareDemoSeeder::class);

        $professional = User::query()->where('email', 'psy.aminata@example.com')->firstOrFail();
        $student = User::query()->where('email', 'etudiant.fatou@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password', $professional->password));
        $this->assertTrue(Hash::check('password', $student->password));
    }

    public function test_demo_seeder_does_not_create_accounts_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        app(MinaCareDemoSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_pagination_and_legacy_status_are_rejected(): void
    {
        $this->authenticate($this->createStudent());

        $this->getJson('/api/professionals?per_page=abc')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/appointments?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/appointments?per_page=-1')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/appointments?status=inconnu')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    private function authenticate(User $user): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user, ['full-access']);
    }

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Student,
            'pseudonym' => 'Etudiant'.Str::random(8),
        ], $attributes));
    }

    private function createVerifiedPro(array $attributes = []): array
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $profile = ProfessionalProfile::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Dr. '.$user->name,
            'specialty' => ProfessionalSpecialty::Psychologue,
            'languages' => ['Français', 'Wolof'],
            'is_verified' => true,
            'charter_accepted_at' => now(),
        ], $attributes));
        $legacy = app(ProfessionalProfileService::class)->syncLegacyProfile($profile);

        return ['user' => $user, 'profile' => $profile, 'legacy' => $legacy];
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

    private function createAppointment(User $student, array $professional, AppointmentStatus $status, ?AvailabilitySlot $slot = null): Appointment
    {
        return Appointment::create([
            'student_id' => $student->id,
            'professional_id' => $professional['legacy']->id,
            'professional_user_id' => $professional['user']->id,
            'availability_slot_id' => $slot?->id,
            'scheduled_at' => $slot?->starts_at ?? now()->addDay(),
            'duration_minutes' => 45,
            'status' => $status,
            'consultation_type' => 'visio',
        ]);
    }

    public function test_student_can_book_availability_slot(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $slot = $this->createSlot($professional['user']);
        $this->authenticate($student);

        $this->postJson('/api/appointments', [
            'availability_slot_id' => $slot->id,
            'reason' => 'Insomnie et stress intense avant les partiels',
        ])
            ->assertCreated()
            ->assertJsonPath('appointment.status', AppointmentStatus::Requested->value)
            ->assertJsonPath('appointment.professional.id', $professional['user']->id);

        $this->assertTrue($slot->fresh()->is_booked);
        $this->assertDatabaseHas('appointments', [
            'student_id' => $student->id,
            'professional_id' => $professional['legacy']->id,
            'professional_user_id' => $professional['user']->id,
            'availability_slot_id' => $slot->id,
            'status' => AppointmentStatus::Requested->value,
        ]);
        Notification::assertSentTo($professional['user'], NewAppointmentRequestedNotification::class);
    }

    public function test_double_booking_same_slot_is_rejected(): void
    {
        $student1 = $this->createStudent();
        $student2 = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $slot = $this->createSlot($professional['user']);

        $this->authenticate($student1);
        $this->postJson('/api/appointments', ['availability_slot_id' => $slot->id])
            ->assertCreated();

        $this->authenticate($student2);
        $this->postJson('/api/appointments', ['availability_slot_id' => $slot->id])
            ->assertStatus(422);

        $this->assertSame(1, Appointment::where('availability_slot_id', $slot->id)->count());
    }

    public function test_professional_cannot_read_appointments_of_another_professional(): void
    {
        $student = $this->createStudent();
        $professionalA = $this->createVerifiedPro(['display_name' => 'Dr. A']);
        $professionalB = $this->createVerifiedPro(['display_name' => 'Dr. B']);
        $appointment = $this->createAppointment($student, $professionalA, AppointmentStatus::Confirmed);
        $this->authenticate($professionalB['user']);

        $this->getJson("/api/pro/appointments/{$appointment->id}")
            ->assertNotFound();

        $response = $this->getJson('/api/pro/appointments')->assertOk();
        $this->assertNotContains($appointment->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_student_identity_is_never_exposed_to_professional(): void
    {
        $student = $this->createStudent([
            'name' => 'Aminata Jeanne Diop',
            'email' => 'aminata.secret@universite.sn',
            'pseudonym' => 'ColombeSereine',
            'university' => 'Université secrète de Dakar',
        ]);
        $professional = $this->createVerifiedPro();
        $appointment = $this->createAppointment($student, $professional, AppointmentStatus::Requested);
        $this->authenticate($professional['user']);

        $response = $this->getJson("/api/pro/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.student.pseudonym', 'ColombeSereine');

        $this->assertStringStartsWith('anon_', $response->json('data.student.id'));
        $this->assertNotSame((string) $student->id, $response->json('data.student.id'));
        $content = $response->getContent();
        foreach (['Aminata', 'Jeanne', 'Diop', 'aminata.secret@universite.sn', 'universite.sn', 'Université secrète de Dakar'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }
    }

    public function test_chat_is_forbidden_if_appointment_is_not_confirmed(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $appointment = $this->createAppointment($student, $professional, AppointmentStatus::Requested);
        $this->authenticate($student);

        $this->postJson("/api/appointments/{$appointment->id}/messages", ['body' => 'Bonjour Docteur'])
            ->assertForbidden();
    }

    public function test_chat_is_allowed_once_confirmed_and_body_is_encrypted_at_rest(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $appointment = $this->createAppointment($student, $professional, AppointmentStatus::Confirmed);
        $body = 'Bonjour Docteur, j’ai une question avant notre séance.';

        $this->authenticate($student);
        $this->postJson("/api/appointments/{$appointment->id}/messages", ['body' => $body])
            ->assertCreated()
            ->assertJsonMissingPath('data.sender_id')
            ->assertJsonPath('data.is_me', true);

        $storedBody = DB::table('chat_messages')->where('conversation_id', DB::table('chat_conversations')->where('appointment_id', $appointment->id)->value('id'))->value('body');
        $this->assertNotSame($body, $storedBody);

        $this->authenticate($professional['user']);
        $response = $this->getJson("/api/pro/appointments/{$appointment->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', $body)
            ->assertJsonPath('data.0.is_me', false)
            ->assertJsonMissingPath('data.0.sender_id');
        $this->assertTrue($response->json('data.0.is_me') === false);

        $this->postJson("/api/pro/appointments/{$appointment->id}/messages", ['body' => 'Merci pour votre message.'])
            ->assertCreated()
            ->assertJsonPath('data.is_me', true)
            ->assertJsonMissingPath('data.sender_id');

        $this->getJson("/api/pro/appointments/{$appointment->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.sender_id')
            ->assertJsonMissingPath('data.1.sender_id');
    }

    public function test_completed_appointment_keeps_history_but_closes_new_messages(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $appointment = $this->createAppointment($student, $professional, AppointmentStatus::Completed);
        $this->authenticate($student);

        $this->getJson("/api/appointments/{$appointment->id}/messages")->assertOk();
        $this->postJson("/api/appointments/{$appointment->id}/messages", ['body' => 'Message après la séance'])
            ->assertForbidden();

        $this->authenticate($professional['user']);
        $this->getJson("/api/pro/appointments/{$appointment->id}/messages")->assertOk();
        $this->postJson("/api/pro/appointments/{$appointment->id}/messages", ['body' => 'Message professionnel après la séance'])
            ->assertForbidden();

        $this->assertSame(0, Message::query()->count());
    }

    public function test_session_note_is_only_readable_by_authoring_professional_and_is_encrypted(): void
    {
        $student = $this->createStudent();
        $author = $this->createVerifiedPro(['display_name' => 'Dr. Auteur']);
        $other = $this->createVerifiedPro(['display_name' => 'Dr. Intrus']);
        $appointment = $this->createAppointment($student, $author, AppointmentStatus::Confirmed);
        $body = 'Note clinique hautement confidentielle : évaluation du risque.';

        $this->authenticate($author['user']);
        $this->putJson("/api/pro/appointments/{$appointment->id}/note", ['body' => $body])
            ->assertOk()
            ->assertJsonPath('note.body', $body);

        $this->assertNotSame($body, DB::table('session_notes')->where('appointment_id', $appointment->id)->value('body'));
        $this->getJson("/api/pro/appointments/{$appointment->id}/note")
            ->assertOk()
            ->assertJsonPath('note.body', $body);
        $this->authenticate($other['user']);
        $this->getJson("/api/pro/appointments/{$appointment->id}/note")
            ->assertNotFound();
    }

    public function test_student_cannot_create_a_professional_session_note(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $appointment = $this->createAppointment($student, $professional, AppointmentStatus::Confirmed);
        $this->authenticate($student);

        $this->putJson("/api/pro/appointments/{$appointment->id}/note", ['body' => 'Tentative non autorisée.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('session_notes', ['appointment_id' => $appointment->id]);
    }

    public function test_unverified_or_unchartered_professional_cannot_use_professional_tools(): void
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);
        ProfessionalProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Dr. Non vérifié',
            'specialty' => ProfessionalSpecialty::Psychologue,
            'is_verified' => false,
            'charter_accepted_at' => null,
        ]);
        $this->authenticate($user);

        $this->postJson('/api/pro/availability', [
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(10, 45)->toIso8601String(),
            'mode' => 'visio',
        ])
            ->assertForbidden();
        $this->getJson('/api/pro/appointments')->assertForbidden();
    }

    public function test_professional_appointment_and_alert_query_parameters_are_validated(): void
    {
        $professional = $this->createVerifiedPro();
        $this->authenticate($professional['user']);

        foreach (['0', '51', 'abc', '1.5'] as $perPage) {
            $this->getJson('/api/pro/appointments?per_page='.$perPage)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('per_page');
            $this->getJson('/api/pro/alerts?per_page='.$perPage)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('per_page');
        }

        $this->getJson('/api/pro/appointments?status=invalide')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->getJson('/api/pro/alerts?status=invalide')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson('/api/pro/appointments?per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
        $this->getJson('/api/pro/alerts?status=open&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_professional_cannot_self_verify_through_profile_update(): void
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $this->authenticate($user);

        $this->putJson('/api/pro/profile', [
            'display_name' => 'Dr. Test',
            'specialty' => 'psychologue',
            'languages' => ['Français'],
            'is_verified' => true,
        ])->assertOk();

        $this->assertFalse($user->professionalCareProfile->fresh()->is_verified);
    }

    public function test_catalogue_excludes_unverified_or_unchartered_professionals(): void
    {
        $student = $this->createStudent();
        $verified = $this->createVerifiedPro(['display_name' => 'Dr. Visible']);
        $uncharteredUser = User::factory()->create(['role' => UserRole::Professional]);
        ProfessionalProfile::create([
            'user_id' => $uncharteredUser->id,
            'display_name' => 'Dr. Sans charte',
            'specialty' => ProfessionalSpecialty::Psychologue,
            'is_verified' => true,
        ]);

        $this->authenticate($student);
        $response = $this->getJson('/api/professionals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $verified['user']->id);
        $this->assertSame($verified['user']->id, $response->json('data.0.id'));
    }

    public function test_professional_can_propose_a_slot_and_student_can_accept_it(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $original = $this->createSlot($professional['user'], ['is_booked' => true]);
        $proposed = $this->createSlot($professional['user'], [
            'starts_at' => now()->addDays(3)->setTime(15, 0),
            'ends_at' => now()->addDays(3)->setTime(15, 45),
        ]);
        $appointment = $this->createAppointment($student, $professional, AppointmentStatus::Requested, $original);

        $this->authenticate($professional['user']);
        $this->postJson("/api/pro/appointments/{$appointment->id}/propose", ['availability_slot_id' => $proposed->id])
            ->assertOk()
            ->assertJsonPath('appointment.status', 'proposed');

        $this->assertFalse($original->fresh()->is_booked);
        $this->assertTrue($proposed->fresh()->is_booked);

        $this->authenticate($student);
        $this->postJson("/api/appointments/{$appointment->id}/accept-proposal")
            ->assertOk()
            ->assertJsonPath('appointment.status', 'confirmed');

        $this->assertDatabaseHas('chat_conversations', ['appointment_id' => $appointment->id]);
    }

    public function test_overlapping_availability_is_rejected(): void
    {
        $professional = $this->createVerifiedPro();
        $this->createSlot($professional['user']);
        $this->authenticate($professional['user']);

        $this->postJson('/api/pro/availability', [
            'starts_at' => now()->addDays(2)->setTime(10, 15)->toIso8601String(),
            'ends_at' => now()->addDays(2)->setTime(11, 0)->toIso8601String(),
            'mode' => 'chat',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_urgent_alert_creation_and_handling(): void
    {
        $student = $this->createStudent();
        $professional = $this->createVerifiedPro();

        $this->authenticate($student);
        $response = $this->postJson('/api/alerts', ['message' => 'Angoisse aiguë, sensation de malaise.'])
            ->assertCreated();
        $alertId = $response->json('alert.id');

        $this->authenticate($professional['user']);
        $this->getJson('/api/pro/alerts?status=open')
            ->assertOk()
            ->assertJsonPath('data.0.id', $alertId)
            ->assertJsonPath('data.0.student.pseudonym', $student->pseudonym);

        $this->postJson("/api/pro/alerts/{$alertId}/handle")
            ->assertOk()
            ->assertJsonPath('alert.status', 'handled');

        $this->assertDatabaseHas('urgent_alerts', [
            'id' => $alertId,
            'status' => 'handled',
            'handled_by' => $professional['user']->id,
        ]);
    }

    public function test_urgent_alert_message_is_encrypted_at_rest(): void
    {
        $student = $this->createStudent();
        $body = 'Contenu confidentiel de l’alerte urgente.';
        $this->authenticate($student);

        $response = $this->postJson('/api/alerts', ['message' => $body])
            ->assertCreated()
            ->assertJsonPath('alert.message', $body);

        $storedBody = DB::table('urgent_alerts')->where('id', $response->json('alert.id'))->value('message');
        $this->assertNotSame($body, $storedBody);
        $this->assertStringNotContainsString($body, $storedBody);
        $this->assertSame($body, UrgentAlert::query()->findOrFail($response->json('alert.id'))->message);
    }

    public function test_student_cannot_attach_an_alert_to_another_students_appointment(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $professional = $this->createVerifiedPro();
        $appointment = $this->createAppointment($otherStudent, $professional, AppointmentStatus::Confirmed);

        $this->authenticate($student);
        $this->postJson('/api/alerts', ['appointment_id' => $appointment->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('appointment_id');
    }

    public function test_demo_seeder_can_run_twice_without_duplicating_care_data(): void
    {
        $this->seed(MinaCareDemoSeeder::class);
        $this->seed(MinaCareDemoSeeder::class);

        $this->assertSame(3, ProfessionalProfile::where('is_verified', true)->count());
        $this->assertSame(6, Appointment::count());
        $this->assertSame(1, SessionNote::count());
        $this->assertSame(1, UrgentAlert::count());
    }
}
