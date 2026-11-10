<?php

return [
    'consents' => [
        'personal_data' => [
            'version' => env('PERSONAL_DATA_CONSENT_VERSION', '1.0'),
        ],
        'mental_health_data' => [
            'version' => env('MENTAL_HEALTH_CONSENT_VERSION', '1.0'),
        ],
    ],
];
