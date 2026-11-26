<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Services\CrisisDetectorService;
use Tests\Feature\Chat\Concerns\InteractsWithChat;
use Tests\TestCase;

class ChatEnhancementsApiTest extends TestCase
{
    use InteractsWithChat;
    use RefreshDatabase;

    public function test_message_with_crisis_keywords_triggers_alert_and_emergency_contacts(): void
    {
        Event::fake([MessageSent::class]);
        $contacts = [['name' => 'Ligne configurée pour le test', 'number' => 'TEST-123']];
        config(['care.emergency_contacts' => $contacts]);

        [$student, $professional, $conversation] = $this->existingConversation();

        $response = $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Je n\'en peux plus, je suis fatigué de vivre et je pense au suicide.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_crisis', true)
            ->assertJsonPath('crisis_alert.contacts', $contacts)
            ->assertJsonMissingPath('data.sender_id')
            ->assertJsonStructure([
                'crisis_alert' => [
                    'message',
                    'contacts' => [
                        '*' => ['name', 'number'],
                    ],
                ],
            ]);

        $this->assertNotNull($conversation->fresh()->crisis_flagged_at);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => ! array_key_exists('sender_id', $event->broadcastWith()));
    }

    public function test_student_can_request_ambassador_orientation(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $ambassador = $this->userWithRole(UserRole::Ambassador, ['pseudonym' => 'Amba-Fatou']);

        $response = $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/chat/request-ambassador')
            ->assertOk()
            ->assertJsonPath('message', 'Vous êtes maintenant connecté avec un pair aidant / ambassadeur formé.')
            ->assertJsonPath('data.is_with_ambassador', true);

        $this->assertDatabaseHas('chat_conversations', [
            'is_with_ambassador' => true,
        ]);
    }

    public function test_student_orientation_never_falls_back_to_a_professional(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $this->verifiedProfessional();

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/chat/request-ambassador')
            ->assertNotFound();

        $this->assertSame(0, Conversation::count());
    }

    public function test_emergency_contacts_default_to_an_empty_configuration(): void
    {
        $configuration = $this->careConfigurationFromEnvironment('[]');
        $this->assertSame([], $configuration['emergency_contacts']);

        config(['care.emergency_contacts' => $configuration['emergency_contacts']]);
        $analysis = app(CrisisDetectorService::class)->analyze('suicide');

        $this->assertSame([], $analysis['emergency_contacts']);
    }

    public function test_emergency_contacts_are_loaded_from_json_environment_configuration(): void
    {
        $contacts = [['name' => 'Ligne configurée pour le test', 'number' => 'TEST-123']];
        $configuration = $this->careConfigurationFromEnvironment(json_encode($contacts, JSON_THROW_ON_ERROR));
        $this->assertSame($contacts, $configuration['emergency_contacts']);

        config(['care.emergency_contacts' => $configuration['emergency_contacts']]);
        $analysis = app(CrisisDetectorService::class)->analyze('suicide');

        $this->assertSame($contacts, $analysis['emergency_contacts']);
    }

    public function test_invalid_emergency_contact_configuration_is_ignored(): void
    {
        $configuration = $this->careConfigurationFromEnvironment('{json invalide');

        $this->assertSame([], $configuration['emergency_contacts']);
    }

    public function test_student_can_delete_own_message(): void
    {
        [$student, $professional, $conversation] = $this->existingConversation();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'body' => 'Message à supprimer',
        ]);

        $this->withToken($this->tokenFor($student))
            ->deleteJson("/api/v1/conversations/{$conversation->id}/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Message supprimé.');

        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
    }

    public function test_student_can_report_message(): void
    {
        [$student, $professional, $conversation] = $this->existingConversation();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $professional->id,
            'body' => 'Propos inapproprié',
        ]);

        $response = $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages/{$message->id}/report", [
                'reason' => 'Propos déplacés et insultants',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Signalement enregistré. Notre équipe de modération va examiner ce message.');

        $this->assertDatabaseHas('chat_reports', [
            'reporter_id' => $student->id,
            'message_id' => $message->id,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function careConfigurationFromEnvironment(string $json): array
    {
        $key = 'CARE_EMERGENCY_CONTACTS';
        $hadEnvironmentValue = array_key_exists($key, $_ENV);
        $hadServerValue = array_key_exists($key, $_SERVER);
        $previousEnvironmentValue = $_ENV[$key] ?? null;
        $previousServerValue = $_SERVER[$key] ?? null;
        $previousProcessValue = getenv($key);

        putenv($key.'='.$json);
        $_ENV[$key] = $json;
        $_SERVER[$key] = $json;

        try {
            return require config_path('care.php');
        } finally {
            if ($hadEnvironmentValue) {
                $_ENV[$key] = $previousEnvironmentValue;
            } else {
                unset($_ENV[$key]);
            }

            if ($hadServerValue) {
                $_SERVER[$key] = $previousServerValue;
            } else {
                unset($_SERVER[$key]);
            }

            putenv($previousProcessValue === false ? $key : $key.'='.$previousProcessValue);
        }
    }
}
