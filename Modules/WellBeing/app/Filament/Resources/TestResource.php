<?php

namespace Modules\WellBeing\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\WellBeing\Filament\Resources\TestResource\Pages\CreateTest;
use Modules\WellBeing\Filament\Resources\TestResource\Pages\EditTest;
use Modules\WellBeing\Filament\Resources\TestResource\Pages\ListTests;
use Modules\WellBeing\Filament\Resources\TestResource\Pages\ViewTest;
use Modules\WellBeing\Filament\Resources\TestResource\RelationManagers\QuestionsRelationManager;
use Modules\WellBeing\Filament\Resources\TestResource\Schemas\TestForm;
use Modules\WellBeing\Filament\Resources\TestResource\Schemas\TestInfolist;
use Modules\WellBeing\Filament\Resources\TestResource\Tables\TestsTable;
use Modules\WellBeing\Models\AssessmentTest;
use UnitEnum;

/**
 * Administration des tests d'auto-évaluation et de leurs questions.
 */
class TestResource extends Resource
{
    protected static ?string $model = AssessmentTest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Bien-être';

    protected static ?string $navigationLabel = 'Tests d’auto-évaluation';

    protected static ?string $modelLabel = 'test d’auto-évaluation';

    protected static ?string $pluralModelLabel = 'tests d’auto-évaluation';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return TestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTests::route('/'),
            'create' => CreateTest::route('/create'),
            'view' => ViewTest::route('/{record}'),
            'edit' => EditTest::route('/{record}/edit'),
        ];
    }
}
