<?php

namespace App\Filament\Resources\Appointments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Étudiant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('professional.user.name')
                    ->label('Professionnel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->label('Date & Heure')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label('Durée')
                    ->suffix(' min'),
                TextColumn::make('consultation_type')
                    ->label('Format')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('scheduled_at', 'desc');
    }
}
