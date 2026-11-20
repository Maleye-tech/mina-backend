<?php

namespace App\Filament\Resources\Assessment\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Enums\QuestionType;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Textarea::make('question')
                    ->label('Énoncé')
                    ->rows(3)
                    ->required()
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Select::make('question_type')
                    ->label('Type')
                    ->options(QuestionType::options())
                    ->default(QuestionType::Single->value)
                    ->live()
                    ->required(),
                Select::make('difficulty')
                    ->label('Difficulté')
                    ->options(Difficulty::options())
                    ->default(Difficulty::Medium->value)
                    ->required(),
                Repeater::make('options')
                    ->label('Options')
                    ->relationship('options')
                    ->orderColumn('sort_order')
                    ->minItems(2)
                    ->maxItems(6)
                    ->required()
                    ->rule(function (array $state, callable $get): bool|string {
                        $correctCount = count(array_filter(
                            $state,
                            fn (array $option): bool => ! empty($option['is_correct']),
                        ));

                        if ($correctCount === 0) {
                            return 'Au moins une option doit être la bonne réponse.';
                        }

                        $isSingle = ($get('question_type') ?? QuestionType::Single->value) === QuestionType::Single->value;

                        if ($isSingle && $correctCount > 1) {
                            return 'Un QCM à choix unique ne peut avoir qu’une seule bonne réponse.';
                        }

                        return true;
                    })
                    ->schema([
                        TextInput::make('label')
                            ->label('Option')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_correct')
                            ->label('Bonne réponse')
                            ->default(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Statut')
                    ->options(ContentStatus::options())
                    ->default(ContentStatus::Draft->value)
                    ->required(),
                Textarea::make('explanation')
                    ->label('Explication détaillée')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('source_reference')
                    ->label('Référence')
                    ->maxLength(255),
            ])
            ->columns(2);
    }
}
