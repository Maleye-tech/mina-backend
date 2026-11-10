<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password_with_their_current_password(): void
    {
        $user = User::factory()->create(['password' => 'oldPassword123']);
        $token = $user->createToken('test-session');

        $response = $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/password', [
                'current_password' => 'oldPassword123',
                'password' => 'newPassword123',
                'password_confirmation' => 'newPassword123',
            ]);

        $response->assertOk()->assertExactJson([
            'message' => 'Votre mot de passe a été modifié. Veuillez vous reconnecter.',
        ]);

        $this->assertTrue(password_verify('newPassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'oldPassword123']);
        $originalPassword = $user->password;
        $token = $user->createToken('test-session');

        $response = $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/password', [
                'current_password' => 'incorrectPassword123',
                'password' => 'newPassword123',
                'password_confirmation' => 'newPassword123',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'current_password' => 'Le mot de passe actuel est incorrect.',
        ]);

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_password_change_requires_authentication(): void
    {
        $response = $this->patchJson('/api/auth/password', [
            'current_password' => 'oldPassword123',
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_their_account_after_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'oldPassword123']);
        $token = $user->createToken('test-session');

        $response = $this->withToken($token->plainTextToken)
            ->deleteJson('/api/auth/account', [
                'current_password' => 'oldPassword123',
            ]);

        $response->assertOk()->assertExactJson([
            'message' => 'Votre compte a été supprimé.',
        ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_account_deletion_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'oldPassword123']);
        $token = $user->createToken('test-session');

        $response = $this->withToken($token->plainTextToken)
            ->deleteJson('/api/auth/account', [
                'current_password' => 'incorrectPassword123',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'current_password' => 'Le mot de passe actuel est incorrect.',
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_account_deletion_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/auth/account', [
            'current_password' => 'oldPassword123',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_revoke_all_sessions(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-session');
        $otherToken = $user->createToken('other-session');

        $response = $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/auth/logout-all');

        $response->assertOk()->assertExactJson([
            'message' => 'Toutes les sessions ont été déconnectées.',
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }
}
