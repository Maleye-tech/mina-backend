<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentSlot;
use Modules\Auth\Enums\UserRole;
use Modules\Notifications\Notifications\AppointmentReminderNotification;
use Modules\Notifications\Notifications\TrialExpiringNotification;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Subscription;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('notifications-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_user_can_view_notifications_and_mark_as_read(): void
    {
        $user = $this->actingAsUser();

        $slot = AppointmentSlot::factory()->create();
        $appointment = Appointment::factory()->create([
            'student_id' => $user->id,
            'slot_id' => $slot->id,
            'professional_id' => $slot->professional_id,
        ]);

        $user->notify(new AppointmentReminderNotification($appointment));

        $response = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data');

        $notificationId = $response->json('data.0.id');

        $this->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('message', 'Notification marquée comme lue.');

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->actingAsUser();

        $slot = AppointmentSlot::factory()->create();
        $appointment = Appointment::factory()->create([
            'student_id' => $user->id,
            'slot_id' => $slot->id,
            'professional_id' => $slot->professional_id,
        ]);

        $user->notify(new AppointmentReminderNotification($appointment));

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/v1/notifications/preferences', [
            'email_notifications' => false,
            'push_notifications' => true,
            'fcm_token' => 'fcm-sample-token-xyz',
        ])
            ->assertOk()
            ->assertJsonPath('preferences.email_notifications', false)
            ->assertJsonPath('preferences.push_notifications', true);

        $this->assertFalse($user->fresh()->email_notifications);
        $this->assertSame('fcm-sample-token-xyz', $user->fresh()->fcm_token);
    }

    public function test_send_appointment_reminders_command(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Student]);
        $slot = AppointmentSlot::factory()->create([
            'starts_at' => now()->addHours(6),
        ]);

        $appointment = Appointment::factory()->create([
            'student_id' => $user->id,
            'slot_id' => $slot->id,
            'professional_id' => $slot->professional_id,
            'scheduled_at' => now()->addHours(6),
            'reminder_sent_at' => null,
        ]);

        Artisan::call('mina:remind-appointments');

        Notification::assertSentTo($user, AppointmentReminderNotification::class);
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_check_trial_expirations_command(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Student]);

        $activeTrial = Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Trial,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addHours(12),
            'trial_ends_at' => now()->addHours(12),
        ]);

        $expiredTrial = Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Trial,
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->subHours(2),
            'trial_ends_at' => now()->subHours(2),
        ]);

        Artisan::call('mina:check-trial-expirations');

        Notification::assertSentTo($user, TrialExpiringNotification::class);
        $this->assertSame(SubscriptionStatus::Expired->value, $expiredTrial->fresh()->status->value);
    }
}
