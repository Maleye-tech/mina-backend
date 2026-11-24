<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Payments\Http\Requests\CheckoutRequest;
use Modules\Payments\Http\Requests\RefundRequest;
use Modules\Payments\Http\Resources\PaymentResource;
use Modules\Payments\Models\Payment;
use Modules\Payments\Services\PaymentService;
use Modules\Subscriptions\Models\SubscriptionPlan;

class PaymentsController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($request->integer('plan_id'));
        $idempotencyKey = $request->header('Idempotency-Key');

        $payment = $this->payments->checkout(
            $request->user(),
            $plan,
            $request->input('provider'),
            $idempotencyKey,
            $request->input('payment_method')
        );

        return response()->json([
            'message' => 'Lien de paiement généré.',
            'payment' => PaymentResource::make($payment->load('plan')),
            'checkout_url' => $payment->checkout_url,
        ], 201);
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        $payment = $this->payments->handleWebhook($provider, $request);

        return response()->json([
            'message' => 'Webhook traité avec succès.',
            'status' => $payment->status->value,
            'reference' => $payment->reference,
        ]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        return PaymentResource::collection($this->payments->history($request->user(), $perPage));
    }

    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        return response()->json($this->payments->receipt($payment, $request->user()));
    }

    public function refund(RefundRequest $request, Payment $payment): JsonResponse
    {
        $refunded = $this->payments->refund($payment, $request->string('reason')->toString());

        return response()->json([
            'message' => 'Paiement remboursé avec succès.',
            'payment' => PaymentResource::make($refunded),
        ]);
    }
}
