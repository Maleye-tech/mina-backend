<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class UsersManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        User::factory()->create(['name' => 'Ambassadeur Alpha']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/users?search=Alpha&role=student');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Ambassadeur Alpha')
            ->assertJsonPath('data.0.role', UserRole::Student->value);
    }

    public function test_admin_can_show_a_user(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.is_active', true);
    }

    public function test_admin_can_update_a_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$user->id}", [
                'name' => 'Nouveau Nom',
                'role' => UserRole::Ambassador->value,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nouveau Nom')
            ->assertJsonPath('user.role', UserRole::Ambassador->value)
            ->assertJsonPath('user.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nouveau Nom',
            'role' => UserRole::Ambassador->value,
            'is_active' => false,
        ]);
    }

    public function test_changing_the_email_resets_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$user->id}", [
                'email' => 'new-address@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('user.email_verified', false);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_deactivating_a_user_revokes_his_tokens(): void
    {
        $user = User::factory()->create();
        $userToken = $user->createToken('user-session');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $adminToken = $admin->createToken('admin-session');

        $this->withToken($adminToken->plainTextToken)
            ->patchJson("/api/v1/users/{$user->id}", ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $userToken->accessToken->id,
        ]);
    }

    public function test_admin_cannot_change_his_own_role_or_deactivate_himself(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$admin->id}", [
                'role' => UserRole::Student->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$admin->id}", [
                'is_active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_the_last_active_admin_cannot_be_replaced_nor_deactivated(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);
        $token = $actor->createToken('admin-session');
        // Désactivation hors API : les jetons existants restent valides.
        $actor->update(['is_active' => false]);

        $lastAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$lastAdmin->id}", [
                'role' => UserRole::Professional->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$lastAdmin->id}", [
                'is_active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);

        $this->assertSame(UserRole::Admin, $lastAdmin->fresh()->role);
        $this->assertTrue($lastAdmin->fresh()->is_active);
    }

    public function test_an_admin_can_hand_over_to_another_active_admin(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);
        $token = $actor->createToken('admin-session');
        $target = User::factory()->create(['role' => UserRole::Admin]);

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$target->id}", [
                'role' => UserRole::Ambassador->value,
            ])
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::Ambassador->value);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $user = User::factory()->create();
        $user->createToken('user-session');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_his_own_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->deleteJson("/api/v1/users/{$admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $user = User::factory()->create();
        $student = User::factory()->create();
        $token = $student->createToken('student-session');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/users')
            ->assertForbidden();

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/users/{$user->id}", ['name' => 'Hacked'])
            ->assertForbidden();

        $this->withToken($token->plainTextToken)
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertForbidden();
    }

    public function test_admin_can_create_a_managed_user_through_the_users_resource(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/users', [
                'name' => 'Nouveau Pro',
                'email' => 'pro@example.test',
                'role' => UserRole::Professional->value,
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::Professional->value);
    }
}
