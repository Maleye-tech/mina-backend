<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UrgentAlertResource\Pages\ListUrgentAlerts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Appointments\Enums\AlertStatus;
use Modules\Appointments\Models\UrgentAlert;

class UrgentAlertResource extends Resource
{
    protected static ?string $model = UrgentAlert::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Mina Care';

    protected static ?string $modelLabel = 'Alerte d’urgence';

    protected static ?string $pluralModelLabel = 'Alertes d’urgence';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('N° alerte')
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.pseudonym')
                    ->label('Pseudonyme étudiant')
                    ->placeholder('Pseudonyme non renseigné')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof AlertStatus ? $state : AlertStatus::from($state))->label())
                    ->color(fn ($state): string => ($state instanceof AlertStatus ? $state : AlertStatus::from($state))->getColor()),
                Tables\Columns\TextColumn::make('handler.name')
                    ->label('Prise en charge')
                    ->placeholder('En attente'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Déclenchée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(AlertStatus::class),
            ])
            ->actions([
                Action::make('mark_handled')
                    ->label('Marquer traitée')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (UrgentAlert $record): bool => $record->status === AlertStatus::Open)
                    ->action(function (UrgentAlert $record): void {
                        $record->markAsHandled(auth()->user());
                        Notification::make()->title('Alerte marquée comme traitée.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUrgentAlerts::route('/'),
        ];
    }
}
