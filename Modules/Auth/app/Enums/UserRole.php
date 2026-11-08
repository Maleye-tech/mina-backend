<?php

namespace Modules\Auth\Enums;

enum UserRole: string
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
}
