<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Enums\UserRole;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
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

    public function test_admin_can_access_dashboard_and_see_stats_widget(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['password_hash_web' => $admin->getAuthPassword()])
            ->get('/admin')
            ->assertOk();

        Livewire::test(StatsOverviewWidget::class)
            ->assertSuccessful();
    }

    public function test_admin_can_access_all_module_resource_pages(): void
    {
        $admin = $this->admin();

        $urls = [
            '/admin/subscriptions',
            '/admin/payments',
            '/admin/appointments',
            '/admin/events',
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)
                ->withSession(['password_hash_web' => $admin->getAuthPassword()])
                ->get($url)
                ->assertOk();
        }
    }
}
