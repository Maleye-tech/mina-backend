<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;
use Modules\Subscriptions\Database\Seeders\SubscriptionsDatabaseSeeder;
use Modules\Subscriptions\Models\SubscriptionPlan;
use Tests\TestCase;

class PaymentsApiTest extends TestCase
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
        $token = $user->createToken('payments-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    public function test_checkout_creates_payment_and_returns_redirect_url(): void
    {
        $user = $this->actingAsUser();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();

        $response = $this->postJson('/api/v1/payments/checkout', [
            'plan_id' => $plan->id,
            'provider' => 'mock',
            'payment_method' => 'wave',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'checkout_url',
                'payment' => ['id', 'reference', 'amount', 'currency', 'status', 'status_label'],
            ]);

        $this->assertNotEmpty($response->json('checkout_url'));
        $this->assertSame(PaymentStatus::Pending->value, $response->json('payment.status'));
        $this->assertSame(3000, $response->json('payment.amount'));
    }

    public function test_checkout_is_idempotent_with_header(): void
    {
        $user = $this->actingAsUser();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();

        $idempotencyKey = 'key-123456';

        $first = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/checkout', ['plan_id' => $plan->id])
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/payments/checkout', ['plan_id' => $plan->id])
            ->assertCreated();

        $this->assertSame($first->json('payment.id'), $second->json('payment.id'));
        $this->assertSame($first->json('payment.reference'), $second->json('payment.reference'));
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_webhook_completes_payment_and_activates_subscription(): void
    {
        $user = $this->actingAsUser();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'amount' => 3000,
            'status' => PaymentStatus::Pending,
            'provider' => 'mock',
        ]);

        $response = $this->postJson('/api/v1/payments/webhook/mock', [
            'reference' => $payment->reference,
            'status' => 'completed',
            'payment_method' => 'wave',
        ])->assertOk();

        $this->assertSame(PaymentStatus::Completed->value, $payment->fresh()->status->value);
        $this->assertNotNull($payment->fresh()->paid_at);

        // Verify student now has active subscription
        $currentSub = $this->getJson('/api/v1/subscriptions/current')
            ->assertOk()
            ->assertJson(['has_active_access' => true]);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $response = $this->withHeader('X-Webhook-Signature', 'invalid')
            ->postJson('/api/v1/payments/webhook/mock', [
                'reference' => 'non-existent',
            ])->assertStatus(422);
    }

    public function test_user_can_view_payment_history_and_receipt(): void
    {
        $user = $this->actingAsUser();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'amount' => 3000,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $this->getJson('/api/v1/payments/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id);

        $this->getJson("/api/v1/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertJsonPath('amount', 3000)
            ->assertJsonPath('reference', $payment->reference);
    }

    public function test_admin_can_refund_payment(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $admin = $this->actingAsUser(UserRole::Admin);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'amount' => 3000,
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);

        $this->postJson("/api/v1/payments/{$payment->id}/refund", [
            'reason' => 'Demande d\'annulation sous 24h',
        ])
            ->assertOk()
            ->assertJsonPath('payment.status', PaymentStatus::Refunded->value);

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
    }
}
