<?php

namespace Modules\WellBeing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Types de tests d'auto-évaluation proposés par Mina Care.
 */
enum AssessmentTestType: string implements HasColor, HasLabel
{
    case Anxiety = 'anxiety';
    case Stress = 'stress';
    case Wellbeing = 'wellbeing';

    public function label(): string
    {
        return match ($this) {
            self::Anxiety => 'Anxiété',
            self::Stress => 'Stress',
            self::Wellbeing => 'Bien-être',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Anxiety => 'danger',
            self::Stress => 'warning',
            self::Wellbeing => 'success',
        };
    }
}
