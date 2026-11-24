<?php

namespace Modules\Payments\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\UserRole;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\Payment;
use Modules\Subscriptions\Models\SubscriptionPlan;
use Modules\Subscriptions\Services\SubscriptionService;

class PaymentService
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
        private readonly SubscriptionService $subscriptions
    ) {}

    public function checkout(
        User $user,
        SubscriptionPlan $plan,
        ?string $provider = null,
        ?string $idempotencyKey = null,
        ?string $paymentMethod = null
    ): Payment {
        if ($idempotencyKey) {
            $existing = Payment::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $plan, $provider, $idempotencyKey, $paymentMethod): Payment {
            $provider = $provider ?: config('payments.default_gateway', 'mock');
            $reference = 'MINA-PAY-'.strtoupper(Str::random(12));

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'provider' => $provider,
                'status' => PaymentStatus::Pending,
                'payment_method' => $paymentMethod,
            ]);

            $gateway = $this->gatewayManager->gateway($provider);
            $checkoutData = $gateway->createCheckout($payment);

            $payment->update([
                'checkout_url' => $checkoutData['checkout_url'],
                'provider_reference' => $checkoutData['provider_reference'],
            ]);

            return $payment;
        });
    }

    public function handleWebhook(string $provider, Request $request): Payment
    {
        $gateway = $this->gatewayManager->gateway($provider);

        if (! $gateway->verifyWebhookSignature($request)) {
            throw ValidationException::withMessages([
                'signature' => ['Signature de webhook invalide.'],
            ]);
        }

        $reference = $request->input('reference')
            ?: $request->input('custom_data.reference')
            ?: $request->input('data.custom_data.reference');

        $payment = Payment::query()
            ->where('reference', $reference)
            ->orWhere('provider_reference', $request->input('provider_reference'))
            ->firstOrFail();

        if ($payment->isCompleted()) {
            return $payment;
        }

        $statusInput = strtolower((string) ($request->input('status') ?: $request->input('data.status', 'completed')));

        if (in_array($statusInput, ['completed', 'success', 'paid'], true)) {
            DB::transaction(function () use ($payment, $request): void {
                $payment->update([
                    'status' => PaymentStatus::Completed,
                    'paid_at' => now(),
                    'payment_method' => $request->input('payment_method') ?: $payment->payment_method,
                    'metadata' => $request->all(),
                ]);

                if ($payment->plan && $payment->user) {
                    $this->subscriptions->activateSubscription($payment->user, $payment->plan);
                }
            });
        } elseif (in_array($statusInput, ['failed', 'cancelled'], true)) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => $request->all(),
            ]);
        }

        return $payment;
    }

    public function refund(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->status !== PaymentStatus::Completed) {
            throw ValidationException::withMessages([
                'payment' => ['Seuls les paiements validés peuvent être remboursés.'],
            ]);
        }

        $gateway = $this->gatewayManager->gateway($payment->provider);
        $gateway->refund($payment, $reason);

        $payment->update([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);

        return $payment;
    }

    public function history(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->with('plan')
            ->latest('id')
            ->paginate($perPage);
    }

    public function receipt(Payment $payment, User $user): array
    {
        if ($payment->user_id !== $user->id && ! $user->hasRole(UserRole::Admin)) {
            abort(403, 'Accès non autorisé à ce reçu.');
        }

        return [
            'receipt_number' => 'REC-'.$payment->reference,
            'date' => $payment->paid_at?->toIso8601String() ?: $payment->created_at->toIso8601String(),
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
            'status_label' => $payment->status->getLabel(),
            'payment_method' => $payment->payment_method,
            'customer' => [
                'name' => $payment->user->name,
                'email' => $payment->user->email,
            ],
            'item' => [
                'description' => $payment->plan ? $payment->plan->name : 'Abonnement Mina',
                'amount' => $payment->amount,
            ],
        ];
    }
}
