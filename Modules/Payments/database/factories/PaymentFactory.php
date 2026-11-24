<?php

namespace Modules\Payments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;
use Modules\Subscriptions\Models\SubscriptionPlan;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'reference' => 'MINA-PAY-'.strtoupper(Str::random(12)),
            'idempotency_key' => Str::uuid()->toString(),
            'amount' => 3000,
            'currency' => 'XOF',
            'provider' => 'mock',
            'status' => PaymentStatus::Pending,
            'payment_method' => 'wave',
            'provider_reference' => 'MOCK-'.strtoupper(Str::random(10)),
            'checkout_url' => 'https://payment.mina.sn/checkout/demo',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
        ]);
    }
}
