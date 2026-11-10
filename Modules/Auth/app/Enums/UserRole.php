<?php

namespace Modules\Auth\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Student = 'student';
    case Ambassador = 'ambassador';
    case Professional = 'professional';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Étudiant',
            self::Ambassador => 'Ambassadeur',
            self::Professional => 'Professionnel de santé mentale',
            self::Admin => 'Administrateur',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Student => 'gray',
            self::Ambassador => 'warning',
            self::Professional => 'info',
            self::Admin => 'danger',
        };
    }
}
