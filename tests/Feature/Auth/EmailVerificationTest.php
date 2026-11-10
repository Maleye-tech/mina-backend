<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_email_and_marks_the_profile_unverified(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Compte créé. Vérifiez votre adresse e-mail pour la confirmer.')
            ->assertJsonPath('user.email_verified', false);

        $user = User::where('email', 'student@example.test')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_verification_link_marks_the_address_verified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();
        $notification = Notification::sent($user, VerifyEmail::class)->first();
        $verificationUrl = $notification->toMail($user)->actionUrl;

        $response = $this->getJson($verificationUrl);

        $response->assertOk()->assertExactJson([
            'message' => 'Votre adresse e-mail a été vérifiée.',
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_rejects_an_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('api.verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->getJson($url.'&signature=invalid');

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_can_request_a_new_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->postJson('/api/auth/email/verification-notification');

        $response->assertStatus(202)->assertExactJson([
            'message' => 'Un nouveau lien de vérification a été envoyé.',
        ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_does_not_receive_another_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/auth/email/verification-notification');

        $response->assertOk()->assertExactJson([
            'message' => 'Votre adresse e-mail est déjà vérifiée.',
        ]);

        Notification::assertNothingSent();
    }

    public function test_verification_email_resend_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/email/verification-notification');

        $response->assertUnauthorized();
    }
}
