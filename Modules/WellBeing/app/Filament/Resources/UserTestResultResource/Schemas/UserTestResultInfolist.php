<?php

namespace Modules\WellBeing\Filament\Resources\UserTestResultResource\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\WellBeing\Services\TestScoringService;

class UserTestResultInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Résultat')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Utilisateur'),
                        TextEntry::make('test.title')
                            ->label('Test'),
                        TextEntry::make('score')
                            ->label('Score'),
                        TextEntry::make('response_data.max_score')
                            ->label('Score maximal'),
                        TextEntry::make('response_data.percentage')
                            ->label('Pourcentage')
                            ->suffix(' %'),
                        TextEntry::make('response_data.level')
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
                        TextEntry::make('feedback_given')
                            ->label('Retour immédiat')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Passé le')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),
                Section::make('Réponses détaillées')
                    ->schema([
                        RepeatableEntry::make('response_data.answers')
                            ->label('')
                            ->schema([
                                TextEntry::make('question_text')
                                    ->label('Question'),
                                TextEntry::make('option_label')
                                    ->label('Réponse choisie'),
                                TextEntry::make('points')
                                    ->label('Points'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Avertissement')
                    ->schema([
                        TextEntry::make('disclaimer')
                            ->label('')
                            ->state(TestScoringService::DISCLAIMER),
                    ]),
            ]);
    }
}
