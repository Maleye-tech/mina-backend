<?php

namespace App\Filament\Resources\Assessment\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Enums\QuestionType;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Énoncé')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('subject.name')
                    ->label('Matière')
                    ->sortable(),
                TextColumn::make('level.name')
                    ->label('Niveau')
                    ->sortable(),
                TextColumn::make('question_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('difficulty')
                    ->label('Difficulté')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ContentStatus::options()),
                SelectFilter::make('difficulty')
                    ->label('Difficulté')
                    ->options(Difficulty::options()),
                SelectFilter::make('question_type')
                    ->label('Type')
                    ->options(QuestionType::options()),
                SelectFilter::make('subject_id')
                    ->label('Matière')
                    ->relationship('subject', 'name'),
                SelectFilter::make('level_id')
                    ->label('Niveau')
                    ->relationship('level', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }
}
