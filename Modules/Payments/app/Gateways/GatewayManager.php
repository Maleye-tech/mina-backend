<?php

namespace Modules\Payments\Gateways;

class GatewayManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    private array $gateways = [];

    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: config('payments.default_gateway', 'mock');

        if (! isset($this->gateways[$name])) {
            $this->gateways[$name] = $this->resolve($name);
        }

        return $this->gateways[$name];
    }

    private function resolve(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'mock' => new MockGateway,
            'paydunya' => new PayDunyaGateway(
                config('payments.gateways.paydunya.master_key'),
                config('payments.gateways.paydunya.private_key'),
                config('payments.gateways.paydunya.token'),
            ),
            default => new MockGateway,
        };
    }
}
