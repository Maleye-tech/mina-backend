<?php

namespace Modules\Events\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EventType: string implements HasColor, HasLabel
{
    case Workshop = 'workshop';
    case Webinar = 'webinar';
    case AmbassadorTraining = 'ambassador_training';

    public function label(): string
    {
        return match ($this) {
            self::Workshop => 'Atelier',
            self::Webinar => 'Webinaire',
            self::AmbassadorTraining => 'Formation ambassadeur',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Workshop => 'info',
            self::Webinar => 'primary',
            self::AmbassadorTraining => 'warning',
        };
    }
}
