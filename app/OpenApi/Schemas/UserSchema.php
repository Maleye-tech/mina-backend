<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    title: 'Utilisateur',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'email_verified', type: 'boolean'),
        new OA\Property(property: 'role', type: 'string', enum: ['student', 'ambassador', 'professional', 'admin']),
        new OA\Property(property: 'role_label', type: 'string'),
        new OA\Property(property: 'pseudonym', type: 'string', nullable: true),
        new OA\Property(
            property: 'preferences',
            type: 'object',
            properties: [
                new OA\Property(property: 'language', type: 'string', example: 'fr'),
                new OA\Property(property: 'notifications_enabled', type: 'boolean', example: true),
            ]
        ),
        new OA\Property(
            property: 'consents',
            type: 'object',
            properties: [
                new OA\Property(property: 'personal_data', type: 'boolean'),
                new OA\Property(property: 'mental_health_data', type: 'boolean'),
            ]
        ),
        new OA\Property(property: 'student_profile', type: 'object', nullable: true),
    ]
)]
class UserSchema {}
