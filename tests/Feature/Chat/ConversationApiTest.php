<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Chat\Concerns\InteractsWithChat;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use InteractsWithChat;
    use RefreshDatabase;

    public function test_conversations_require_authentication(): void
    {
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
    }

    public function test_index_returns_only_the_current_user_conversations(): void
    {
        [$student, $professional, $conversation] = $this->existingConversation();
        $this->existingConversation();

        $this->withToken($this->tokenFor($student))
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.participant.id', $professional->id)
            ->assertJsonPath('data.0.participant.role', UserRole::Professional->value)
            ->assertJsonPath('data.0.participant.role_label', UserRole::Professional->getLabel())
            ->assertJsonPath('data.0.unread_count', 0);
    }

    #[DataProvider('disallowedRoles')]
    public function test_index_rejects_roles_outside_the_care_scope(UserRole $role): void
    {
        $user = $this->userWithRole($role);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/conversations')
            ->assertForbidden();
    }

    /**
     * @return array<string, array<int, UserRole>>
     */
    public static function disallowedRoles(): array
    {
        return [
            'admin' => [UserRole::Admin],
            'partner' => [UserRole::Partner],
        ];
    }

    public function test_a_student_can_start_a_conversation_with_a_professional(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $professional = $this->userWithRole(UserRole::Professional);

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $professional->id])
            ->assertCreated()
            ->assertJsonPath('message', 'Conversation ouverte.')
            ->assertJsonPath('data.participant.id', $professional->id)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_a_professional_can_start_a_conversation_with_a_student(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $professional = $this->userWithRole(UserRole::Professional);

        $this->withToken($this->tokenFor($professional))
            ->postJson('/api/v1/conversations', ['participant_id' => $student->id])
            ->assertCreated()
            ->assertJsonPath('data.participant.id', $student->id);
    }

    public function test_starting_the_same_conversation_twice_returns_the_existing_one(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $professional = $this->userWithRole(UserRole::Professional);

        $first = $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $professional->id])
            ->assertCreated();

        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor($professional))
            ->postJson('/api/v1/conversations', ['participant_id' => $student->id])
            ->assertOk()
            ->assertJsonPath('message', 'Conversation existante.')
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Conversation::count());
    }

    public function test_cannot_start_a_conversation_with_oneself(): void
    {
        $student = $this->userWithRole(UserRole::Student);

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $student->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participant_id']);
    }

    public function test_cannot_start_a_conversation_with_a_user_of_the_same_role(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $otherStudent = $this->userWithRole(UserRole::Student);

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $otherStudent->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participant_id']);
    }

    public function test_starting_a_conversation_requires_a_participant(): void
    {
        $student = $this->userWithRole(UserRole::Student);

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participant_id']);
    }

    public function test_a_conversation_with_a_non_participant_is_not_found(): void
    {
        [, , $conversation] = $this->existingConversation();
        $stranger = $this->userWithRole(UserRole::Student);

        $this->withToken($this->tokenFor($stranger))
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_a_participant_can_view_the_conversation_detail(): void
    {
        [$student, $professional, $conversation] = $this->existingConversation();

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.participant.id', $professional->id)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_index_lists_the_most_recently_active_conversation_first(): void
    {
        [$student, , $older] = $this->existingConversation();
        [, , $newer] = $this->existingConversation($student);

        $older->update(['last_message_at' => now()->subMinutes(10)]);
        $newer->update(['last_message_at' => now()]);

        $this->withToken($this->tokenFor($student))
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_messages_are_returned_newest_first_and_paginated(): void
    {
        [$student, , $conversation] = $this->existingConversation();

        $oldest = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'body' => 'Premier message',
        ]);
        $middle = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'body' => 'Deuxième message',
        ]);
        $latest = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'body' => 'Troisième message',
        ]);

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('meta.total', 3);

        $this->assertSame($oldest->id, Message::orderBy('id')->first()->id);
    }

    public function test_reading_messages_marks_incoming_messages_as_read(): void
    {
        [$student, $professional, $conversation] = $this->existingConversation();

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $professional->id,
            'body' => 'Bonjour, comment allez-vous ?',
        ]);

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull(Message::first()->read_at);
    }

    public function test_a_participant_can_send_a_message(): void
    {
        [$student, , $conversation] = $this->existingConversation();

        $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Journée très chargée...',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Message envoyé.')
            ->assertJsonPath('data.body', 'Journée très chargée...')
            ->assertJsonPath('data.sender_id', $student->id);

        $conversation->refresh();

        $this->assertNotNull($conversation->last_message_at);
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_sending_a_message_requires_a_body(): void
    {
        [$student, , $conversation] = $this->existingConversation();

        $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_a_message_body_cannot_exceed_5000_characters(): void
    {
        [$student, , $conversation] = $this->existingConversation();

        $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => str_repeat('a', 5001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_a_non_participant_cannot_read_or_send_messages(): void
    {
        [, , $conversation] = $this->existingConversation();
        $stranger = $this->userWithRole(UserRole::Professional);

        $this->withToken($this->tokenFor($stranger))
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertNotFound();

        $this->withToken($this->tokenFor($stranger))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Intrusion'])
            ->assertNotFound();

        $this->assertSame(0, Message::count());
    }

    public function test_a_token_without_full_access_is_rejected(): void
    {
        $student = $this->userWithRole(UserRole::Student);

        $this->withToken($this->tokenFor($student, ['read']))
            ->getJson('/api/v1/conversations')
            ->assertForbidden();
    }
}
