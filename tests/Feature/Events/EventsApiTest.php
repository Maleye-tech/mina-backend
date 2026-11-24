<?php

namespace Tests\Feature\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\Events\Enums\EventType;
use Modules\Events\Models\Event;
use Tests\TestCase;

class EventsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('events-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_list_events_with_filters(): void
    {
        Event::factory()->create(['type' => EventType::Workshop, 'is_published' => true]);
        Event::factory()->create(['type' => EventType::Webinar, 'is_published' => true]);

        $response = $this->getJson('/api/v1/events?type=webinar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', EventType::Webinar->value);
    }

    public function test_show_event_includes_replay_if_past(): void
    {
        $event = Event::factory()->past()->create();

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.replay_url', $event->replay_url);
    }

    public function test_student_can_register_to_event(): void
    {
        $student = $this->actingAsUser();
        $event = Event::factory()->create(['max_participants' => 50, 'current_participants' => 0]);

        $response = $this->postJson("/api/v1/events/{$event->id}/register")
            ->assertCreated()
            ->assertJsonPath('registration.status', 'registered');

        $this->assertSame(1, $event->fresh()->current_participants);

        // Verify event show now says is_registered = true
        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.is_registered', true);
    }

    public function test_student_cannot_register_if_event_is_full(): void
    {
        $student = $this->actingAsUser();
        $event = Event::factory()->create(['max_participants' => 10, 'current_participants' => 10]);

        $this->postJson("/api/v1/events/{$event->id}/register")
            ->assertStatus(422);
    }

    public function test_student_can_unregister_from_event(): void
    {
        $student = $this->actingAsUser();
        $event = Event::factory()->create(['max_participants' => 50, 'current_participants' => 0]);

        $this->postJson("/api/v1/events/{$event->id}/register")->assertCreated();
        $this->assertSame(1, $event->fresh()->current_participants);

        $this->deleteJson("/api/v1/events/{$event->id}/register")
            ->assertOk()
            ->assertJsonPath('message', 'Inscription annulée.');

        $this->assertSame(0, $event->fresh()->current_participants);
    }

    public function test_student_can_view_my_registrations(): void
    {
        $student = $this->actingAsUser();
        $event = Event::factory()->create();

        $this->postJson("/api/v1/events/{$event->id}/register")->assertCreated();

        $this->getJson('/api/v1/events/my/registrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.id', $event->id);
    }
}
