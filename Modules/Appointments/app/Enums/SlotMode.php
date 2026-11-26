<?php

namespace Modules\Appointments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SlotMode: string implements HasColor, HasLabel
{
    case Visio = 'visio';
    case Chat = 'chat';

    public function label(): string
    {
        return match ($this) {
            self::Visio => 'Visioconférence (lien sécurisé)',
            self::Chat => 'Chat écrit en direct',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Visio => 'info',
            self::Chat => 'success',
        };
    }
}
