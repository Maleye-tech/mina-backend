<?php

namespace Modules\Assessment\Services;

use Carbon\CarbonInterface;
use Modules\Assessment\Enums\ReviewQuality;
use Modules\Assessment\Models\FlashcardState;

/**
 * Algorithme SM-2 (SuperMemo 2) de répétition espacée.
 *
 * Chaque révision est notée de 0 à 5. L'algorithme en déduit :
 * — le nouveau facteur de facilité (jamais sous 1,30) ;
 * — le nombre de répétitions réussies consécutives ;
 * — l'intervalle en jours avant la prochaine révision.
 */
class Sm2Service
{
    /**
     * Facteur de facilité minimal imposé par SM-2.
     */
    public const MIN_EASINESS_FACTOR = 1.3;

    /**
     * Intervalle des première et deuxième répétitions réussies.
     */
    private const FIRST_INTERVAL_DAYS = 1;

    private const SECOND_INTERVAL_DAYS = 6;

    /**
     * Calculer le nouvel état SM-2 d'une carte après une révision.
     *
     * La méthode ne tou ni la base de données : elle peut être testée
     * unitairement sur un état en mémoire.
     *
     * @return array{
     *     easiness_factor: float,
     *     interval_days: int,
     *     repetitions: int,
     *     lapses: int,
     *     last_quality: int,
     *     due_at: CarbonInterface,
     *     last_reviewed_at: CarbonInterface
     * }
     */
    public function calculate(FlashcardState $state, ReviewQuality $quality, ?CarbonInterface $reviewedAt = null): array
    {
        $reviewedAt ??= now();

        $easinessFactor = $this->nextEasinessFactor((float) $state->easiness_factor, $quality);

        if ($quality->isSuccessful()) {
            $repetitions = (int) $state->repetitions + 1;

            $intervalDays = match (true) {
                $repetitions === 1 => self::FIRST_INTERVAL_DAYS,
                $repetitions === 2 => self::SECOND_INTERVAL_DAYS,
                default => (int) round($state->interval_days * $easinessFactor),
            };

            $lapses = (int) $state->lapses;
        } else {
            $repetitions = 0;

            $intervalDays = self::FIRST_INTERVAL_DAYS;

            $lapses = (int) $state->lapses + 1;
        }

        return [
            'easiness_factor' => $easinessFactor,
            'interval_days' => max(1, $intervalDays),
            'repetitions' => $repetitions,
            'lapses' => $lapses,
            'last_quality' => $quality->value,
            'due_at' => $reviewedAt->addDays($intervalDays),
            'last_reviewed_at' => $reviewedAt,
        ];
    }

    /**
     * Facteur de facilité suivant : EF' = EF + (0,1 − (5 − q) × (0,08 + (5 − q) × 0,02)).
     *
     * Une qualité de 5 augmente le facteur de 0,1 ; une qualité de 4 le laisse
     * inchangé ; une qualité inférieure le diminue, sans jamais descendre
     * sous 1,30.
     */
    private function nextEasinessFactor(float $easinessFactor, ReviewQuality $quality): float
    {
        $penalty = 5 - $quality->value;

        $next = $easinessFactor + (0.1 - $penalty * (0.08 + $penalty * 0.02));

        return round(max($next, self::MIN_EASINESS_FACTOR), 2);
    }
}
