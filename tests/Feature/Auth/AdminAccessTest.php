<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Enums\UserRole;
use Modules\Auth\Notifications\AdminLoginCode;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_ambassador_account_and_send_setup_links(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/managed-users', [
                'name' => 'Mina Ambassador',
                'email' => 'ambassador@example.test',
                'role' => UserRole::Ambassador->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', UserRole::Ambassador->value)
            ->assertJsonPath('user.email_verified', false);

        $createdUser = User::where('email', 'ambassador@example.test')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'role' => UserRole::Ambassador->value,
        ]);
        Notification::assertSentTo($createdUser, ResetPassword::class);
        Notification::assertSentTo($createdUser, VerifyEmail::class);
    }

    public function test_admin_cannot_create_another_admin_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/managed-users', [
                'name' => 'Another Admin',
                'email' => 'another-admin@example.test',
                'role' => UserRole::Admin->value,
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'another-admin@example.test']);
    }

    public function test_non_admin_cannot_create_managed_accounts(): void
    {
        $student = User::factory()->create();
        $token = $student->createToken('student-session');

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/managed-users', [
                'name' => 'Mina Professional',
                'email' => 'professional@example.test',
                'role' => UserRole::Professional->value,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'professional@example.test']);
    }

    public function test_admin_login_returns_a_challenge_token_and_sends_an_email_code(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_two_factor', true)
            ->assertJsonPath('requires_two_factor_setup', false)
            ->assertJsonMissingPath('token');

        Notification::assertSentTo($admin, AdminLoginCode::class, function (AdminLoginCode $notification): bool {
            return preg_match('/^\d{6}$/', $notification->code) === 1;
        });

        $challengeToken = $response->json('challenge_token');
        $this->withToken($challengeToken)->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_admin_can_exchange_the_email_code_for_a_full_token(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $challengeToken = $login->json('challenge_token');
        $code = Notification::sent($admin, AdminLoginCode::class)->first()->code;

        $response = $this->withToken($challengeToken)->postJson('/api/auth/two-factor/challenge', [
            'code' => $code,
        ]);

        $response->assertOk()->assertJsonPath('message', 'Connexion réussie.');
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'admin-email-challenge']);
        $this->assertNull($admin->fresh()->login_code_hash);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))->getJson('/api/auth/me')->assertOk();
    }

    public function test_admin_login_sends_an_email_code_even_when_totp_is_enrolled(): void
    {
        Notification::fake();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_enabled_at' => now(),
            'two_factor_last_used_counter' => null,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('requires_two_factor', true)
            ->assertJsonMissingPath('token');

        Notification::assertSentTo($admin, AdminLoginCode::class);

        $challengeToken = $login->json('challenge_token');
        $code = Notification::sent($admin, AdminLoginCode::class)->first()->code;

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($challengeToken)->postJson('/api/auth/two-factor/challenge', [
            'code' => $code,
        ]);

        $response->assertOk()->assertJsonPath('message', 'Connexion réussie.');
    }

    public function test_admin_cannot_reuse_an_email_code_for_a_new_login_challenge(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $firstChallenge = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('challenge_token');
        $firstCode = Notification::sent($admin, AdminLoginCode::class)->first()->code;

        $this->withToken($firstChallenge)->postJson('/api/auth/two-factor/challenge', [
            'code' => $firstCode,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $secondChallenge = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('challenge_token');

        $this->withToken($secondChallenge)->postJson('/api/auth/two-factor/challenge', [
            'code' => $firstCode,
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_admin_email_code_expires_after_ten_minutes(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $challengeToken = $login->json('challenge_token');
        $code = Notification::sent($admin, AdminLoginCode::class)->first()->code;

        $this->travel(11)->minutes();

        $this->withToken($challengeToken)->postJson('/api/auth/two-factor/challenge', [
            'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_admin_email_code_is_invalidated_after_five_failed_attempts(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $challengeToken = $login->json('challenge_token');
        $code = Notification::sent($admin, AdminLoginCode::class)->first()->code;
        $wrongCode = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withToken($challengeToken)->postJson('/api/auth/two-factor/challenge', [
                'code' => $wrongCode,
            ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
        }

        $this->withToken($challengeToken)->postJson('/api/auth/two-factor/challenge', [
            'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_filament_totp_enrollment_stores_the_secret_and_counter(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $secret = (new Google2FA)->generateSecretKey();

        $admin->saveAppAuthenticationSecret($secret);

        $admin->refresh();
        $this->assertSame($secret, $admin->two_factor_secret);
        $this->assertNotNull($admin->two_factor_enabled_at);
        $this->assertNotNull($admin->two_factor_last_used_counter);
    }

    public function test_disabling_filament_totp_revokes_api_sessions_and_clears_recovery_state(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_enabled_at' => now(),
            'two_factor_last_used_counter' => 123,
            'app_authentication_recovery_codes' => ['recovery-code'],
        ]);
        $token = $admin->createToken('admin-session');

        $admin->saveAppAuthenticationSecret(null);

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_enabled_at);
        $this->assertNull($admin->two_factor_last_used_counter);
        $this->assertNull($admin->app_authentication_recovery_codes);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
