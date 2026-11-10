<?php

namespace Modules\Assessment\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Qualité de rappel d'une carte mémoire selon l'échelle SM-2 (0 à 5).
 *
 * 0 : aucun souvenir — 5 : réponse parfaite et immédiate.
 */
enum ReviewQuality: int implements HasColor, HasLabel
{
    case Blackout = 0;

    case Wrong = 1;

    case Familiar = 2;

    case Hard = 3;

    case Good = 4;

    case Perfect = 5;

    /**
     * L'échelle SM-2 considère une révision réussie à partir de 3.
     */
    public function isSuccessful(): bool
    {
        return $this->value >= 3;
    }

    public function label(): string
    {
        return match ($this) {
            self::Blackout => 'Aucun souvenir de la réponse.',
            self::Wrong => 'Réponse incorrecte, souvenir vague de la leçon.',
            self::Familiar => 'Réponse incorrecte, mais la bonne réponse semblait familière.',
            self::Hard => 'Réponse correcte retrouvée avec beaucoup de difficulté.',
            self::Good => 'Réponse correcte après hésitation.',
            self::Perfect => 'Réponse parfaite, immédiate.',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Blackout, self::Wrong => 'danger',
            self::Familiar, self::Hard => 'warning',
            self::Good => 'success',
            self::Perfect => 'info',
        };
    }
}
