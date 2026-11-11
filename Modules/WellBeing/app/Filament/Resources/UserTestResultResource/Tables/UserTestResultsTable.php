<?php

namespace Modules\WellBeing\Filament\Resources\UserTestResultResource\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserTestResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('test.title')
                    ->label('Test')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('score')
                    ->label('Score')
                    ->sortable(),
                TextColumn::make('response_data.percentage')
                    ->label('Pourcentage')
                    ->suffix(' %'),
                TextColumn::make('response_data.level')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'low' => 'Faible',
                        'moderate' => 'Modéré',
                        'high' => 'Élevé',
                        default => 'Non déterminé',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'success',
                        'moderate' => 'warning',
                        'high' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Passé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('test_id')
                    ->label('Test')
                    ->relationship('test', 'title')
                    ->searchable(),
                SelectFilter::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
