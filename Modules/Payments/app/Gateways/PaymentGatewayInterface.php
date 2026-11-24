<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Request;
use Modules\Payments\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{checkout_url: string, provider_reference: string}
     */
    public function createCheckout(Payment $payment, array $options = []): array;

    public function verifyWebhookSignature(Request $request): bool;

    public function refund(Payment $payment, ?string $reason = null): bool;
}
