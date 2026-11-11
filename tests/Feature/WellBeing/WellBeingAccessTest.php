<?php

namespace Tests\Feature\WellBeing;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\WellBeing\Concerns\InteractsWithWellBeing;
use Tests\TestCase;

class WellBeingAccessTest extends TestCase
{
    use InteractsWithWellBeing;
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
    public function test_authorized_roles_can_access_the_wellbeing_catalogue(UserRole $role): void
    {
        $user = $this->userWithConsent($role);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles')
            ->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/wellbeing/articles')->assertUnauthorized();
        $this->getJson('/api/v1/wellbeing/tests')->assertUnauthorized();
        $this->getJson('/api/v1/wellbeing/results')->assertUnauthorized();
        $this->getJson('/api/v1/wellbeing/emergency-contacts')->assertUnauthorized();
    }

    public function test_full_access_ability_is_required(): void
    {
        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user, ['basic-access']))
            ->getJson('/api/v1/wellbeing/articles')
            ->assertForbidden();
    }

    public function test_current_mental_health_consent_is_required(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles')
            ->assertForbidden();
    }

    public function test_outdated_consent_version_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->consents()->create([
            'consent_type' => UserConsent::MENTAL_HEALTH_DATA,
            'granted' => true,
            'version' => 'older-version',
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles')
            ->assertForbidden();
    }

    public function test_legacy_wellbeings_alias_remains_available(): void
    {
        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeings')
            ->assertOk();
    }
}
