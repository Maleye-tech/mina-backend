<?php

namespace Tests\Feature\Privacy;

use App\Models\MentalHealthAuditLog;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class PrivacyApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('privacy-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_user_can_export_personal_and_academic_data(): void
    {
        $user = $this->actingAsUser();

        $user->consents()->create([
            'consent_type' => UserConsent::PERSONAL_DATA,
            'granted' => true,
            'version' => '1.0',
        ]);

        $response = $this->getJson('/api/v1/privacy/export')
            ->assertOk()
            ->assertJsonStructure([
                'declaration_cdp',
                'exported_at',
                'donnees' => [
                    'profil',
                    'consentements_cdp',
                    'sessions_qcm',
                    'tests_bien_etre',
                    'rendez_vous_care',
                    'abonnements',
                    'historique_paiements',
                ],
            ]);

        $this->assertSame($user->id, $response->json('donnees.profil.id'));
        $this->assertSame(1, MentalHealthAuditLog::query()->where('student_id', $user->id)->count());
    }

    public function test_user_can_delete_account_and_erase_personal_data(): void
    {
        $user = $this->actingAsUser();
        $userId = $user->id;

        $this->deleteJson('/api/v1/privacy/delete-account', [
            'confirmation' => 'SUPPRIMER',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Votre compte et l\'ensemble de vos données associées ont été supprimés avec succès conformément à vos droits.');

        $this->assertNull(User::find($userId));
    }

    public function test_health_check_endpoint(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'status' => 'healthy',
                'database' => 'connected',
            ]);
    }
}
