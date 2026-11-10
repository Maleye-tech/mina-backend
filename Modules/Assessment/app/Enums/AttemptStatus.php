<?php

namespace Modules\Assessment\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttemptStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Abandoned => 'Abandonnée',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Abandoned => 'gray',
        };
    }
}
