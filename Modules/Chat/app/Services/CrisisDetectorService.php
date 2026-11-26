<?php

namespace Modules\Chat\Services;

class CrisisDetectorService
{
    /**
     * @var array<int, string>
     */
    private const CRISIS_PATTERNS = [
        'suicide',
        'suicider',
        'me tuer',
        'veux mourir',
        'mettre fin à mes jours',
        'en finir',
        'plus envie de vivre',
        'plus la force de vivre',
        'fatigué de vivre',
        'adieu tout le monde',
        'bëgg naa dee', // Wolof: je veux mourir
        'faatu',        // Wolof: mourir / décès
    ];

    /**
     * @return array{is_crisis: bool, keywords: array<int, string>, emergency_contacts: array<int, array{name: string, number: string}>}
     */
    public function analyze(string $text): array
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        $detected = [];

        foreach (self::CRISIS_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                $detected[] = $pattern;
            }
        }

        $isCrisis = count($detected) > 0;

        return [
            'is_crisis' => $isCrisis,
            'keywords' => $detected,
            'emergency_contacts' => $isCrisis ? config('care.emergency_contacts', []) : [],
        ];
    }
}
