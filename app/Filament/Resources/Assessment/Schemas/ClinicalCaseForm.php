<?php

namespace App\Filament\Resources\Assessment\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;

class ClinicalCaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('presentation')
                    ->label('Présentation du cas')
                    ->rows(3)
                    ->required()
                    ->columnSpanFull(),
                Select::make('subject_id')
                    ->label('Matière')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('level_id')
                    ->label('Niveau')
                    ->relationship('level', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Repeater::make('steps')
                    ->label('Étapes du cas')
                    ->relationship('steps')
                    ->orderColumn('sort_order')
                    ->minItems(1)
                    ->collapsible()
                    ->schema([
                        Textarea::make('prompt')
                            ->label('Contexte / consigne')
                            ->rows(2)
                            ->required(),
                        Textarea::make('question')
                            ->label('Question posée au student')
                            ->rows(2)
                            ->required(),
                        Textarea::make('expected_answer')
                            ->label('Réponse attendue')
                            ->rows(2)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Textarea::make('learning_goals')
                    ->label('Objectifs pédagogiques')
                    ->rows(2)
                    ->columnSpanFull(),
                Select::make('difficulty')
                    ->label('Difficulté')
                    ->options(Difficulty::options())
                    ->default(Difficulty::Medium->value)
                    ->required(),
                Select::make('status')
                    ->label('Statut')
                    ->options(ContentStatus::options())
                    ->default(ContentStatus::Draft->value)
                    ->required(),
                TextInput::make('duration_minutes')
                    ->label('Durée (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->default(20)
                    ->required(),
            ])
            ->columns(2);
    }
}
