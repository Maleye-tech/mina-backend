<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Étudiant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label('Formule')
                    ->badge()
                    ->placeholder('Essai / Code Partenaire'),
                TextColumn::make('partnerInstitution.name')
                    ->label('Institution Partenaire')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                IconColumn::make('auto_renew')
                    ->label('Renouvellement')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc');
    }
}
