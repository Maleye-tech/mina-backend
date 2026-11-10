<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nom'),
                TextEntry::make('email')->label('Adresse e-mail'),
                TextEntry::make('role')->label('Rôle')->badge(),
                IconEntry::make('is_active')->label('Compte actif')->boolean(),
                TextEntry::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i'),
            ]);
    }
}
