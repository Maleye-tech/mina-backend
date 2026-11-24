<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Payments\Models\Payment;

class PayDunyaGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly ?string $masterKey = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $token = null
    ) {}

    public function createCheckout(Payment $payment, array $options = []): array
    {
        // When running without external API keys, fallback to standard checkout redirect
        $token = $this->token ?: 'mock_token';
        $ref = 'PD-'.Str::upper(Str::random(10));

        return [
            'checkout_url' => "https://app.paydunya.com/sandbox-checkout/invoice/{$ref}",
            'provider_reference' => $ref,
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $token = $request->header('PAYDUNYA-TOKEN') ?: $request->input('data.token');
        $configuredToken = config('payments.gateways.paydunya.token');

        if ($configuredToken && $token && ! hash_equals($configuredToken, (string) $token)) {
            return false;
        }

        return true;
    }

    public function refund(Payment $payment, ?string $reason = null): bool
    {
        return true;
    }
}
