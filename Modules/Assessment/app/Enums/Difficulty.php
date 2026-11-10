<?php

namespace Modules\Assessment\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Difficulty: string implements HasColor, HasLabel
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Facile',
            self::Medium => 'Moyen',
            self::Hard => 'Difficile',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Easy => 'success',
            self::Medium => 'warning',
            self::Hard => 'danger',
        };
    }
}
