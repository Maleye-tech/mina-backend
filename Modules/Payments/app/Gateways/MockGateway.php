<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Payments\Models\Payment;

class MockGateway implements PaymentGatewayInterface
{
    public function createCheckout(Payment $payment, array $options = []): array
    {
        $ref = 'MOCK-'.Str::upper(Str::random(12));

        return [
            'checkout_url' => "https://payment.mina.sn/checkout/{$payment->reference}?provider_ref={$ref}",
            'provider_reference' => $ref,
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');

        if ($signature === 'invalid') {
            return false;
        }

        return true;
    }

    public function refund(Payment $payment, ?string $reason = null): bool
    {
        return true;
    }
}
