<?php

namespace Modules\Appointments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Pending = 'pending';
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Demandé',
            self::Pending => 'En attente',
            self::Proposed => 'Nouveau créneau proposé',
            self::Confirmed => 'Confirmé',
            self::Declined => 'Décliné',
            self::Cancelled => 'Annulé',
            self::Completed => 'Terminé',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Pending => 'warning',
            self::Proposed => 'info',
            self::Confirmed => 'success',
            self::Declined => 'danger',
            self::Cancelled => 'danger',
            self::Completed => 'info',
        };
    }
}
