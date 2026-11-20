<?php

namespace App\Filament\Resources\Library\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Library\Enums\ContentType;

class LibraryContentsTable
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
                    ->formatStateUsing(fn (ContentType|string $state): string => ($state instanceof ContentType ? $state : ContentType::from($state))->label())
                    ->color(fn (ContentType|string $state): string => match ($state instanceof ContentType ? $state : ContentType::from($state)) {
                        ContentType::Course => 'info',
                        ContentType::SummarySheet => 'warning',
                        ContentType::Recap => 'success',
                    }),
                TextColumn::make('subject.name')
                    ->label('Matière')
                    ->sortable(),
                TextColumn::make('level.name')
                    ->label('Niveau')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Publié')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(ContentType::options()),
                SelectFilter::make('subject_id')
                    ->label('Matière')
                    ->relationship('subject', 'name'),
                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->relationship('level', 'name'),
                TernaryFilter::make('is_published')
                    ->label('Publié'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order');
    }
}
