<?php

namespace Modules\WellBeing\Filament\Resources\TestResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Gestion des questions d'un test, avec leurs options et leurs points.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Questions';

    protected static ?string $modelLabel = 'question';

    protected static ?string $pluralModelLabel = 'questions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('question_text')
                    ->label('Question')
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Repeater::make('options')
                    ->label('Options de réponse')
                    ->schema([
                        TextInput::make('key')
                            ->label('Clé')
                            ->required()
                            ->maxLength(10)
                            ->helperText('Ex. : a, b, c, d.'),
                        TextInput::make('label')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('points')
                            ->label('Points')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->default(0),
                    ])
                    ->columns(3)
                    ->defaultItems(4)
                    ->minItems(2)
                    ->maxItems(10)
                    ->reorderable()
                    ->addActionLabel('Ajouter une option')
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_text')
                    ->label('Question')
                    ->searchable()
                    ->limit(80),
                TextColumn::make('options')
                    ->label('Options')
                    ->formatStateUsing(fn (?array $state): string => collect($state ?? [])
                        ->map(fn (array $option): string => sprintf(
                            '%s : %s (%d pt)',
                            $option['key'] ?? '?',
                            $option['label'] ?? '?',
                            (int) ($option['points'] ?? 0),
                        ))
                        ->join(' · '))
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id');
    }
}
