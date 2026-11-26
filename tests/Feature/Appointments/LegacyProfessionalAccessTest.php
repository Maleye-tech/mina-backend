<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Auth\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyProfessionalAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function professionalRoutes(): array
    {
        return [
            'appointments' => ['GET', '/api/v1/appointments/'],
            'availability' => ['GET', '/api/v1/appointments/availability'],
            'consultation requests' => ['GET', '/api/v1/appointments/consultation-requests'],
            'private notes' => ['GET', '/api/v1/appointments/private-notes'],
            'alerts' => ['GET', '/api/v1/appointments/alerts'],
        ];
    }

    #[DataProvider('ineligibleProfessionalStates')]
    public function test_ineligible_professional_cannot_use_legacy_routes(
        string $method,
        string $uri,
        bool $isActive,
        bool $isVerified,
        bool $hasAcceptedCharter,
    ): void {
        $this->actingAsProfessional($isActive, $isVerified, $hasAcceptedCharter);

        $this->json($method, $uri)->assertForbidden();
    }

    public static function ineligibleProfessionalStates(): array
    {
        $cases = [];

        foreach (self::professionalRoutes() as $name => [$method, $uri]) {
            $cases[$name.' inactive'] = [$method, $uri, false, true, true];
            $cases[$name.' unverified'] = [$method, $uri, true, false, true];
            $cases[$name.' without charter'] = [$method, $uri, true, true, false];
        }

        return $cases;
    }

    public function test_student_can_still_use_shared_legacy_routes(): void
    {
        $this->actingAsStudent();

        $this->getJson('/api/v1/appointments/')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/appointments/private-notes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/appointments/alerts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/appointments/availability')->assertForbidden();
        $this->getJson('/api/v1/appointments/consultation-requests')->assertForbidden();
    }

    public function test_professional_cannot_book_through_the_student_legacy_route(): void
    {
        $this->actingAsProfessional();

        $this->postJson('/api/v1/appointments/', [
            'professional_id' => 1,
            'slot_id' => 1,
        ])->assertForbidden();
    }

    public function test_legacy_availability_requires_the_synchronized_profile_to_be_eligible(): void
    {
        $user = $this->actingAsProfessional();
        $legacyProfile = AppointmentProfessional::query()->where('user_id', $user->id)->firstOrFail();
        $legacyProfile->update(['charter_accepted_at' => null]);

        $this->getJson('/api/v1/appointments/availability')->assertForbidden();
    }

    private function actingAsProfessional(bool $isActive = true, bool $isVerified = true, bool $hasAcceptedCharter = true): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create([
            'role' => UserRole::Professional,
            'is_active' => $isActive,
        ]);

        ProfessionalProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => $user->name,
            'specialty' => 'psychologue',
            'languages' => ['Français'],
            'is_verified' => $isVerified,
            'charter_accepted_at' => $hasAcceptedCharter ? now() : null,
        ]);
        AppointmentProfessional::factory()->create([
            'user_id' => $user->id,
            'is_verified' => $isVerified,
            'charter_accepted_at' => $hasAcceptedCharter ? now() : null,
        ]);

        $token = $user->createToken('legacy-professional-test', ['full-access']);
        $this->withToken($token->plainTextToken);

        return $user;
    }

    private function actingAsStudent(): void
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => UserRole::Student]);
        $token = $user->createToken('legacy-student-test', ['full-access']);
        $this->withToken($token->plainTextToken);
    }
}
