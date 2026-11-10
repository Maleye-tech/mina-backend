<?php

namespace Modules\Assessment\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasColor, HasLabel
{
    case Single = 'single';
    case Multiple = 'multiple';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Réponse unique',
            self::Multiple => 'Réponses multiples',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Single => 'info',
            self::Multiple => 'warning',
        };
    }
}
