<?php

namespace Tests\Feature\Subscriptions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\Subscriptions\Database\Seeders\SubscriptionsDatabaseSeeder;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\AccessCode;
use Modules\Subscriptions\Models\SubscriptionPlan;
use Tests\TestCase;

class SubscriptionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionsDatabaseSeeder::class);
    }

    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('subscriptions-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_plans_lists_monthly_and_yearly_plans(): void
    {
        $response = $this->getJson('/api/v1/subscriptions/plans')
            ->assertOk()
            ->assertJsonStructure([
                'trial_days',
                'plans' => [
                    '*' => ['id', 'name', 'slug', 'price', 'currency', 'duration_days'],
                ],
            ]);

        $this->assertSame(3, $response->json('trial_days'));
        $this->assertCount(2, $response->json('plans'));
        $this->assertSame(3000, $response->json('plans.0.price'));
        $this->assertSame(15000, $response->json('plans.1.price'));
    }

    public function test_current_shows_no_subscription_for_new_user(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/subscriptions/current')
            ->assertOk()
            ->assertJson([
                'has_active_access' => false,
                'can_start_trial' => true,
                'subscription' => null,
            ]);
    }

    public function test_student_can_start_3_day_trial(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/subscriptions/trial')
            ->assertCreated()
            ->assertJsonPath('subscription.status', SubscriptionStatus::Trial->value)
            ->assertJsonPath('subscription.is_trial', true)
            ->assertJsonPath('subscription.is_valid', true);

        $this->assertNotNull($response->json('subscription.trial_ends_at'));

        $this->getJson('/api/v1/subscriptions/current')
            ->assertOk()
            ->assertJson([
                'has_active_access' => true,
                'can_start_trial' => false,
            ]);
    }

    public function test_student_cannot_start_trial_twice(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/subscriptions/trial')->assertCreated();
        $this->postJson('/api/v1/subscriptions/trial')->assertStatus(422);
    }

    public function test_student_can_redeem_partner_access_code(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/subscriptions/redeem-code', [
            'code' => 'MINA-CROUS-DEMO',
        ])
            ->assertOk()
            ->assertJsonPath('subscription.status', SubscriptionStatus::Active->value)
            ->assertJsonPath('subscription.is_valid', true);

        $this->assertSame('CROUS Dakar - Université Cheikh Anta Diop', $response->json('subscription.partner.name'));

        // Re-using same code must fail
        $otherUser = $this->actingAsUser();
        $this->postJson('/api/v1/subscriptions/redeem-code', [
            'code' => 'MINA-CROUS-DEMO',
        ])->assertStatus(422);
    }

    public function test_student_can_cancel_active_subscription(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/subscriptions/redeem-code', [
            'code' => 'MINA-CROUS-DEMO',
        ])->assertOk();

        $this->postJson('/api/v1/subscriptions/cancel')
            ->assertOk()
            ->assertJsonPath('subscription.auto_renew', false);
    }
}
