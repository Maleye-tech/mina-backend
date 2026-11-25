<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsProfessional(): User
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Professional]);
        $token = $user->createToken('users-pro-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_professional_can_list_users(): void
    {
        $this->actingAsProfessional();
        User::factory()->count(3)->create();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_student_cannot_list_users(): void
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('users-student-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_users_require_auth(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}
