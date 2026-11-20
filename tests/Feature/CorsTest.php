<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_allows_a_configured_frontend_origin(): void
    {
        $origin = 'https://mina.example.com';

        config(['cors.allowed_origins' => [$origin]]);

        $response = $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization, content-type',
        ])->options('/api/auth/login');

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_preflight_does_not_echo_an_unconfigured_frontend_origin(): void
    {
        $allowedOrigin = 'https://mina.example.com';

        config(['cors.allowed_origins' => [$allowedOrigin]]);

        $response = $this->withHeaders([
            'Origin' => 'https://other.example.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/auth/login');

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
    }
}
