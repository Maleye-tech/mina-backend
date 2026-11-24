<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Montant (FCFA)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('provider')
                    ->label('Agrégateur')
                    ->badge(),
                TextColumn::make('payment_method')
                    ->label('Méthode')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date paiement')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('id', 'desc');
    }
}
