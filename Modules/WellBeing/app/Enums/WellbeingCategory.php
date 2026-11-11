<?php

namespace Modules\WellBeing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Thématiques des fiches de prévention Mina Care.
 */
enum WellbeingCategory: string implements HasColor, HasLabel
{
    case Stress = 'stress';
    case Burnout = 'burnout';
    case Sleep = 'sleep';

    public function label(): string
    {
        return match ($this) {
            self::Stress => 'Stress',
            self::Burnout => 'Épuisement professionnel',
            self::Sleep => 'Sommeil',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stress => 'warning',
            self::Burnout => 'danger',
            self::Sleep => 'info',
        };
    }
}
