<?php

namespace App\Filament\Resources\Assessment\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Models\QcmImport;

class QcmImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->label('Fichier')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                IconColumn::make('dry_run')
                    ->label('Test à blanc')
                    ->boolean(),
                TextColumn::make('user.name')
                    ->label('Par')
                    ->toggleable(),
                TextColumn::make('total_rows')
                    ->label('Lignes')
                    ->sortable(),
                TextColumn::make('imported_count')
                    ->label('Importées')
                    ->sortable(),
                TextColumn::make('duplicate_count')
                    ->label('Doublons')
                    ->sortable(),
                TextColumn::make('failed_count')
                    ->label('Échecs')
                    ->sortable(),
                TextColumn::make('errors')
                    ->label('Erreurs')
                    ->badge()
                    ->state(fn (QcmImport $record): int => count($record->errors ?? []))
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('finished_at')
                    ->label('Terminé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ImportStatus::options()),
                TernaryFilter::make('dry_run')
                    ->label('Test à blanc'),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }
}
