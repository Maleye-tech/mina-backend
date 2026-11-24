<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\ChatReport;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Tests\Feature\Chat\Concerns\InteractsWithChat;
use Tests\TestCase;

class ChatEnhancementsApiTest extends TestCase
{
    use InteractsWithChat;
    use RefreshDatabase;

    public function test_message_with_crisis_keywords_triggers_alert_and_emergency_contacts(): void
    {
        Event::fake([MessageSent::class]);

        [$student, $professional, $conversation] = $this->existingConversation();

        $response = $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Je n\'en peux plus, je suis fatigué de vivre et je pense au suicide.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_crisis', true)
            ->assertJsonStructure([
                'crisis_alert' => [
                    'message',
                    'contacts' => [
                        '*' => ['name', 'number'],
                    ],
                ],
            ]);

        $this->assertNotNull($conversation->fresh()->crisis_flagged_at);
        Event::assertDispatched(MessageSent::class);
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
}
