<?php

return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'mock'),

    'gateways' => [
        'paydunya' => [
            'master_key' => env('PAYDUNYA_MASTER_KEY'),
            'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
            'token' => env('PAYDUNYA_TOKEN'),
        ],
        'mock' => [],
    ],
];
