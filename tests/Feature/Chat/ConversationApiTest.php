<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Enums\AppointmentStatus;
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
        $professional = $this->verifiedProfessional();

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $professional->id])
            ->assertCreated()
            ->assertJsonPath('message', 'Conversation ouverte.')
            ->assertJsonPath('data.participant.id', $professional->id)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_a_professional_cannot_start_an_arbitrary_conversation_with_a_student(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $professional = $this->verifiedProfessional();

        $this->withToken($this->tokenFor($professional))
            ->postJson('/api/v1/conversations', ['participant_id' => $student->id])
            ->assertNotFound();

        $this->assertSame(0, Conversation::count());
    }

    public function test_starting_the_same_conversation_twice_returns_the_existing_one(): void
    {
        $student = $this->userWithRole(UserRole::Student);
        $professional = $this->userWithRole(UserRole::Professional);

        $first = $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $professional->id])
            ->assertCreated();

        $this->withToken($this->tokenFor($student))
            ->postJson('/api/v1/conversations', ['participant_id' => $professional->id])
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

    public function test_a_professional_can_open_an_existing_appointment_conversation(): void
    {
        [$student, $professional, , $conversation] = $this->appointmentConversation();

        $this->withToken($this->tokenFor($professional))
            ->postJson('/api/v1/conversations', ['participant_id' => $student->id])
            ->assertOk()
            ->assertJsonPath('message', 'Conversation existante.')
            ->assertJsonPath('data.id', $conversation->id);

        $this->assertSame(1, Conversation::count());
    }

    public function test_professional_conversation_list_only_contains_authorized_appointments(): void
    {
        $professional = $this->verifiedProfessional();
        $confirmed = $this->appointmentConversation(null, $professional, AppointmentStatus::Confirmed)[3];
        $completed = $this->appointmentConversation(null, $professional, AppointmentStatus::Completed)[3];
        $this->appointmentConversation(null, $professional, AppointmentStatus::Requested);
        $this->appointmentConversation(null, $professional, AppointmentStatus::Declined);
        $this->appointmentConversation(null, $this->verifiedProfessional(), AppointmentStatus::Confirmed);
        $this->existingConversation(null, $professional);

        $response = $this->withToken($this->tokenFor($professional))
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$confirmed->id, $completed->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_professional_sees_only_anonymized_student_identity(): void
    {
        $student = $this->userWithRole(UserRole::Student, [
            'name' => 'Nom Réel Secret',
            'email' => 'secret@universite.sn',
            'university' => 'Université Secrète',
            'pseudonym' => 'ColombeSereine',
        ]);
        [$student, $professional, , $conversation] = $this->appointmentConversation($student);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $student->id,
            'body' => 'Bonjour Docteur.',
        ]);

        $response = $this->withToken($this->tokenFor($professional))
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.participant.name', 'ColombeSereine')
            ->assertJsonPath('data.participant.pseudonym', 'ColombeSereine')
            ->assertJsonMissingPath('data.last_message.sender_id');

        $anonymousId = $response->json('data.participant.id');
        $this->assertStringStartsWith('anon_', $anonymousId);
        $this->assertNotSame($student->id, $anonymousId);
        foreach (['Nom Réel Secret', 'secret@universite.sn', 'Université Secrète'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_professional_cannot_access_unlinked_or_unowned_conversations(): void
    {
        $professional = $this->verifiedProfessional();
        [$student, , , $unlinked] = $this->appointmentConversation(null, $professional);
        $unlinked->update(['appointment_id' => null]);

        $this->withToken($this->tokenFor($professional))
            ->getJson("/api/v1/conversations/{$unlinked->id}")
            ->assertNotFound();
        $this->withToken($this->tokenFor($professional))
            ->postJson("/api/v1/conversations/{$unlinked->id}/messages", ['body' => 'Accès interdit.'])
            ->assertNotFound();

        [$student, $owner, , $unowned] = $this->appointmentConversation();
        $intruder = $this->verifiedProfessional();
        $unowned->update([
            'participant_one_id' => min($student->id, $intruder->id),
            'participant_two_id' => max($student->id, $intruder->id),
        ]);

        $this->withToken($this->tokenFor($intruder))
            ->getJson("/api/v1/conversations/{$unowned->id}")
            ->assertNotFound();
        $this->withToken($this->tokenFor($intruder))
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertNotSame($owner->id, $intruder->id);
        $this->assertSame(0, Message::count());
    }

    #[DataProvider('ineligibleProfessionalStates')]
    public function test_professional_chat_requires_an_active_verified_profile_and_accepted_charter(string $state): void
    {
        [$student, $professional, , $conversation] = $this->appointmentConversation();

        match ($state) {
            'inactive' => $professional->update(['is_active' => false]),
            'unverified' => $professional->professionalCareProfile->update(['is_verified' => false]),
            'unchartered' => $professional->professionalCareProfile->update(['charter_accepted_at' => null]),
        };

        $this->withToken($this->tokenFor($professional))
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertForbidden();
        $this->withToken($this->tokenFor($professional))
            ->getJson('/api/v1/conversations')
            ->assertForbidden();
        $this->withToken($this->tokenFor($professional))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Message interdit.'])
            ->assertForbidden();
        $this->withToken($this->tokenFor($professional))
            ->postJson('/api/v1/conversations', ['participant_id' => $student->id])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function ineligibleProfessionalStates(): array
    {
        return [
            'inactive' => ['inactive'],
            'unverified' => ['unverified'],
            'unchartered' => ['unchartered'],
        ];
    }

    #[DataProvider('professionalConversationStatuses')]
    public function test_professional_read_and_send_permissions_follow_appointment_status(
        AppointmentStatus $status,
        int $readStatus,
        int $sendStatus,
    ): void {
        [, $professional, , $conversation] = $this->appointmentConversation(status: $status);

        $this->withToken($this->tokenFor($professional))
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus($readStatus);
        $this->withToken($this->tokenFor($professional))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Message professionnel.'])
            ->assertStatus($sendStatus);

        $this->assertSame($sendStatus === 201 ? 1 : 0, Message::count());
    }

    /**
     * @return array<string, array<int, AppointmentStatus|int>>
     */
    public static function professionalConversationStatuses(): array
    {
        return [
            'requested' => [AppointmentStatus::Requested, 403, 403],
            'confirmed' => [AppointmentStatus::Confirmed, 200, 201],
            'completed' => [AppointmentStatus::Completed, 200, 403],
        ];
    }

    #[DataProvider('studentConversationStatuses')]
    public function test_student_read_and_send_permissions_follow_appointment_status(
        AppointmentStatus $status,
        int $readStatus,
        int $sendStatus,
    ): void {
        [$student, , , $conversation] = $this->appointmentConversation(status: $status);

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus($readStatus);
        $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Message étudiant.'])
            ->assertStatus($sendStatus);

        $this->assertSame($sendStatus === 201 ? 1 : 0, Message::count());
    }

    /**
     * @return array<string, array<int, AppointmentStatus|int>>
     */
    public static function studentConversationStatuses(): array
    {
        return [
            'requested' => [AppointmentStatus::Requested, 403, 403],
            'confirmed' => [AppointmentStatus::Confirmed, 200, 201],
            'completed' => [AppointmentStatus::Completed, 200, 403],
        ];
    }

    public function test_reporting_a_message_from_another_conversation_is_rejected(): void
    {
        [$student, , $conversation] = $this->existingConversation();
        [, $otherProfessional, $otherConversation] = $this->existingConversation();
        $message = Message::factory()->create([
            'conversation_id' => $otherConversation->id,
            'sender_id' => $otherProfessional->id,
            'body' => 'Message privé d’une autre conversation.',
        ]);

        $this->withToken($this->tokenFor($student))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages/{$message->id}/report", [
                'reason' => 'Signalement de test.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('chat_reports', 0);
    }

    public function test_message_pagination_rejects_invalid_values(): void
    {
        [$student, , $conversation] = $this->existingConversation();

        $this->withToken($this->tokenFor($student))
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?per_page=abc")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
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
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonMissingPath('data.sender_id');

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
