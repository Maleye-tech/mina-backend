<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_link_to_the_frontend(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://mina.example']);

        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()->assertExactJson([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation sera envoyé.',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->actionUrl === 'https://mina.example/reset-password?'.http_build_query([
                'token' => $notification->token,
                'email' => $user->email,
            ]);
        });
    }

    public function test_forgot_password_does_not_disclose_whether_an_email_exists(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@example.test',
        ]);

        $response->assertOk()->assertExactJson([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation sera envoyé.',
        ]);

        Notification::assertNothingSent();
    }

    public function test_reset_password_changes_the_password_and_revokes_existing_tokens(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('existing-session');
        $resetToken = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertOk()->assertExactJson([
            'message' => 'Votre mot de passe a été réinitialisé avec succès.',
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->accessToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue(password_verify('newPassword123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'token' => 'Le lien de réinitialisation est invalide ou a expiré.',
        ]);

        $this->assertSame($originalPassword, $user->fresh()->password);
    }
}
