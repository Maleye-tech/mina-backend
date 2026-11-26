<?php

$emergencyContacts = json_decode((string) env('CARE_EMERGENCY_CONTACTS', '[]'), true);

return [
    'emergency_contacts' => array_values(array_filter(
        is_array($emergencyContacts) ? $emergencyContacts : [],
        static fn (mixed $contact): bool => is_array($contact)
            && is_string($contact['name'] ?? null)
            && is_string($contact['number'] ?? null)
            && trim($contact['name']) !== ''
            && trim($contact['number']) !== '',
    )),
];
