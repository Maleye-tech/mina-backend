<?php

namespace App\Filament\Resources\Assessment\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Assessment\Enums\ContentStatus;

class FlashcardDeckForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(150),
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
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
                Repeater::make('flashcards')
                    ->label('Cartes')
                    ->relationship('flashcards')
                    ->orderColumn('sort_order')
                    ->minItems(1)
                    ->collapsible()
                    ->schema([
                        Textarea::make('front')
                            ->label('Recto')
                            ->rows(2)
                            ->required()
                            ->maxLength(65000),
                        Textarea::make('back')
                            ->label('Verso')
                            ->rows(2)
                            ->required()
                            ->maxLength(65000),
                        TextInput::make('hint')
                            ->label('Indice')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Statut')
                    ->options(ContentStatus::options())
                    ->default(ContentStatus::Draft->value)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ])
            ->columns(2);
    }
}
