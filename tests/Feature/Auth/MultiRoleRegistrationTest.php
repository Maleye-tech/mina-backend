<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\Subscriptions\Models\PartnerInstitution;
use Tests\TestCase;

class MultiRoleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_as_student(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Awa Diop',
            'email' => 'awa.diop@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'student',
            'study_field' => 'Médecine',
            'education_level' => '2e année',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.role_label', 'Étudiant')
            ->assertJsonPath('user.role_profile.type', 'student')
            ->assertJsonPath('user.role_profile.study_field', 'Médecine');

        $this->assertDatabaseHas('users', [
            'email' => 'awa.diop@example.com',
            'role' => UserRole::Student->value,
            'study_field' => 'Médecine',
        ]);
    }

    public function test_can_register_as_ambassador(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ousmane Ba',
            'email' => 'ousmane.ba@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'ambassador',
            'study_field' => 'Pharmacie',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'ambassador')
            ->assertJsonPath('user.role_label', 'Ambassadeur')
            ->assertJsonPath('user.role_profile.type', 'ambassador');

        $this->assertDatabaseHas('users', [
            'email' => 'ousmane.ba@example.com',
            'role' => UserRole::Ambassador->value,
        ]);
    }

    public function test_can_register_as_professional(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Dr. Marie Fall',
            'email' => 'marie.fall@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'professional',
            'professional_title' => 'Psychiatre & Addictologue',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'professional')
            ->assertJsonPath('user.role_label', 'Professionnel de santé mentale')
            ->assertJsonPath('user.role_profile.type', 'professional')
            ->assertJsonPath('user.role_profile.title', 'Psychiatre & Addictologue');

        $user = User::where('email', 'marie.fall@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('appointment_professionals', [
            'user_id' => $user->id,
            'title' => 'Psychiatre & Addictologue',
        ]);
    }

    public function test_can_register_as_partner(): void
    {
        $crous = PartnerInstitution::create([
            'name' => 'CROUS Dakar',
            'type' => 'crous',
            'code' => 'CROUS-TEST',
            'subsidy_rate' => 100,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Moustapha Dieng',
            'email' => 'moustapha@crous-dakar.sn',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'partner',
            'partner_institution_id' => $crous->id,
            'partner_position' => 'Chef de division bourses et aides sociales',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'partner')
            ->assertJsonPath('user.role_label', 'Partenaire institutionnel')
            ->assertJsonPath('user.role_profile.type', 'partner')
            ->assertJsonPath('user.role_profile.institution_id', $crous->id)
            ->assertJsonPath('user.role_profile.position', 'Chef de division bourses et aides sociales');

        $this->assertDatabaseHas('users', [
            'email' => 'moustapha@crous-dakar.sn',
            'role' => UserRole::Partner->value,
            'partner_institution_id' => $crous->id,
        ]);
    }

    public function test_onboarding_route_returns_4_roles_definitions(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $token = $student->createToken('test', ['full-access']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/auth/onboarding')
            ->assertOk()
            ->assertJsonStructure([
                'available_roles' => [
                    '*' => ['id', 'title', 'subtitle', 'icon'],
                ],
                'questions',
                'universities',
                'partner_institutions',
                'current_role',
            ]);

        $roles = collect($response->json('available_roles'))->pluck('id')->all();
        $this->assertEquals(['student', 'ambassador', 'professional', 'partner'], $roles);
    }

    public function test_partner_dashboard_and_code_generation(): void
    {
        $crous = PartnerInstitution::create([
            'name' => 'CROUS Saint-Louis',
            'type' => 'crous',
            'code' => 'CROUS-SL',
            'max_beneficiaries' => 500,
            'current_beneficiaries' => 45,
            'subsidy_rate' => 100,
            'is_active' => true,
        ]);

        $partnerUser = User::factory()->create([
            'role' => UserRole::Partner,
            'partner_institution_id' => $crous->id,
            'partner_position' => 'Directeur',
        ]);
        $token = $partnerUser->createToken('partner-token', ['full-access']);

        // Check dashboard
        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/partner/dashboard')
            ->assertOk()
            ->assertJsonPath('institution.name', 'CROUS Saint-Louis')
            ->assertJsonPath('institution.current_beneficiaries', 45)
            ->assertJsonPath('institution.remaining_beneficiaries', 455);

        // Generate access codes
        $genResponse = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/partner/access-codes', [
                'count' => 5,
                'duration_days' => 60,
            ])
            ->assertCreated()
            ->assertJsonCount(5, 'codes');

        $firstCode = $genResponse->json('codes.0');
        $this->assertDatabaseHas('access_codes', [
            'code' => $firstCode,
            'partner_institution_id' => $crous->id,
            'duration_days' => 60,
        ]);

        // List codes
        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/partner/access-codes')
            ->assertOk()
            ->assertJsonPath('total', 5);
    }

    public function test_non_partner_cannot_access_partner_dashboard(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $token = $student->createToken('student-token', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/partner/dashboard')
            ->assertForbidden();
    }
}
