<?php

namespace Modules\Appointments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlertStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Handled = 'handled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouverte (à traiter)',
            self::Handled => 'Traitée',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Handled => 'success',
        };
    }
}
