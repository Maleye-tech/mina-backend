<?php

namespace App\Filament\Resources\Library\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Library\Enums\CompressionStatus;

class PodcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Matière'),
                TextColumn::make('level.name')
                    ->label('Niveau'),
                TextColumn::make('duration_seconds')
                    ->label('Durée')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : sprintf('%d:%02d', intdiv($state, 60), $state % 60)),
                TextColumn::make('compression_status')
                    ->label('Compression')
                    ->badge()
                    ->formatStateUsing(fn (CompressionStatus|string $state): string => ($state instanceof CompressionStatus ? $state : CompressionStatus::from($state))->label())
                    ->color(fn (CompressionStatus|string $state): string => match ($state instanceof CompressionStatus ? $state : CompressionStatus::from($state)) {
                        CompressionStatus::Done => 'success',
                        CompressionStatus::Pending, CompressionStatus::Processing => 'warning',
                        CompressionStatus::Failed => 'danger',
                        CompressionStatus::Skipped => 'gray',
                    }),
                IconColumn::make('is_published')
                    ->label('Publié')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('compression_status')
                    ->label('Compression')
                    ->options(array_column(
                        array_map(
                            fn (CompressionStatus $case): array => [$case->value, $case->label()],
                            CompressionStatus::cases(),
                        ),
                        1,
                        0,
                    )),
                TernaryFilter::make('is_published')
                    ->label('Publié'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
