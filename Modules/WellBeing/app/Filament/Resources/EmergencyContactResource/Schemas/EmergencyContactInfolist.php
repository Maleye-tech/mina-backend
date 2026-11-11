<?php

namespace Modules\WellBeing\Filament\Resources\EmergencyContactResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmergencyContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact d’urgence')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom'),
                        TextEntry::make('role_or_description')
                            ->label('Rôle ou description'),
                        TextEntry::make('phone_number')
                            ->label('Numéro de téléphone')
                            ->copyable(),
                        TextEntry::make('availability')
                            ->label('Disponibilité'),
                        TextEntry::make('address')
                            ->label('Adresse')
                            ->columnSpanFull(),
                        IconEntry::make('is_active')
                            ->label('Contact actif')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
