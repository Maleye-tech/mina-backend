<?php

namespace Modules\WellBeing\Filament\Resources\EmergencyContactResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmergencyContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact d’urgence')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('role_or_description')
                            ->label('Rôle ou description')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone_number')
                            ->label('Numéro de téléphone')
                            ->tel()
                            ->required()
                            ->maxLength(40),
                        TextInput::make('availability')
                            ->label('Disponibilité')
                            ->maxLength(255)
                            ->placeholder('24h/24, 7j/7'),
                        TextInput::make('address')
                            ->label('Adresse')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Contact actif')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
