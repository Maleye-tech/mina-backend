<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Enums\UserRole;
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

    public function test_admin_login_requires_enrollment_before_issuing_a_full_access_token(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_two_factor_setup', true)
            ->assertJsonMissingPath('token');

        $setupToken = $response->json('challenge_token');
        $this->withToken($setupToken)->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_admin_can_enroll_totp_and_exchange_the_setup_token_for_a_full_token(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
        ]);
        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $setupToken = $login->json('challenge_token');

        $setup = $this->withToken($setupToken)->postJson('/api/auth/two-factor/setup');

        $setup->assertOk()->assertJsonStructure(['secret', 'otpauth_url']);
        $secret = $setup->json('secret');
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('otpauth_url'));

        $code = $this->nextTotpCode($secret);
        $confirm = $this->withToken($setupToken)->postJson('/api/auth/two-factor/confirm-setup', [
            'code' => $code,
        ]);
        $confirm->assertOk()->assertJsonPath('message', 'La double authentification est activée.');
        $this->assertNotEmpty($confirm->json('token'));
        $this->assertNotNull($admin->fresh()->two_factor_enabled_at);
        $this->assertNotSame(
            $secret,
            DB::table('users')->where('id', $admin->id)->value('two_factor_secret'),
        );
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'admin-2fa-setup']);
        $this->app['auth']->forgetGuards();
        $this->withToken($confirm->json('token'));
        $profile = $this->getJson('/api/auth/me');
        $profile->assertOk();
    }

    public function test_admin_login_requires_a_totp_challenge_after_enrollment(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => $secret,
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

        $challengeToken = $login->json('challenge_token');
        $this->withToken($challengeToken)->getJson('/api/auth/me')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($challengeToken)->postJson('/api/auth/two-factor/challenge', [
            'code' => $this->nextTotpCode($secret),
        ]);
        $response->assertOk()->assertJsonPath('message', 'Connexion réussie.');
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'admin-2fa-challenge']);
        $this->app['auth']->forgetGuards();
        $profile = $this->withToken($response->json('token'))->getJson('/api/auth/me');
        $profile->assertOk();
    }

    public function test_admin_cannot_reuse_a_totp_code_for_a_new_login_challenge(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
            'two_factor_last_used_counter' => null,
        ]);
        $code = $this->nextTotpCode($secret);

        $firstChallenge = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('challenge_token');

        $this->withToken($firstChallenge)->postJson('/api/auth/two-factor/challenge', [
            'code' => $code,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $secondChallenge = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('challenge_token');

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($secondChallenge)->postJson('/api/auth/two-factor/challenge', [
            'code' => $code,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_filament_totp_enrollment_enables_api_two_factor_and_records_the_current_counter(): void
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

    private function nextTotpCode(string $secret): string
    {
        $google2fa = new Google2FA;

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
    }
}
