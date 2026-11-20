<?php

namespace App\Filament\Resources\Assessment\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Assessment\Enums\ContentStatus;

class FlashcardDecksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Matière')
                    ->sortable(),
                TextColumn::make('level.name')
                    ->label('Niveau')
                    ->sortable(),
                TextColumn::make('flashcards.count')
                    ->label('Cartes')
                    ->counts('flashcards'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ContentStatus::options()),
                SelectFilter::make('subject_id')
                    ->label('Matière')
                    ->relationship('subject', 'name'),
                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->relationship('level', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order');
    }
}
