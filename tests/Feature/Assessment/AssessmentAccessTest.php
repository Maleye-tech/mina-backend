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
    public function test_returns_questions_for_each_authorized_role(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('assessment-access', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/qcm/questions')
            ->assertOk();
    }

    #[DataProvider('authorizedRoles')]
    public function test_reaches_the_session_endpoint_for_each_authorized_role(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('assessment-access', ['full-access']);

        // Aucune question n'existe encore : le rôle passe, la charge utile est rejetée.
        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/qcm/sessions')
            ->assertUnprocessable();
    }

    #[DataProvider('authorizedRoles')]
    public function test_reaches_the_flashcard_endpoints_for_each_authorized_role(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('assessment-access', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/flashcards/decks')
            ->assertOk();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/flashcards/reviews/today')
            ->assertOk();
    }

    #[DataProvider('authorizedRoles')]
    public function test_reaches_the_clinical_case_endpoints_for_each_authorized_role(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('assessment-access', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/clinical-cases')
            ->assertOk();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/clinical-cases/attempts')
            ->assertOk();
    }

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/qcm/questions')->assertUnauthorized();
        $this->getJson('/api/v1/qcm/sessions')->assertUnauthorized();
        $this->postJson('/api/v1/qcm/sessions')->assertUnauthorized();
        $this->getJson('/api/v1/flashcards/decks')->assertUnauthorized();
        $this->getJson('/api/v1/flashcards/reviews/today')->assertUnauthorized();
        $this->getJson('/api/v1/clinical-cases')->assertUnauthorized();
        $this->getJson('/api/v1/clinical-cases/attempts')->assertUnauthorized();
        $this->postJson('/api/v1/clinical-cases/attempts')->assertUnauthorized();
    }

    public function test_returns_403_without_the_full_access_ability(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('assessment-access', ['basic-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/qcm/questions')
            ->assertForbidden();
    }
}
