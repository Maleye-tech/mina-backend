<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProfessionalProfileResource\Pages\CreateProfessionalProfile;
use App\Filament\Resources\UrgentAlertResource;
use App\Filament\Resources\UrgentAlertResource\Pages\ListUrgentAlerts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Appointments\Notifications\ProfessionalInvitationNotification;
use Modules\Auth\Enums\UserRole;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MinaCareResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_enabled_at' => now(),
        ]);
    }

    public function test_admin_can_access_care_resource_pages(): void
    {
        $admin = $this->admin();
        $profile = ProfessionalProfile::create([
            'user_id' => User::factory()->create(['role' => UserRole::Professional])->id,
            'display_name' => 'Dr. Test',
            'specialty' => 'psychologue',
            'languages' => ['Français'],
        ]);

        foreach ([
            '/admin/professional-profiles',
            '/admin/professional-profiles/create',
            "/admin/professional-profiles/{$profile->id}/edit",
            '/admin/urgent-alerts',
        ] as $url) {
            $this->actingAs($admin)
                ->withSession(['password_hash_web' => $admin->getAuthPassword()])
                ->get($url)
                ->assertOk();
        }
    }

    public function test_non_admin_cannot_access_care_resources(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Professional]))
            ->get('/admin/professional-profiles')
            ->assertForbidden();
    }

    public function test_admin_can_create_and_invite_a_professional(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        Livewire::test(CreateProfessionalProfile::class)
            ->fillForm([
                'account_name' => 'Dr. Awa Sow',
                'email' => 'awa.sow@example.com',
                'display_name' => 'Dr. Awa Sow',
                'specialty' => 'psychiatre',
                'languages' => ['Français', 'Anglais'],
                'bio' => 'Spécialiste des troubles anxieux.',
                'is_verified' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'awa.sow@example.com')->firstOrFail();
        $this->assertSame(UserRole::Professional, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('professional_profiles', [
            'user_id' => $user->id,
            'display_name' => 'Dr. Awa Sow',
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('appointment_professionals', [
            'user_id' => $user->id,
            'display_name' => 'Dr. Awa Sow',
        ]);
        Notification::assertSentTo($user, ProfessionalInvitationNotification::class);
    }

    public function test_urgent_alert_admin_list_never_displays_message_content(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'pseudonym' => 'ColombeSereine',
        ]);
        UrgentAlert::create([
            'student_id' => $student->id,
            'message' => 'Contenu strictement confidentiel de l’alerte',
            'status' => 'open',
        ]);
        $this->actingAs($this->admin());

        $this->assertFalse(UrgentAlertResource::canCreate());
        Livewire::test(ListUrgentAlerts::class)
            ->assertSuccessful()
            ->assertSee('ColombeSereine')
            ->assertDontSee('Contenu strictement confidentiel');
    }
}
