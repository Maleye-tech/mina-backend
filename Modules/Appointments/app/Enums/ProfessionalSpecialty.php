<?php

namespace Modules\Appointments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProfessionalSpecialty: string implements HasColor, HasLabel
{
    case Psychologue = 'psychologue';
    case Psychiatre = 'psychiatre';
    case Conseiller = 'conseiller';

    public function label(): string
    {
        return match ($this) {
            self::Psychologue => 'Psychologue clinicien',
            self::Psychiatre => 'Médecin psychiatre',
            self::Conseiller => 'Conseiller d’écoute psychosociale',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Psychologue => 'info',
            self::Psychiatre => 'danger',
            self::Conseiller => 'success',
        };
    }
}
