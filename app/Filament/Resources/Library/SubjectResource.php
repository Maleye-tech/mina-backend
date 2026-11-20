<?php

namespace App\Filament\Resources\Library;

use App\Filament\Resources\Library\Pages\CreateSubject;
use App\Filament\Resources\Library\Pages\EditSubject;
use App\Filament\Resources\Library\Pages\ListSubjects;
use App\Filament\Resources\Library\Schemas\SubjectForm;
use App\Filament\Resources\Library\Tables\SubjectsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Library\Models\Subject;
use UnitEnum;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Bibliothèque';

    protected static ?string $navigationLabel = 'Matières';

    protected static ?string $modelLabel = 'matière';

    protected static ?string $pluralModelLabel = 'matières';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SubjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubjectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubjects::route('/'),
            'create' => CreateSubject::route('/create'),
            'edit' => EditSubject::route('/{record}/edit'),
        ];
    }
}
