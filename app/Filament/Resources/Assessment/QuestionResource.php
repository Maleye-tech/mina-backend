<?php

namespace App\Filament\Resources\Assessment;

use App\Filament\Resources\Assessment\Pages\CreateQuestion;
use App\Filament\Resources\Assessment\Pages\EditQuestion;
use App\Filament\Resources\Assessment\Pages\ListQuestions;
use App\Filament\Resources\Assessment\Schemas\QuestionForm;
use App\Filament\Resources\Assessment\Tables\QuestionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Assessment\Models\Question;
use UnitEnum;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Évaluation';

    protected static ?string $navigationLabel = 'Questions (QCM)';

    protected static ?string $modelLabel = 'question';

    protected static ?string $pluralModelLabel = 'questions';

    protected static ?string $recordTitleAttribute = 'question';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return QuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestions::route('/'),
            'create' => CreateQuestion::route('/create'),
            'edit' => EditQuestion::route('/{record}/edit'),
        ];
    }
}
