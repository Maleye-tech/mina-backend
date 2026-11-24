<?php

namespace Modules\Subscriptions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Essai gratuit',
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Cancelled => 'Résilié',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'warning',
            self::Active => 'success',
            self::Expired => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
