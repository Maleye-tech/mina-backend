<?php

namespace Modules\WellBeing\Filament\Resources\TestResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Test d’auto-évaluation')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Titre')
                            ->columnSpanFull(),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('questions_count')
                            ->label('Nombre de questions')
                            ->counts('questions'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
