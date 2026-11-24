<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('presenter_name')
                    ->label('Intervenant')
                    ->placeholder('-'),
                TextColumn::make('starts_at')
                    ->label('Date & Heure')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('current_participants')
                    ->label('Inscrits')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_participants')
                    ->label('Capacité')
                    ->numeric()
                    ->placeholder('Illimitée'),
                IconColumn::make('is_published')
                    ->label('Publié')
                    ->boolean(),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
