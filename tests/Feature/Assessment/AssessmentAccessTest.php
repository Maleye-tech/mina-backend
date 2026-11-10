<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssessmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function authorizedRoles(): array
    {
        return [
            'student' => [UserRole::Student],
            'ambassador' => [UserRole::Ambassador],
            'professional' => [UserRole::Professional],
            'admin' => [UserRole::Admin],
        ];
    }

    #[DataProvider('authorizedRoles')]
    public function test_returns_assessments_for_each_authorized_role(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('assessment-access', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/assessments')
            ->assertOk();
    }

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/assessments')->assertUnauthorized();
    }
}
